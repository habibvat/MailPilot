<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use Carbon\CarbonInterface;

/**
 * Jours et heures d'envoi d'une campagne, évalués dans le fuseau horaire de l'application (APP_TIMEZONE).
 */
class SendWindow
{
    public function isOpen(Campaign $campaign, CarbonInterface $at): bool
    {
        $local = $at->copy()->setTimezone(config('mailpilot.timezone'));

        $days = array_map('intval', $campaign->send_days ?? []);
        if ($days !== [] && ! in_array($local->dayOfWeekIso, $days, true)) {
            return false;
        }

        $time = $local->format('H:i');

        return $time >= $campaign->send_start && $time < $campaign->send_end;
    }
}
