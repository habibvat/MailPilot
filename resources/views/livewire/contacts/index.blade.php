<div>
    <x-page-header :title="__('Contacts')" subtitle="{{ number_format($total, 0, ',', ' ') }} contact(s) correspondant aux filtres.">
        <x-slot:actions>
            <button type="button" wire:click="verifyPending" wire:loading.attr="disabled" class="btn-secondary">{{ __('Vérifier les contacts en attente') }}</button>
            <a href="{{ $exportUrl }}" class="btn-secondary">{{ __('Exporter en CSV') }}</a>
            <a href="{{ route('imports.create') }}" wire:navigate class="btn-primary">{{ __('Importer') }}</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Filtres --}}
    <div class="card card-body mb-4">
        <div class="grid gap-3 md:grid-cols-3 lg:grid-cols-7">
            <div class="lg:col-span-2">
                <input type="search" wire:model.live.debounce.400ms="search" class="input" placeholder="{{ __('Rechercher (entreprise, email, téléphone)…') }}">
            </div>
            <select wire:model.live="sector" class="input">
                <option value="">{{ __('Tous les secteurs') }}</option>
                @foreach ($sectors as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach
            </select>
            <select wire:model.live="wilaya" class="input">
                <option value="">{{ __('Toutes les régions') }}</option>
                @foreach ($wilayas as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach
            </select>
            <select wire:model.live="verify_status" class="input">
                <option value="">{{ __('Toute vérification') }}</option>
                @foreach (\App\Models\Contact::VERIFY_STATUSES as $value => $label)<option value="{{ $value }}">{{ __($label) }}</option>@endforeach
            </select>
            <select wire:model.live="status" class="input">
                <option value="">{{ __('Tous les statuts') }}</option>
                @foreach (\App\Models\Contact::STATUSES as $value => $label)<option value="{{ $value }}">{{ __($label) }}</option>@endforeach
            </select>
            <select wire:model.live="tag" class="input">
                <option value="">{{ __('Tous les tags') }}</option>
                @foreach ($tags as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach
            </select>
        </div>
        <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
            <button type="button" wire:click="resetFilters" class="hover:text-slate-800 hover:underline">{{ __('Réinitialiser les filtres') }}</button>
            <label class="flex items-center gap-2">{{ __('Par page') }}
                <select wire:model.live="perPage" class="rounded border border-slate-300 px-2 py-1 text-xs">
                    <option>25</option><option>50</option><option>100</option>
                </select>
            </label>
        </div>
    </div>

    {{-- Actions groupées --}}
    @if ($selectionCount > 0)
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-brand-100 bg-brand-50 px-4 py-3 text-sm">
            <span class="font-medium text-brand-700">{{ __(':count sélectionné(s)', ['count' => number_format($selectionCount, 0, ',', ' ')]) }}</span>
            @if (! $selectAll && $selectionCount < $total)
                <button type="button" wire:click="selectAllMatching" class="text-brand-700 underline">{{ __('Sélectionner les :count contacts filtrés', ['count' => number_format($total, 0, ',', ' ')]) }}</button>
            @endif
            <button type="button" wire:click="clearSelection" class="text-slate-500 underline">{{ __('Annuler') }}</button>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <button type="button" wire:click="bulkVerify" wire:loading.attr="disabled" class="btn-secondary btn-sm">{{ __('Vérifier') }}</button>
                <div class="flex items-center gap-1">
                    <input type="text" wire:model="bulkTag" class="input w-36 py-1.5 text-xs" placeholder="{{ __('Tag…') }}">
                    <button type="button" wire:click="bulkTag" wire:loading.attr="disabled" class="btn-secondary btn-sm">{{ __('Taguer') }}</button>
                    <button type="button" wire:click="bulkUntag" wire:loading.attr="disabled" class="btn-secondary btn-sm">{{ __('Retirer') }}</button>
                </div>
                <button type="button" wire:click="bulkExclude" wire:confirm="{{ __('Exclure ces contacts des campagnes ?') }}" class="btn-danger btn-sm">{{ __('Exclure') }}</button>
                <button type="button" wire:click="bulkReactivate" class="btn-secondary btn-sm">{{ __('Réactiver') }}</button>
            </div>
            @error('bulkTag') <p class="error w-full text-right">{{ $message }}</p> @enderror
        </div>
    @endif

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th class="w-8">
                        <input type="checkbox" class="rounded border-slate-300"
                               wire:click="togglePage({{ json_encode($pageIds) }})"
                               @checked($selectAll || ($pageIds && ! array_diff($pageIds, $selected)))>
                    </th>
                    <th>{{ __('Entreprise') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Téléphone') }}</th>
                    <th>{{ __('Secteur') }}</th>
                    <th>{{ __('Région') }}</th>
                    <th>{{ __('Vérification') }}</th>
                    <th>{{ __('Statut') }}</th>
                    <th>{{ __('Dernier envoi') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contacts as $contact)
                    <tr wire:key="contact-{{ $contact->id }}">
                        <td><input type="checkbox" value="{{ $contact->id }}" wire:model.live="selected" class="rounded border-slate-300" @if ($selectAll) checked disabled @endif></td>
                        <td class="min-w-48">
                            <div class="font-medium text-slate-900">{{ $contact->company_name ?: '—' }}</div>
                            @if ($contact->tags)
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($contact->tags as $t)<span class="badge bg-brand-50 text-brand-700">{{ $t }}</span>@endforeach
                                </div>
                            @endif
                        </td>
                        <td class="font-mono text-xs">{{ $contact->email }}</td>
                        <td class="text-xs">
                            @foreach (preg_split('/\s+\/\s+|\s*[|;]\s*/', (string) $contact->phone, -1, PREG_SPLIT_NO_EMPTY) as $number)
                                <div class="whitespace-nowrap">{{ $number }}</div>
                            @endforeach
                        </td>
                        <td class="max-w-64"><div class="line-clamp-2" title="{{ $contact->sector }}">{{ $contact->sector }}</div></td>
                        <td>{{ $contact->wilaya }}</td>
                        <td>
                            <x-badge :status="$contact->verify_status" :label="\App\Models\Contact::VERIFY_STATUSES[$contact->verify_status] ?? $contact->verify_status" :title="__((string) $contact->verify_reason)" />
                        </td>
                        <td>
                            <x-badge :status="$contact->status" :label="\App\Models\Contact::STATUSES[$contact->status] ?? $contact->status" />
                            @if ($contact->replied_at)<span class="badge ml-1 bg-violet-50 text-violet-700">{{ __('A répondu') }}</span>@endif
                        </td>
                        <td class="whitespace-nowrap text-xs text-slate-500">{{ $contact->last_contacted_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-right">
                            <button type="button" wire:click="edit({{ $contact->id }})" title="{{ __('Modifier') }}" aria-label="{{ __('Modifier le contact') }}"
                                    class="rounded-md p-1.5 text-slate-400 hover:bg-brand-50 hover:text-brand-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="py-10 text-center text-slate-500">{{ __('Aucun contact.') }} <a href="{{ route('imports.create') }}" wire:navigate class="text-brand-600 underline">{{ __('Importer un fichier') }}</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $contacts->links() }}</div>

    {{-- Fenêtre d'édition --}}
    @if ($editingId)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" wire:keydown.escape.window="cancelEdit">
            <form wire:submit="save" class="card w-full max-w-2xl">
                <div class="border-b border-slate-200 px-5 py-4 text-sm font-semibold text-slate-900">{{ __('Modifier le contact') }}</div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    @foreach (['company_name' => 'Raison sociale', 'email' => 'Email', 'phone' => 'Téléphone', 'sector' => 'Secteur', 'wilaya' => 'Région', 'website' => 'Site web'] as $field => $label)
                        <div>
                            <label class="label">{{ __($label) }}</label>
                            <input type="text" wire:model="form.{{ $field }}" class="input">
                            @error('form.'.$field) <p class="error">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                    <div>
                        <label class="label">{{ __('Tags') }}</label>
                        <input type="text" wire:model="form.tags" class="input" placeholder="{{ __('séparés par des virgules') }}">
                    </div>
                    <div>
                        <label class="label">{{ __('Statut') }}</label>
                        <select wire:model="form.status" class="input">
                            @foreach (\App\Models\Contact::STATUSES as $value => $label)<option value="{{ $value }}">{{ __($label) }}</option>@endforeach
                        </select>
                    </div>
                    <p class="help sm:col-span-2">{{ __('Si l\'email change, le contact repasse en « En attente » de vérification.') }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" wire:click="cancelEdit" class="btn-secondary">{{ __('Annuler') }}</button>
                    <button type="submit" class="btn-primary">{{ __('Enregistrer') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
