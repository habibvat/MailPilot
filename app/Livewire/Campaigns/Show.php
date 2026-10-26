<?php

namespace App\Livewire\Campaigns;

use App\Livewire\Campaigns\Concerns\ControlsCampaigns;
use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Services\Campaigns\CampaignStats;
use App\Services\Campaigns\QuotaCalculator;
use App\Services\Campaigns\SendWindow;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use ControlsCampaigns;
    use WithPagination;

    public Campaign $campaign;

    #[Url(except: '')]
    public string $sendStatus = '';

    public function updatedSendStatus(): void
    {
        $this->resetPage();
    }

    public function render(CampaignStats $stats, QuotaCalculator $quotas, SendWindow $window)
    {
        $campaign = $this->campaign->refresh()->load(['template', 'smtpAccount']);
        $now = now();

        $todayCount = $campaign->sends()->whereIn('status', CampaignSend::QUOTA_STATUSES)
            ->where('scheduled_at', '>=', $now->copy()->setTimezone(config('mailpilot.timezone'))->startOfDay())->count();

        $sends = $campaign->sends()->with('contact:id,company_name,email')
            ->when($this->sendStatus !== '', fn ($q) => $q->where('status', $this->sendStatus))
            ->orderByRaw("case status when 'sending' then 0 when 'dispatched' then 1 else 2 end")
            ->orderByDesc('sent_at')->orderBy('id')
            ->paginate(25);

        return view('livewire.campaigns.show', [
            's' => $stats->forMany(collect([$campaign]))[$campaign->id],
            'sends' => $sends,
            'todayCount' => $todayCount,
            'remaining' => $campaign->isRunning() && $campaign->smtpAccount ? $quotas->remaining($campaign, $now) : null,
            'windowOpen' => $window->isOpen($campaign, $now),
            'segment' => $campaign->segment_filters ?? [],
        ])->title($campaign->name);
    }
}
