<?php

namespace App\Livewire\Campaigns\Concerns;

use App\Models\Campaign;
use App\Services\Campaigns\CampaignManager;
use RuntimeException;

/**
 * Actions démarrer / pause / reprendre / arrêter, partagées par la liste et la fiche campagne.
 */
trait ControlsCampaigns
{
    public function startCampaign(int $id, CampaignManager $manager): void
    {
        $this->control(fn () => __('Campagne démarrée : :count contact(s) en file d\'envoi.', ['count' => $manager->start(Campaign::findOrFail($id))]));
    }

    public function pauseCampaign(int $id, CampaignManager $manager): void
    {
        $this->control(fn () => tap(__('Campagne en pause.'), fn () => $manager->pause(Campaign::findOrFail($id))));
    }

    public function resumeCampaign(int $id, CampaignManager $manager): void
    {
        $this->control(fn () => tap(__('Campagne reprise.'), fn () => $manager->resume(Campaign::findOrFail($id))));
    }

    public function stopCampaign(int $id, CampaignManager $manager): void
    {
        $this->control(fn () => tap(__('Campagne arrêtée.'), fn () => $manager->stop(Campaign::findOrFail($id))));
    }

    private function control(callable $action): void
    {
        try {
            $this->dispatch('notify', message: $action());
        } catch (RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }
}
