<?php

namespace App\Services\Campaigns;

use App\Jobs\SendCampaignEmailJob;
use App\Models\Campaign;
use App\Models\CampaignSend;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Appelé chaque minute : pour chaque campagne en cours, calcule les envois autorisés
 * et place les jobs dans la file en les espaçant d'un délai aléatoire.
 */
class SendDispatcher
{
    /** Un job non démarré au-delà de ce délai après son horaire est remis en file. */
    public const LATE_AFTER_MINUTES = 5;

    /** Un job planifié dont on n'a plus de nouvelles après ce délai est considéré perdu. */
    public const LOST_AFTER_MINUTES = 15;

    /** Un envoi resté « en cours » au-delà de ce délai a été interrompu. */
    public const INTERRUPTED_AFTER_MINUTES = 10;

    public function __construct(
        private QuotaCalculator $quotas,
        private SendWindow $window,
        private CircuitBreaker $breaker,
        private CampaignManager $manager,
    ) {}

    /**
     * @return array<int,int> campagne => nombre de jobs placés
     */
    public function tick(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $result = [];

        $campaigns = Campaign::where('status', 'running')->with(['smtpAccount', 'template'])->orderBy('id')->get();

        foreach ($campaigns as $campaign) {
            $this->recover($campaign, $now);

            if ($this->breaker->check($campaign) || $this->manager->completeIfFinished($campaign)) {
                continue;
            }

            if (! $campaign->smtpAccount || ! $campaign->template) {
                $campaign->update(['status' => 'stopped', 'stop_reason' => __('Le template ou le compte SMTP de la campagne a été supprimé.')]);

                continue;
            }

            if (! $this->window->isOpen($campaign, $now)) {
                continue;
            }

            $result[$campaign->id] = $this->dispatchFor($campaign, $now);
        }

        return $result;
    }

    private function dispatchFor(Campaign $campaign, CarbonInterface $now): int
    {
        $allowed = $this->quotas->remaining($campaign, $now);
        if ($allowed === 0) {
            return 0;
        }

        // Les envois sont espacés à partir du dernier créneau planifié, sur un horizon d'une minute.
        $cursor = $campaign->next_send_at && $campaign->next_send_at->gt($now) ? $campaign->next_send_at->copy() : $now->copy();
        $horizon = $now->copy()->addMinute();
        $dispatched = 0;

        $sends = $campaign->sends()->where('status', 'queued')->orderBy('id')->limit($allowed)->pluck('id');

        foreach ($sends as $sendId) {
            if ($cursor->gte($horizon)) {
                break;
            }

            $token = (string) Str::uuid();
            $claimed = CampaignSend::whereKey($sendId)->where('status', 'queued')->update([
                'status' => 'dispatched',
                'dispatch_token' => $token,
                'scheduled_at' => $cursor,
                'updated_at' => $now,
            ]);

            if ($claimed) {
                SendCampaignEmailJob::dispatch($sendId, $token)->delay($cursor);
                $dispatched++;
                $cursor = $cursor->copy()->addSeconds(random_int($campaign->min_delay, max($campaign->min_delay, $campaign->max_delay)));
            }
        }

        if ($dispatched > 0) {
            $campaign->update(['next_send_at' => $cursor]);
        }

        return $dispatched;
    }

    /**
     * Reprise après une fermeture de l'application ou un arrêt brutal du worker.
     */
    private function recover(Campaign $campaign, CarbonInterface $now): void
    {
        // Jobs planifiés jamais exécutés (file vidée, job perdu) : retour en file avec un nouveau jeton.
        // Si l'ancien job s'exécute plus tard, son jeton ne correspond plus et il ne fait rien.
        $campaign->sends()->where('status', 'dispatched')
            ->where('scheduled_at', '<', $now->copy()->subMinutes(self::LOST_AFTER_MINUTES))
            ->update(['status' => 'queued', 'dispatch_token' => null, 'scheduled_at' => null, 'updated_at' => $now]);

        // Envoi interrompu pendant la session SMTP : on ne sait pas si le message est parti.
        // Il n'est jamais renvoyé, pour garantir l'absence de doublon.
        $campaign->sends()->where('status', 'sending')
            ->where('locked_at', '<', $now->copy()->subMinutes(self::INTERRUPTED_AFTER_MINUTES))
            ->update([
                'status' => 'failed',
                'error' => __('Envoi interrompu (fermeture de l\'application) : non renvoyé pour éviter un doublon.'),
                'locked_at' => null,
                'updated_at' => $now,
            ]);
    }
}
