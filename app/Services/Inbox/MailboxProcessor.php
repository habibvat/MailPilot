<?php

namespace App\Services\Inbox;

use App\Models\SmtpAccount;
use App\Services\Mail\ImapClientFactory;
use Webklex\PHPIMAP\Message;

/**
 * Relève la boîte IMAP d'un compte. Les messages sont lus sans être marqués comme lus :
 * on mémorise le dernier UID traité.
 */
class MailboxProcessor
{
    public const BATCH = 200;

    public function __construct(private ImapClientFactory $clients, private InboundMessageHandler $handler) {}

    /**
     * @return array<string,int> nombre de messages par type
     */
    public function process(SmtpAccount $account): array
    {
        $client = $this->clients->make($account);
        $client->connect();
        $stats = [];

        try {
            $folder = $client->getFolder('INBOX') ?? throw new \RuntimeException(__('Dossier INBOX introuvable.'));
            $query = $folder->query()->leaveUnread()->setFetchOrderAsc();

            $messages = $account->imap_last_uid === null
                ? $query->since(now()->subDays(14))->limit(self::BATCH)->get()   // premier relevé : 14 derniers jours
                : $query->getByUidGreater($account->imap_last_uid);

            $lastUid = $account->imap_last_uid ?? 0;
            foreach ($messages->sortBy(fn (Message $m) => (int) $m->getUid())->take(self::BATCH) as $message) {
                $result = $this->handler->handle($this->toInbound($message), $account);
                $stats[$result['type']] = ($stats[$result['type']] ?? 0) + 1;
                $lastUid = max($lastUid, (int) $message->getUid());
            }

            $account->forceFill(['imap_last_uid' => $lastUid, 'imap_checked_at' => now()])->save();
        } finally {
            $client->disconnect();
        }

        return $stats;
    }

    private function toInbound(Message $message): InboundMessage
    {
        $text = $message->getTextBody();
        if (trim($text) === '') {
            $text = html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $message->getHTMLBody())), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return new InboundMessage(
            from: (string) ($message->getFrom()->first()?->mail ?? ''),
            subject: (string) $message->getSubject(),
            text: $text,
            rawHeaders: (string) ($message->getHeader()?->raw ?? ''),
            rawBody: $message->getRawBody(),
            referencedIds: InboundMessage::parseIds((string) $message->getInReplyTo(), (string) $message->getReferences()),
        );
    }
}
