<?php

namespace App\Livewire\Campaigns;

use App\Livewire\Campaigns\Concerns\ControlsCampaigns;
use App\Models\Campaign;
use App\Services\Campaigns\CampaignStats;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Campagnes')]
class Index extends Component
{
    use ControlsCampaigns;

    public function delete(int $id): void
    {
        $campaign = Campaign::findOrFail($id);
        if (! in_array($campaign->status, ['draft', 'completed', 'stopped'], true)) {
            $this->dispatch('notify', type: 'error', message: __('Mettez d\'abord la campagne en pause puis arrêtez-la.'));

            return;
        }

        $campaign->delete();
        $this->dispatch('notify', message: __('Campagne supprimée.'));
    }

    public function render(CampaignStats $stats)
    {
        $campaigns = Campaign::with(['template:id,name', 'smtpAccount:id,label'])->latest('id')->get();

        return view('livewire.campaigns.index', [
            'campaigns' => $campaigns,
            'stats' => $stats->forMany($campaigns),
            'polling' => $campaigns->contains('status', 'running'),
        ]);
    }
}
