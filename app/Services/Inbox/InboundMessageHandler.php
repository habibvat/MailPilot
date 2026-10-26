<?php

namespace App\Services\Inbox;

use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Models\Suppression;
use App\Services\Campaigns\CircuitBreaker;
use App\Support\EmailParser;

/**
 * Classe un message reçu (rebond, désinscription, réponse) et met à jour contacts, envois et liste noire.
 */
class InboundMessageHandler
{
    private const BOUNCE_SENDERS = ['mailer-daemon', 'postmaster', 'mail-delivery-subsystem', 'mail-daemon', 'bounce', 'bounces'];

    private const BOUNCE_SUBJECT = '/undeliver|undelivered|delivery status notification|delivery has failed|delivery failure|failure notice|returned mail|mail delivery failed|non remis|non distribuable|échec de (la )?(remise|distribution)|impossible de remettre|rejected/iu';

    private const UNSUBSCRIBE = '/d[ée]sinscri|unsubscribe|\bstop\b/iu';

    public function __construct(private CircuitBreaker $breaker) {}

    /**
     * @return array{type: string, email: ?string}
     */
    public function handle(InboundMessage $message, SmtpAccount $account): array
    {
        $from = EmailParser::clean($message->from);
        if (in_array($from, [mb_strtolower($account->from_email), mb_strtolower((string) $account->imap_username)], true)) {
            return ['type' => 'ignored', 'email' => null];
        }

        if ($this->isBounce($message, $from)) {
            return ['type' => 'bounce', 'email' => $this->handleBounce($message, $account)];
        }

        if ($this->isUnsubscribe($message)) {
            Suppression::add($from, 'unsubscribe');
            Contact::where('email', $from)->update(['status' => 'unsubscribed', 'updated_at' => now()]);

            return ['type' => 'unsubscribe', 'email' => $from];
        }

        $send = $this->findReferencedSend($message) ?? $this->latestSendTo($from, $account);
        if ($send !== null) {
            $send->replied_at ??= now();
            $send->save();
            Contact::whereKey($send->contact_id)->whereNull('replied_at')->update(['replied_at' => now()]);

            return ['type' => 'reply', 'email' => $send->contact->email];
        }

        return ['type' => 'other', 'email' => null];
    }

    public function isBounce(InboundMessage $message, string $from): bool
    {
        $local = EmailParser::localPart($from);

        return in_array($local, self::BOUNCE_SENDERS, true)
            || preg_match('/report-type\s*=\s*"?delivery-status/i', $message->rawHeaders.$message->rawBody) === 1
            || (preg_match(self::BOUNCE_SUBJECT, $message->subject) === 1
                && preg_match('/^(Final-Recipient|X-Failed-Recipients|Diagnostic-Code):/mi', $message->rawHeaders."\n".$message->rawBody."\n".$message->text) === 1);
    }

    public function isUnsubscribe(InboundMessage $message): bool
    {
        return preg_match(self::UNSUBSCRIBE, $message->subject) === 1
            || preg_match(self::UNSUBSCRIBE, $this->ownContent($message->text)) === 1;
    }

    /**
     * Texte écrit par l'expéditeur, sans le message d'origine cité (qui contient notre propre pied de page).
     */
    public function ownContent(string $text): string
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $trimmed = trim($line);
            if (preg_match('/^(le\s.+a\s[ée]crit\s?:|on\s.+wrote:|-{2,}\s*(original message|message d.origine|message transféré)|-{2}\s*$|_{5,}|(de|from|envoy[ée]|sent)\s?:\s)/iu', $trimmed)) {
                break;
            }
            if (! str_starts_with($trimmed, '>')) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    private function handleBounce(InboundMessage $message, SmtpAccount $account): ?string
    {
        $raw = $message->rawHeaders."\n".$message->rawBody."\n".$message->text;
        $send = null;

        if (preg_match('/^X-MailPilot-Send:\s*(\d+)/mi', $raw, $m)) {
            $send = CampaignSend::find((int) $m[1]);
        }
        $send ??= $this->findReferencedSend($message, $raw);

        $email = $this->failedRecipient($raw) ?? $send?->contact?->email;
        if ($email === null) {
            return null;
        }

        $send ??= $this->latestSendTo($email, $account);

        Suppression::add($email, 'bounce');
        Contact::where('email', $email)->update(['status' => 'bounced', 'updated_at' => now()]);

        if ($send && in_array($send->status, ['sent', 'failed'], true)) {
            $send->update(['status' => 'bounced', 'error' => 'Rebond reçu par email']);
            $this->breaker->check(Campaign::findOrFail($send->campaign_id));
        }

        return $email;
    }

    private function failedRecipient(string $raw): ?string
    {
        $patterns = [
            '/^Final-Recipient:\s*rfc822\s*;\s*<?([^\s<>;]+@[^\s<>;]+)>?/mi',
            '/^Original-Recipient:\s*rfc822\s*;\s*<?([^\s<>;]+@[^\s<>;]+)>?/mi',
            '/^X-Failed-Recipients:\s*<?([^\s<>,;]+@[^\s<>,;]+)>?/mi',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $raw, $m) && EmailParser::isValid(EmailParser::clean($m[1]))) {
                return EmailParser::clean($m[1]);
            }
        }

        // Dernier recours : première adresse citée qui correspond à un contact déjà contacté.
        preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $raw, $m);
        $candidates = array_unique(array_map(fn ($e) => EmailParser::clean($e), $m[0]));
        if ($candidates === []) {
            return null;
        }

        return Contact::whereIn('email', $candidates)->whereNotNull('last_contacted_at')->value('email');
    }

    private function findReferencedSend(InboundMessage $message, string $extra = ''): ?CampaignSend
    {
        $ids = $message->referencedIds;
        if ($extra !== '' && preg_match_all('/^(?:Message-ID|In-Reply-To|References):\s*(.+)$/mi', $extra, $m)) {
            $ids = [...$ids, ...InboundMessage::parseIds(...$m[1])];
        }

        return $ids === [] ? null : CampaignSend::whereIn('message_id', array_unique($ids))->with('contact')->first();
    }

    private function latestSendTo(string $email, SmtpAccount $account): ?CampaignSend
    {
        return CampaignSend::query()
            ->whereIn('campaign_sends.status', ['sent', 'bounced'])
            ->whereHas('contact', fn ($q) => $q->where('email', $email))
            ->whereHas('campaign', fn ($q) => $q->where('smtp_account_id', $account->id))
            ->with('contact')
            ->latest('sent_at')
            ->first();
    }
}
