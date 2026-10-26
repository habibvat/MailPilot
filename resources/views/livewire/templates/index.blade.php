<div>
    <x-page-header :title="__('Templates')" :subtitle="__('Modèles d\'emails avec variables personnalisées.')">
        <x-slot:actions>
            <a href="{{ route('templates.create') }}" wire:navigate class="btn-primary">{{ __('Nouveau template') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <table class="table">
            <thead>
                <tr><th>{{ __('Nom') }}</th><th>{{ __('Objet') }}</th><th class="text-right">{{ __('Campagnes') }}</th><th>{{ __('Modifié le') }}</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($templates as $template)
                    <tr wire:key="tpl-{{ $template->id }}">
                        <td class="font-medium text-slate-900"><a href="{{ route('templates.edit', $template) }}" wire:navigate class="hover:underline">{{ $template->name }}</a> <span class="badge ml-1 bg-slate-100 uppercase text-slate-600" title="{{ __('Langue de l\'email') }}">{{ $template->locale }}</span></td>
                        <td class="text-slate-600">{{ $template->subject }}</td>
                        <td class="text-right tabular-nums">{{ $template->campaigns_count }}</td>
                        <td class="whitespace-nowrap text-slate-500">{{ $template->updated_at->format('d/m/Y H:i') }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('templates.edit', $template) }}" wire:navigate class="btn-secondary btn-sm">{{ __('Modifier') }}</a>
                            <button wire:click="duplicate({{ $template->id }})" class="btn-secondary btn-sm">{{ __('Dupliquer') }}</button>
                            <button wire:click="delete({{ $template->id }})" wire:confirm="{{ __('Supprimer ce template ?') }}" class="btn-secondary btn-sm text-red-600">{{ __('Supprimer') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-slate-500">{{ __('Aucun template.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
