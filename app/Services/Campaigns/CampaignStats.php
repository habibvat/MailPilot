<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignSend;
use Illuminate\Support\Collection;

/**
 * Indicateurs d'avancement et de qualité d'une ou plusieurs campagnes.
 */
class CampaignStats
{
    /**
     * @param  Collection<int,Campaign>  $campaigns
     * @return array<int,array<string,int|float>> campagne => indicateurs
     */
    public function forMany(Collection $campaigns): array
    {
        $ids = $campaigns->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $rows = CampaignSend::whereIn('campaign_id', $ids)
            ->selectRaw('campaign_id, status, count(*) as total, sum(case when replied_at is not null then 1 else 0 end) as replied')
            ->groupBy('campaign_id', 'status')->get();

        $stats = [];
        foreach ($ids as $id) {
            $group = $rows->where('campaign_id', $id);
            $counts = array_fill_keys(CampaignSend::STATUSES_KEYS, 0);
            foreach ($group as $row) {
                $counts[$row->status] = (int) $row->total;
            }
            $stats[$id] = $this->compute($counts, (int) $group->sum('replied'));
        }

        return $stats;
    }

    /**
     * @param  array<string,int>  $counts
     * @return array<string,int|float>
     */
    public function compute(array $counts, int $replied): array
    {
        $total = array_sum($counts);
        $attempted = $counts['sent'] + $counts['failed'] + $counts['bounced'];
        $done = $attempted + $counts['skipped'];
        $delivered = $counts['sent'] + $counts['bounced'];

        return $counts + [
            'total' => $total,
            'pending' => $counts['queued'] + $counts['dispatched'] + $counts['sending'],
            'progress' => $total > 0 ? round($done / $total * 100, 1) : 0.0,
            'failure_rate' => $attempted > 0 ? round($counts['failed'] / $attempted * 100, 1) : 0.0,
            'bounce_rate' => $delivered > 0 ? round($counts['bounced'] / $delivered * 100, 1) : 0.0,
            'replied' => $replied,
            'reply_rate' => $counts['sent'] > 0 ? round($replied / $counts['sent'] * 100, 1) : 0.0,
        ];
    }
}
