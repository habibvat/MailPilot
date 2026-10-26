<?php

namespace App\Support;

final class Badge
{
    private const COLORS = [
        'valid' => 'green', 'active' => 'green', 'sent' => 'green', 'completed' => 'green', 'running' => 'blue',
        'risky' => 'amber', 'paused' => 'amber', 'dispatched' => 'blue', 'sending' => 'blue', 'processing' => 'blue',
        'invalid' => 'red', 'bounced' => 'red', 'failed' => 'red', 'stopped' => 'red',
        'unsubscribed' => 'slate', 'excluded' => 'slate', 'skipped' => 'slate', 'draft' => 'slate', 'pending' => 'slate', 'queued' => 'slate',
    ];

    private const CLASSES = [
        'green' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20',
        'blue' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20',
        'amber' => 'bg-amber-50 text-amber-800 ring-1 ring-amber-600/20',
        'red' => 'bg-red-50 text-red-700 ring-1 ring-red-600/20',
        'slate' => 'bg-slate-100 text-slate-600 ring-1 ring-slate-500/20',
    ];

    public static function classes(?string $status): string
    {
        return self::CLASSES[self::COLORS[$status] ?? 'slate'];
    }
}
