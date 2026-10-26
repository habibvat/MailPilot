<div @if ($polling) wire:poll.3s @endif>
    <x-page-header :title="__('Imports')" :subtitle="__('Historique des fichiers importés et rapport de nettoyage.')">
        <x-slot:actions>
            <a href="{{ route('imports.create') }}" wire:navigate class="btn-primary">{{ __('Nouvel import') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Fichier') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Statut') }}</th>
                    <th class="text-right">{{ __('Lignes lues') }}</th>
                    <th class="text-right">{{ __('Importées') }}</th>
                    <th class="text-right">{{ __('Rejetées') }}</th>
                    <th class="text-right">{{ __('Doublons') }}</th>
                    <th class="text-right">{{ __('À vérifier') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($imports as $import)
                    <tr wire:key="import-{{ $import->id }}">
                        <td class="font-medium text-slate-900">{{ $import->filename }}</td>
                        <td class="whitespace-nowrap text-slate-500">{{ $import->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <x-badge :status="$import->status" :label="\App\Models\Import::STATUSES[$import->status] ?? $import->status" />
                            @if ($import->error_message)
                                <div class="mt-1 max-w-xs text-xs text-red-600">{{ __($import->error_message) }}</div>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ number_format($import->total_rows, 0, ',', ' ') }}</td>
                        <td class="text-right font-medium tabular-nums text-emerald-700">{{ number_format($import->imported, 0, ',', ' ') }}</td>
                        <td class="text-right tabular-nums text-red-600">{{ number_format($import->rejected, 0, ',', ' ') }}</td>
                        <td class="text-right tabular-nums text-amber-700">
                            {{ number_format($import->duplicates, 0, ',', ' ') }}
                            @if ($import->enriched > 0)
                                <div class="text-xs text-slate-500">{{ __('dont :count complétés', ['count' => number_format($import->enriched, 0, ',', ' ')]) }}</div>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ number_format($import->pending_count, 0, ',', ' ') }}</td>
                        <td class="text-right">
                            @if ($import->status === 'completed' && $import->pending_count > 0)
                                <button wire:click="verify({{ $import->id }})" class="btn-secondary btn-sm">{{ __('Vérifier') }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-10 text-center text-slate-500">{{ __('Aucun import pour le moment.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $imports->links() }}</div>
    <p class="help mt-3">{{ __('Rejetées : ligne sans email ou adresse illisible. Doublons : email déjà en base ou répété dans le fichier ; il n\'est pas recréé, mais ses champs vides (raison sociale, secteur, région…) sont complétés.') }}</p>
    <p class="help mt-1">{{ __('La vérification des emails démarre automatiquement à la fin de chaque import (le worker de file d\'attente doit tourner).') }}</p>
</div>
