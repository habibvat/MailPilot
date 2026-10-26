<?php

namespace App\Console\Commands;

use App\Services\Campaigns\SendDispatcher;
use App\Support\Locale;
use Illuminate\Console\Command;

class DispatchCampaignSends extends Command
{
    protected $signature = 'mailpilot:dispatch';

    protected $description = 'Planifie les envois autorisés des campagnes en cours';

    public function handle(SendDispatcher $dispatcher): int
    {
        Locale::apply();

        foreach ($dispatcher->tick() as $campaignId => $count) {
            if ($count > 0) {
                $this->line("Campagne #$campaignId : $count envoi(s) planifié(s).");
            }
        }

        return self::SUCCESS;
    }
}
