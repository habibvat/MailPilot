<?php

namespace App\Services\Inbox;

/**
 * Message reçu, indépendamment de la bibliothèque IMAP.
 */
final class InboundMessage
{
    /**
     * @param  list<string>  $referencedIds  Message-ID cités dans In-Reply-To / References (sans chevrons)
     */
    public function __construct(
        public readonly string $from,
        public readonly string $subject,
        public readonly string $text,
        public readonly string $rawHeaders = '',
        public readonly string $rawBody = '',
        public readonly array $referencedIds = [],
    ) {}

    /**
     * @return list<string>
     */
    public static function parseIds(string ...$values): array
    {
        preg_match_all('/<([^<>\s]+)>/', implode(' ', $values), $m);

        return array_values(array_unique($m[1]));
    }
}
