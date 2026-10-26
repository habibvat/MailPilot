<div @if ($campaign->isRunning()) wire:poll.10s @endif>
    <x-page-header :title="$campaign->name">
        <x-slot:actions>
            <a href="{{ route('campaigns.index') }}" wire:navigate class="btn-secondary">{{ __('Toutes les campagnes') }}</a>
            <x-campaign-actions :campaign="$campaign" />
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-center gap-3 text-sm">
        <x-badge :status="$campaign->status" :label="\App\Models\Campaign::STATUSES[$campaign->status]" />
        @if ($campaign->isRunning())
            <span class="{{ $windowOpen ? 'text-emerald-700' : 'text-slate-500' }}">
                {{ $windowOpen ? __('Créneau d\'envoi ouvert') : __('Hors créneau d\'envoi : reprise automatique au prochain créneau') }}
            </span>
        @endif
        @if ($campaign->started_at)<span class="text-slate-500">{{ __('Démarrée le :date', ['date' => $campaign->started_at->format('d/m/Y H:i')]) }}</span>@endif
        @if ($campaign->completed_at)<span class="text-slate-500">· {{ __('Terminée le :date', ['date' => $campaign->completed_at->format('d/m/Y H:i')]) }}</span>@endif
    </div>

    @if ($campaign->stop_reason)
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ __($campaign->stop_reason) }}</div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['Destinataires', number_format($s['total'], 0, ',', ' '), null],
            ['Envoyés', number_format($s['sent'], 0, ',', ' '), null],
            ['Échecs', $s['failed'], $s['failure_rate'].' %'],
            ['Rebonds', $s['bounced'], $s['bounce_rate'].' %'],
            ['Réponses', $s['replied'], $s['reply_rate'].' %'],
        ] as [$label, $value, $rate])
            <div class="card card-body">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __($label) }}</div>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-semibold tabular-nums text-slate-900">{{ $value }}</span>
                    @if ($rate)<span class="text-sm text-slate-500">{{ $rate }}</span>@endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        <div class="card card-body lg:col-span-2">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-medium text-slate-900">{{ __('Progression') }}</span>
                <span class="text-slate-500">{{ __(':progress % · :pending en attente · :skipped ignoré(s)', ['progress' => $s['progress'], 'pending' => number_format($s['pending'], 0, ',', ' '), 'skipped' => $s['skipped']]) }}</span>
            </div>
            <x-progress :value="$s['progress']" />
            <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                <div><dt class="text-slate-500">{{ __('Aujourd\'hui') }}</dt><dd class="font-medium tabular-nums">{{ $todayCount }} / {{ $campaign->daily_limit }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Quota horaire') }}</dt><dd class="font-medium">{{ $campaign->hourly_limit }}/h</dd></div>
                <div><dt class="text-slate-500">{{ __('Encore autorisés maintenant') }}</dt><dd class="font-medium tabular-nums">{{ $remaining ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Jours') }}</dt><dd class="font-medium">{{ collect($campaign->send_days)->sort()->map(fn ($d) => __(\App\Models\Campaign::DAYS[$d]))->implode(', ') }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Horaires') }}</dt><dd class="font-medium">{{ $campaign->send_start }} – {{ $campaign->send_end }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Délai entre emails') }}</dt><dd class="font-medium">{{ __(':min à :max s', ['min' => $campaign->min_delay, 'max' => $campaign->max_delay]) }}</dd></div>
            </dl>
        </div>
        <div class="card card-body text-sm">
            <dl class="space-y-2">
                <div><dt class="text-slate-500">{{ __('Template') }}</dt><dd class="font-medium">{{ $campaign->template?->name ?? __('Supprimé') }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Compte SMTP') }}</dt><dd class="font-medium">{{ $campaign->smtpAccount ? $campaign->smtpAccount->label.' ('.$campaign->smtpAccount->from_email.')' : __('Supprimé') }}</dd></div>
                <div>
                    <dt class="text-slate-500">{{ __('Segment') }}</dt>
                    <dd class="font-medium">
                        @forelse (['sector' => 'Secteurs', 'wilaya' => 'Régions', 'tag' => 'Tags'] as $key => $label)
                            @if (! empty($segment[$key]))<div>{{ __(':label : :value', ['label' => __($label), 'value' => implode(', ', $segment[$key])]) }}</div>@endif
                        @empty
                        @endforelse
                        @if (empty(array_filter($segment))){{ __('Tous les contacts éligibles') }}@endif
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Journal des envois') }}</h2>
            <select wire:model.live="sendStatus" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                <option value="">{{ __('Tous les statuts') }}</option>
                @foreach (\App\Models\CampaignSend::STATUSES as $value => $label)<option value="{{ $value }}">{{ __($label) }} ({{ $s[$value] }})</option>@endforeach
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>{{ __('Contact') }}</th><th>{{ __('Statut') }}</th><th>{{ __('Planifié') }}</th><th>{{ __('Envoyé') }}</th><th>{{ __('Tentatives') }}</th><th>{{ __('Détail') }}</th></tr></thead>
                <tbody>
                    @forelse ($sends as $send)
                        <tr wire:key="send-{{ $send->id }}">
                            <td>
                                <div class="font-medium text-slate-900">{{ $send->contact?->company_name ?: '—' }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $send->contact?->email }}</div>
                            </td>
                            <td>
                                <x-badge :status="$send->status" :label="\App\Models\CampaignSend::STATUSES[$send->status]" />
                                @if ($send->replied_at)<span class="badge ml-1 bg-violet-50 text-violet-700">{{ __('Réponse') }}</span>@endif
                            </td>
                            <td class="whitespace-nowrap text-xs text-slate-500">{{ $send->scheduled_at?->format('d/m H:i:s') ?? '—' }}</td>
                            <td class="whitespace-nowrap text-xs text-slate-500">{{ $send->sent_at?->format('d/m H:i:s') ?? '—' }}</td>
                            <td class="tabular-nums">{{ $send->attempts }}</td>
                            <td class="max-w-md truncate text-xs text-slate-500" title="{{ __($send->error) }}">{{ __($send->error) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ $campaign->status === 'draft' ? __('Les envois seront créés au démarrage.') : __('Aucun envoi pour ce filtre.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-3">{{ $sends->links() }}</div>
    </div>
</div>
