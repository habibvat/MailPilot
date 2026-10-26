<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;

/**
 * Arrête une campagne quand le taux de rebond ou les échecs SMTP consécutifs deviennent anormaux.
 */
class CircuitBreaker
{
    /**
     * @return bool true si la campagne vient d'être arrêtée
     */
    public function check(Campaign $campaign): bool
    {
        $campaign->refresh();
        if (! $campaign->isRunning()) {
            return false;
        }

        $reason = $this->reason($campaign);
        if ($reason === null) {
            return false;
        }

        $campaign->update(['status' => 'stopped', 'stop_reason' => $reason, 'next_send_at' => null]);

        return true;
    }

    public function reason(Campaign $campaign): ?string
    {
        $max = config('mailpilot.max_consecutive_failures');
        if ($campaign->consecutive_failures >= $max) {
            return __('Disjoncteur : :max échecs SMTP consécutifs. Vérifiez le compte d\'envoi avant de reprendre.', ['max' => $max]);
        }

        $counts = $campaign->sendCounts();
        $delivered = $counts['sent'] + $counts['bounced'];
        if ($delivered >= config('mailpilot.bounce_rate_min_sends') && $delivered > 0) {
            $rate = $counts['bounced'] / $delivered;
            if ($rate > config('mailpilot.bounce_rate_threshold')) {
                return __('Disjoncteur : taux de rebond de :rate % sur :count envois (seuil :threshold %).', [
                    'rate' => number_format($rate * 100, 1, ',', ' '),
                    'count' => $delivered,
                    'threshold' => number_format(config('mailpilot.bounce_rate_threshold') * 100, 0),
                ]);
            }
        }

        return null;
    }
}
