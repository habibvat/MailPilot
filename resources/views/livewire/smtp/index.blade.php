<div>
    <x-page-header :title="__('Comptes SMTP')" :subtitle="__('Comptes d\'envoi et boîtes IMAP associées (rebonds, désinscriptions, réponses).')">
        <x-slot:actions>
            <a href="{{ route('smtp.create') }}" wire:navigate class="btn-primary">{{ __('Nouveau compte') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <table class="table">
            <thead>
                <tr><th>{{ __('Libellé') }}</th><th>{{ __('Serveur') }}</th><th>{{ __('Expéditeur') }}</th><th class="text-right">{{ __('Aujourd\'hui / quota') }}</th><th class="text-right">{{ __('Quota horaire') }}</th><th>{{ __('IMAP') }}</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr wire:key="smtp-{{ $account->id }}">
                        <td class="font-medium text-slate-900">{{ $account->label }}</td>
                        <td class="font-mono text-xs">{{ $account->host }}:{{ $account->port }} <span class="text-slate-400">({{ __(\App\Models\SmtpAccount::ENCRYPTIONS[$account->encryption] ?? $account->encryption) }})</span></td>
                        <td>{{ $account->from_name }} <span class="text-slate-500">&lt;{{ $account->from_email }}&gt;</span></td>
                        <td class="text-right tabular-nums">{{ $account->sent_today }} / {{ $account->daily_limit }}</td>
                        <td class="text-right tabular-nums">{{ $account->hourly_limit }}/h</td>
                        <td>
                            @if ($account->hasImap())
                                <x-badge status="active" :label="__('Configuré')" />
                                @if ($account->imap_checked_at)<div class="mt-0.5 text-[11px] text-slate-400">{{ __('relevé :time', ['time' => $account->imap_checked_at->diffForHumans()]) }}</div>@endif
                            @else
                                <x-badge status="pending" :label="__('Non configuré')" />
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('smtp.edit', $account) }}" wire:navigate class="btn-secondary btn-sm">{{ __('Modifier') }}</a>
                            <button wire:click="delete({{ $account->id }})" wire:confirm="{{ __('Supprimer ce compte ?') }}" class="btn-secondary btn-sm text-red-600">{{ __('Supprimer') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-slate-500">{{ __('Aucun compte SMTP. Ajoutez-en un pour pouvoir envoyer.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
