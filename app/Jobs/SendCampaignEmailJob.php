<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use App\Models\Suppression;
use App\Services\Campaigns\CampaignManager;
use App\Services\Campaigns\CircuitBreaker;
use App\Services\Campaigns\SendDispatcher;
use App\Services\Campaigns\SendWindow;
use App\Services\Mail\Mailer;
use App\Services\Mail\TemplateRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Envoie un email de campagne à un seul destinataire.
 */
class SendCampaignEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $sendId, public string $token)
    {
        // File prioritaire : les envois ne sont pas retardés par les vérifications en masse.
        $this->onQueue('sends');
    }

    public function handle(
        Mailer $mailer,
        TemplateRenderer $renderer,
        SendWindow $window,
        CircuitBreaker $breaker,
        CampaignManager $manager,
    ): void {
        // Verrou : seul le job porteur du jeton courant peut passer l'envoi de « planifié » à « en cours ».
        $claimed = CampaignSend::whereKey($this->sendId)
            ->where('status', 'dispatched')
            ->where('dispatch_token', $this->token)
            ->update(['status' => 'sending', 'locked_at' => now(), 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $send = CampaignSend::with(['campaign.template', 'campaign.smtpAccount', 'contact'])->findOrFail($this->sendId);
        $campaign = $send->campaign;

        // Campagne en pause / arrêtée, hors créneau, ou job exécuté en retard (application fermée) :
        // l'envoi retourne en file et sera replanifié avec l'espacement normal.
        if (! $campaign->isRunning()
            || ! $window->isOpen($campaign, now())
            || $send->scheduled_at?->lt(now()->subMinutes(SendDispatcher::LATE_AFTER_MINUTES))) {
            $this->requeue($send);

            return;
        }

        $contact = $send->contact;
        if ($reason = $this->ineligibility($contact)) {
            $send->update(['status' => 'skipped', 'error' => $reason, 'locked_at' => null]);
            $manager->completeIfFinished($campaign);

            return;
        }

        $send->increment('attempts');

        try {
            $rendered = $renderer->render($campaign->template, $contact, $campaign->smtpAccount);
            $messageId = $mailer->send($campaign->smtpAccount, $contact->email, $rendered, [
                'X-MailPilot-Send' => (string) $send->id,
            ]);

            $send->update(['status' => 'sent', 'message_id' => $messageId, 'sent_at' => now(), 'error' => null, 'locked_at' => null]);
            $contact->update(['last_contacted_at' => now()]);
            Campaign::whereKey($campaign->id)->update(['consecutive_failures' => 0]);
        } catch (TransportExceptionInterface $e) {
            $this->handleTransportFailure($send, $contact, $e);
        } catch (Throwable $e) {
            $send->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'locked_at' => null]);
            Campaign::whereKey($campaign->id)->increment('consecutive_failures');
        }

        if (! $breaker->check($campaign)) {
            $manager->completeIfFinished($campaign);
        }
    }

    private function handleTransportFailure(CampaignSend $send, Contact $contact, TransportExceptionInterface $e): void
    {
        $code = (int) $e->getCode();
        $message = mb_substr($e->getMessage(), 0, 1000);

        // Destinataire refusé définitivement par le serveur : rebond immédiat.
        if (in_array($code, [550, 551, 553], true)
            && preg_match('/user|mailbox|recipient|address|unknown|exist|not found|invalid|introuvable/i', $message)) {
            $send->update(['status' => 'bounced', 'error' => "[$code] $message", 'locked_at' => null]);
            $contact->update(['status' => 'bounced']);
            Suppression::add($contact->email, 'bounce');

            return;
        }

        Campaign::whereKey($send->campaign_id)->increment('consecutive_failures');

        // Erreur temporaire (4xx, connexion) : nouvelle tentative plus tard, dans la limite autorisée.
        $temporary = $code === 0 || ($code >= 400 && $code < 500);
        if ($temporary && $send->attempts < config('mailpilot.max_attempts')) {
            $send->update(['status' => 'queued', 'dispatch_token' => null, 'scheduled_at' => null, 'locked_at' => null, 'error' => $message]);

            return;
        }

        $send->update(['status' => 'failed', 'error' => ($code ? "[$code] " : '').$message, 'locked_at' => null]);
    }

    private function requeue(CampaignSend $send): void
    {
        $send->update(['status' => 'queued', 'dispatch_token' => null, 'scheduled_at' => null, 'locked_at' => null]);
    }

    private function ineligibility(Contact $contact): ?string
    {
        return match (true) {
            Suppression::contains($contact->email) => __('Adresse en liste noire'),
            $contact->status !== 'active' => __('Contact :status', ['status' => mb_strtolower(__(Contact::STATUSES[$contact->status] ?? $contact->status))]),
            $contact->verify_status !== 'valid' => __('Email non valide'),
            $contact->last_contacted_at?->gt(now()->subDays(config('mailpilot.recontact_days'))) ?? false => __('Déjà contacté il y a moins de :days jours', ['days' => config('mailpilot.recontact_days')]),
            default => null,
        };
    }
}
