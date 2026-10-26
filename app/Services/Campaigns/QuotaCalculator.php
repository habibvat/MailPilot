<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignSend;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Nombre d'envois encore autorisés : le plus petit des quotas horaire / journalier
 * de la campagne et de son compte SMTP (tous comptes confondus pour ce dernier).
 */
class QuotaCalculator
{
    public function remaining(Campaign $campaign, CarbonInterface $now): int
    {
        $account = $campaign->smtpAccount;
        $hourAgo = $now->copy()->subHour();
        $startOfDay = $now->copy()->setTimezone(config('mailpilot.timezone'))->startOfDay();

        $campaignSends = fn () => $this->consumed()->where('campaign_id', $campaign->id);
        $accountSends = fn () => $this->consumed()->whereIn('campaign_id', Campaign::select('id')->where('smtp_account_id', $account->id));

        return max(0, min(
            $campaign->hourly_limit - $campaignSends()->where('scheduled_at', '>', $hourAgo)->count(),
            $campaign->daily_limit - $campaignSends()->where('scheduled_at', '>=', $startOfDay)->count(),
            $account->hourly_limit - $accountSends()->where('scheduled_at', '>', $hourAgo)->count(),
            $account->daily_limit - $accountSends()->where('scheduled_at', '>=', $startOfDay)->count(),
        ));
    }

    /** Envois du jour pour un compte (planifiés ou tentés). */
    public function sentTodayForAccount(int $accountId, CarbonInterface $now): int
    {
        return $this->consumed()
            ->whereIn('campaign_id', Campaign::select('id')->where('smtp_account_id', $accountId))
            ->where('scheduled_at', '>=', $now->copy()->setTimezone(config('mailpilot.timezone'))->startOfDay())
            ->count();
    }

    private function consumed(): Builder
    {
        return CampaignSend::query()->whereIn('status', CampaignSend::QUOTA_STATUSES);
    }
}
