<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignSend;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Transitions d'état d'une campagne : démarrer, pause, reprendre, arrêter.
 */
class CampaignManager
{
    public function __construct(private CampaignAudience $audience, private CircuitBreaker $breaker) {}

    /**
     * Crée les envois pour les contacts éligibles et passe la campagne en cours.
     *
     * @return int nombre de contacts ajoutés à la file
     */
    public function start(Campaign $campaign): int
    {
        if ($campaign->status !== 'draft') {
            throw new RuntimeException(__('Seule une campagne en brouillon peut être démarrée.'));
        }
        if (! $campaign->template || ! $campaign->smtpAccount) {
            throw new RuntimeException(__('Choisissez un template et un compte SMTP avant de démarrer.'));
        }

        return DB::transaction(function () use ($campaign) {
            $now = now();
            $eligible = $this->audience->eligible($campaign->segment_filters ?? [])
                ->select([
                    DB::raw($campaign->id.' as campaign_id'),
                    'contacts.id as contact_id',
                    DB::raw("'queued' as status"),
                    DB::raw('0 as attempts'),
                    DB::raw($this->quote($now).' as created_at'),
                    DB::raw($this->quote($now).' as updated_at'),
                ]);

            $count = DB::table('campaign_sends')->insertOrIgnoreUsing(
                ['campaign_id', 'contact_id', 'status', 'attempts', 'created_at', 'updated_at'],
                $eligible->toBase()
            );

            if ($count === 0) {
                throw new RuntimeException('Aucun contact éligible dans ce segment (vérifiés valides, actifs, hors liste noire, non contactés depuis '.config('mailpilot.recontact_days').' jours).');
            }

            $campaign->update([
                'status' => 'running',
                'stop_reason' => null,
                'consecutive_failures' => 0,
                'next_send_at' => null,
                'started_at' => $now,
            ]);

            return $count;
        });
    }

    public function pause(Campaign $campaign): void
    {
        $this->expect($campaign, ['running']);
        $campaign->update(['status' => 'paused', 'next_send_at' => null]);
    }

    public function resume(Campaign $campaign): void
    {
        $this->expect($campaign, ['paused', 'stopped']);
        $campaign->update(['status' => 'running', 'stop_reason' => null, 'consecutive_failures' => 0, 'next_send_at' => null]);

        // Si la cause d'un arrêt automatique est toujours présente, la campagne est de nouveau arrêtée.
        if ($this->breaker->check($campaign)) {
            throw new RuntimeException($campaign->fresh()->stop_reason);
        }
    }

    public function stop(Campaign $campaign, ?string $reason = null): void
    {
        $this->expect($campaign, ['running', 'paused']);
        $campaign->update(['status' => 'stopped', 'stop_reason' => $reason ?? __('Arrêtée manuellement.'), 'next_send_at' => null]);
    }

    /** Passe la campagne en terminée s'il ne reste rien à envoyer. */
    public function completeIfFinished(Campaign $campaign): bool
    {
        $pending = $campaign->sends()->whereIn('status', CampaignSend::PENDING_STATUSES)->exists();
        if ($pending) {
            return false;
        }

        return Campaign::whereKey($campaign->id)->where('status', 'running')
            ->update(['status' => 'completed', 'completed_at' => now(), 'next_send_at' => null]) > 0;
    }

    private function expect(Campaign $campaign, array $statuses): void
    {
        if (! in_array($campaign->status, $statuses, true)) {
            throw new RuntimeException(__('Action impossible pour une campagne « :status ».', ['status' => __(Campaign::STATUSES[$campaign->status] ?? $campaign->status)]));
        }
    }

    private function quote(\DateTimeInterface $date): string
    {
        return DB::connection()->getPdo()->quote($date->format('Y-m-d H:i:s'));
    }
}
