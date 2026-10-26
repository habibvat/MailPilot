<?php

namespace App\Services\Mail;

final class RenderedEmail
{
    public function __construct(
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text,
    ) {}
}
