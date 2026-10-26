<div @if ($polling) wire:poll.15s @endif>
    <x-page-header :title="__('Campagnes')" :subtitle="__('Envois par vagues contrôlées, un email par destinataire.')">
        <x-slot:actions>
            <a href="{{ route('campaigns.create') }}" wire:navigate class="btn-primary">{{ __('Nouvelle campagne') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
                <tr><th>{{ __('Campagne') }}</th><th>{{ __('Statut') }}</th><th class="w-48">{{ __('Progression') }}</th><th class="text-right">{{ __('Envoyés') }}</th><th class="text-right">{{ __('Échecs') }}</th><th class="text-right">{{ __('Rebonds') }}</th><th class="text-right">{{ __('Réponses') }}</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($campaigns as $campaign)
                    @php $s = $stats[$campaign->id]; @endphp
                    <tr wire:key="campaign-{{ $campaign->id }}">
                        <td>
                            <a href="{{ route('campaigns.show', $campaign) }}" wire:navigate class="font-medium text-slate-900 hover:underline">{{ $campaign->name }}</a>
                            <div class="text-xs text-slate-500">{{ $campaign->template?->name ?? __('Template supprimé') }} · {{ $campaign->smtpAccount?->label ?? __('Compte supprimé') }}</div>
                        </td>
                        <td>
                            <x-badge :status="$campaign->status" :label="\App\Models\Campaign::STATUSES[$campaign->status]" />
                            @if ($campaign->stop_reason)<div class="mt-1 max-w-56 text-xs text-red-600">{{ __($campaign->stop_reason) }}</div>@endif
                        </td>
                        <td>
                            @if ($campaign->status === 'draft')
                                <span class="text-xs text-slate-400">{{ __('Non démarrée') }}</span>
                            @else
                                <x-progress :value="$s['progress']" />
                                <div class="mt-1 text-xs text-slate-500">{{ __(':progress % · :count restant(s)', ['progress' => $s['progress'], 'count' => number_format($s['pending'], 0, ',', ' ')]) }}</div>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ number_format($s['sent'], 0, ',', ' ') }}</td>
                        <td class="text-right tabular-nums">{{ $s['failed'] }} <span class="text-xs text-slate-400">({{ $s['failure_rate'] }} %)</span></td>
                        <td class="text-right tabular-nums">{{ $s['bounced'] }} <span class="text-xs {{ $s['bounce_rate'] > 5 ? 'font-semibold text-red-600' : 'text-slate-400' }}">({{ $s['bounce_rate'] }} %)</span></td>
                        <td class="text-right tabular-nums">{{ $s['replied'] }}</td>
                        <td>
                            <div class="flex items-center justify-end gap-2">
                                <x-campaign-actions :campaign="$campaign" small />
                                @if (in_array($campaign->status, ['draft', 'completed', 'stopped']))
                                    <button type="button" wire:click="delete({{ $campaign->id }})" wire:confirm="{{ __('Supprimer la campagne et son historique d\'envois ?') }}" class="btn-secondary btn-sm text-red-600">{{ __('Supprimer') }}</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-slate-500">{{ __('Aucune campagne.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
