<div wire:poll.30s>
    <x-page-header :title="__('Tableau de bord')" :subtitle="__('Vue d\'ensemble de la base et des envois du jour.')" />

    {{-- Indicateurs principaux --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card card-body">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Contacts') }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ number_format($totalContacts, 0, ',', ' ') }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ __(':count valides', ['count' => number_format($verify['valid'] ?? 0, 0, ',', ' ')]) }}</div>
        </div>
        <div class="card card-body">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Envoyés aujourd\'hui') }}</div>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-semibold tabular-nums text-slate-900">{{ $sentToday }}</span>
                <span class="text-sm text-slate-500">/ {{ $dailyQuota }} {{ __('quota') }}</span>
            </div>
            <div class="mt-2"><x-progress :value="$dailyQuota > 0 ? $sentToday / $dailyQuota * 100 : 0" /></div>
        </div>
        <div class="card card-body">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Planifiés (file d\'attente)') }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $plannedToday }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ __('envois prévus dans les prochaines minutes') }}</div>
        </div>
        <div class="card card-body">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Campagnes en cours') }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $runningCount }}</div>
            <div class="mt-1 text-sm"><a href="{{ route('campaigns.create') }}" wire:navigate class="text-brand-600 hover:underline">{{ __('Nouvelle campagne') }}</a></div>
        </div>
    </div>

    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        {{-- Vérification --}}
        <div class="card card-body">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">{{ __('Vérification des emails') }}</h2>
            @foreach (\App\Models\Contact::VERIFY_STATUSES as $key => $label)
                @php $count = $verify[$key] ?? 0; @endphp
                <a href="{{ route('contacts.index', ['verify_status' => $key]) }}" wire:navigate class="mb-3 block">
                    <div class="mb-1 flex justify-between text-sm"><x-badge :status="$key" :label="$label" /><span class="tabular-nums text-slate-700">{{ number_format($count, 0, ',', ' ') }}</span></div>
                    <x-progress :value="$totalContacts ? $count / $totalContacts * 100 : 0" />
                </a>
            @endforeach
        </div>

        {{-- Statuts --}}
        <div class="card card-body">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">{{ __('Statut des contacts') }}</h2>
            @foreach (\App\Models\Contact::STATUSES as $key => $label)
                @php $count = $status[$key] ?? 0; @endphp
                <a href="{{ route('contacts.index', ['status' => $key]) }}" wire:navigate class="mb-3 block">
                    <div class="mb-1 flex justify-between text-sm"><x-badge :status="$key" :label="$label" /><span class="tabular-nums text-slate-700">{{ number_format($count, 0, ',', ' ') }}</span></div>
                    <x-progress :value="$totalContacts ? $count / $totalContacts * 100 : 0" />
                </a>
            @endforeach
        </div>

        {{-- Comptes --}}
        <div class="card card-body">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">{{ __('Quotas par compte (aujourd\'hui)') }}</h2>
            @forelse ($accounts as $account)
                <div class="mb-3">
                    <div class="mb-1 flex justify-between text-sm"><span class="truncate text-slate-700">{{ $account->label }}</span><span class="tabular-nums text-slate-700">{{ $account->used_today }} / {{ $account->daily_limit }}</span></div>
                    <x-progress :value="$account->daily_limit ? $account->used_today / $account->daily_limit * 100 : 0" />
                    <div class="mt-0.5 text-[11px] text-slate-400">
                        {{ $account->hourly_limit }}/h ·
                        @if ($account->hasImap())
                            IMAP {{ $account->imap_checked_at ? __('relevé :time', ['time' => $account->imap_checked_at->diffForHumans()]) : __('pas encore relevé') }}
                        @else
                            {{ __('IMAP non configuré') }}
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ __('Aucun compte.') }} <a href="{{ route('smtp.create') }}" wire:navigate class="text-brand-600 underline">{{ __('Ajouter un compte SMTP') }}</a></p>
            @endforelse
        </div>
    </div>

    {{-- Campagnes --}}
    <div class="card overflow-x-auto">
        <div class="border-b border-slate-200 px-5 py-3"><h2 class="text-sm font-semibold text-slate-900">{{ __('Campagnes') }}</h2></div>
        <table class="table">
            <thead><tr><th>{{ __('Campagne') }}</th><th>{{ __('Statut') }}</th><th class="w-56">{{ __('Progression') }}</th><th class="text-right">{{ __('Envoyés') }}</th><th class="text-right">{{ __('Taux d\'échec') }}</th><th class="text-right">{{ __('Taux de rebond') }}</th><th class="text-right">{{ __('Réponses') }}</th></tr></thead>
            <tbody>
                @forelse ($campaigns as $campaign)
                    @php $s = $stats[$campaign->id]; @endphp
                    <tr wire:key="dash-{{ $campaign->id }}">
                        <td><a href="{{ route('campaigns.show', $campaign) }}" wire:navigate class="font-medium text-slate-900 hover:underline">{{ $campaign->name }}</a><div class="text-xs text-slate-500">{{ $campaign->smtpAccount?->label }}</div></td>
                        <td><x-badge :status="$campaign->status" :label="\App\Models\Campaign::STATUSES[$campaign->status]" /></td>
                        <td><x-progress :value="$s['progress']" /><div class="mt-1 text-xs text-slate-500">{{ __(':progress % de :total', ['progress' => $s['progress'], 'total' => number_format($s['total'], 0, ',', ' ')]) }}</div></td>
                        <td class="text-right tabular-nums">{{ number_format($s['sent'], 0, ',', ' ') }}</td>
                        <td class="text-right tabular-nums {{ $s['failure_rate'] > 10 ? 'font-semibold text-red-600' : '' }}">{{ $s['failure_rate'] }} %</td>
                        <td class="text-right tabular-nums {{ $s['bounce_rate'] > 5 ? 'font-semibold text-red-600' : '' }}">{{ $s['bounce_rate'] }} %</td>
                        <td class="text-right tabular-nums">{{ $s['replied'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">{{ __('Aucune campagne démarrée.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
