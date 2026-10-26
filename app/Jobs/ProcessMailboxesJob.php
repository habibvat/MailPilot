<?php

namespace App\Jobs;

use App\Models\SmtpAccount;
use App\Services\Inbox\MailboxProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Relève les boîtes IMAP de tous les comptes configurés (planifié toutes les 15 minutes).
 */
class ProcessMailboxesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(MailboxProcessor $processor): void
    {
        foreach (SmtpAccount::whereNotNull('imap_host')->whereNotNull('imap_username')->get() as $account) {
            try {
                $stats = $processor->process($account);
                Log::info("Relevé IMAP {$account->label}", $stats);
            } catch (\Throwable $e) {
                Log::warning("Relevé IMAP impossible pour {$account->label} : {$e->getMessage()}");
            }
        }
    }
}
