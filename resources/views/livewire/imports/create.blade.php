<div>
    <x-page-header :title="__('Nouvel import')" :subtitle="__('Fichier CSV ou Excel (XLSX), jusqu\'à 100 000 lignes et 50 Mo.')">
        <x-slot:actions>
            <a href="{{ route('imports.index') }}" wire:navigate class="btn-secondary">{{ __('Retour aux imports') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card card-body">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">{{ __('1. Fichier') }}</h2>
        @if (! $storedPath)
            <label class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center hover:border-brand-500">
                <svg class="mb-2 h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span class="text-sm font-medium text-slate-700">{{ __('Cliquez pour choisir un fichier') }}</span>
                <span class="mt-1 text-xs text-slate-500">{{ __('.csv, .xlsx ou .xls') }}</span>
                <input type="file" wire:model="file" class="hidden" accept=".csv,.txt,.xlsx,.xls">
            </label>
            <div wire:loading wire:target="file" class="mt-3 text-sm text-brand-700">{{ __('Envoi et lecture du fichier…') }}</div>
            @error('file') <p class="error">{{ $message }}</p> @enderror
        @else
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-md bg-slate-50 px-4 py-3">
                <div class="text-sm">
                    <span class="font-medium text-slate-900">{{ $originalName }}</span>
                    @if ($delimiter)
                        <span class="ml-2 text-slate-500">{{ __('séparateur « :delimiter » · encodage :encoding', ['delimiter' => $delimiter === "\t" ? __('tabulation') : $delimiter, 'encoding' => $encoding]) }}</span>
                    @endif
                </div>
                <button type="button" wire:click="resetFile" class="btn-secondary btn-sm">{{ __('Changer de fichier') }}</button>
            </div>
        @endif
    </div>

    @if ($storedPath)
        <div class="card card-body mt-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('2. Correspondance des colonnes') }}</h2>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model.live="hasHeader" class="rounded border-slate-300">
                    {{ __('La première ligne contient les intitulés de colonnes') }}
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($fields as $field => $label)
                    <div>
                        <label class="label">{{ __($label) }} @if ($field === 'email')<span class="text-red-600">*</span>@endif</label>
                        <select wire:model="mapping.{{ $field }}" class="input">
                            <option value="">{{ __('— Ne pas importer —') }}</option>
                            @foreach ($columns as $index => $column)
                                <option value="{{ $index }}">{{ $column }}</option>
                            @endforeach
                        </select>
                        @error('mapping.'.$field) <p class="error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
            <p class="help mt-3">{{ __('Une cellule email peut contenir plusieurs adresses (séparées par ; , / espace ou retour à la ligne) : chacune devient un contact. Les emails déjà présents sont comptés comme doublons.') }}</p>

            <h3 class="mb-2 mt-6 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aperçu des premières lignes') }}</h3>
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="table">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th class="whitespace-nowrap">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($hasHeader ? $sampleRows : array_merge([$headers], $sampleRows)) as $row)
                            <tr>
                                @foreach ($row as $value)
                                    <td class="max-w-56 truncate whitespace-nowrap">{{ $value }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" wire:click="start" wire:loading.attr="disabled" class="btn-primary">{{ __('Lancer l\'import') }}</button>
            </div>
        </div>
    @endif
</div>
