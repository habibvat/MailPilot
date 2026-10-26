<div>
    <x-page-header :title="$template ? 'Modifier le template' : 'Nouveau template'" :subtitle="__('Le pied de page légal et le lien de désinscription sont ajoutés automatiquement à chaque email.')">
        <x-slot:actions>
            <a href="{{ route('templates.index') }}" wire:navigate class="btn-secondary">{{ __('Retour') }}</a>
            <button type="button" wire:click="save" class="btn-primary">{{ __('Enregistrer') }}</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Édition --}}
        <div class="card card-body space-y-4"
             x-data="{
                field: 'body_html',
                insert(variable) {
                    const el = this.$refs[this.field];
                    const start = el.selectionStart ?? el.value.length, end = el.selectionEnd ?? el.value.length;
                    el.value = el.value.slice(0, start) + variable + el.value.slice(end);
                    el.focus(); el.selectionStart = el.selectionEnd = start + variable.length;
                    el.dispatchEvent(new Event('input'));
                }
             }">
            <div>
                <label class="label">{{ __('Nom interne') }}</label>
                <input type="text" wire:model="name" class="input" placeholder="{{ __('Ex. Offre maintenance BTP') }}">
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">{{ __('Langue de l\'email') }}</label>
                <select wire:model.live="locale" class="input">
                    @foreach (\App\Support\Locale::SUPPORTED as $code => $language)<option value="{{ $code }}">{{ $language }}</option>@endforeach
                </select>
                <p class="help">{{ __('Langue du pied de page légal, du lien de désinscription et des valeurs par défaut des variables.') }}</p>
            </div>
            <div>
                <label class="label">{{ __('Objet') }}</label>
                <input type="text" wire:model.live.debounce.500ms="subject" x-ref="subject" x-on:focus="field = 'subject'" class="input">
                @error('subject') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="text-slate-500">{{ __('Insérer une variable :') }}</span>
                @foreach (\App\Models\Template::VARIABLES[$locale] ?? \App\Models\Template::VARIABLES['fr'] as $variable)
                    @php $placeholder = \App\Models\Template::placeholder($variable); @endphp
                    <button type="button" x-on:mousedown.prevent x-on:click="insert(@js($placeholder))" class="rounded border border-slate-300 bg-slate-50 px-2 py-0.5 font-mono text-slate-700 hover:bg-white">{{ $placeholder }}</button>
                @endforeach
            </div>

            <div>
                <label class="label">{{ __('Contenu HTML') }}</label>
                <textarea wire:model.live.debounce.700ms="body_html" x-ref="body_html" x-on:focus="field = 'body_html'" rows="16" class="input font-mono text-xs leading-relaxed"></textarea>
                @error('body_html') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <div class="mb-1 flex items-center justify-between">
                    <label class="label mb-0">{{ __('Version texte') }}</label>
                    <button type="button" wire:click="generateText" class="text-xs text-brand-600 hover:underline">{{ __('Générer depuis le HTML') }}</button>
                </div>
                <textarea wire:model.live.debounce.700ms="body_text" x-ref="body_text" x-on:focus="field = 'body_text'" rows="8" class="input font-mono text-xs leading-relaxed" placeholder="{{ __('Laissez vide pour la générer automatiquement à l\'envoi.') }}"></textarea>
            </div>
        </div>

        {{-- Aperçu --}}
        <div class="space-y-4">
            <div class="card card-body">
                <div class="mb-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">{{ __('Aperçu sur le contact') }}</label>
                        <input type="search" wire:model.live.debounce.400ms="previewSearch" class="input mb-2" placeholder="{{ __('Rechercher un contact…') }}">
                        <select wire:model.live="previewContactId" class="input">
                            @forelse ($contacts as $c)
                                <option value="{{ $c->id }}">{{ $c->company_name ?: '—' }} · {{ $c->email }}</option>
                            @empty
                                <option value="">{{ __('Contact d\'exemple (aucun contact importé)') }}</option>
                            @endforelse
                        </select>
                    </div>
                    <div>
                        <label class="label">{{ __('Compte d\'envoi') }}</label>
                        <select wire:model.live="testAccountId" class="input">
                            @forelse ($accounts as $a)
                                <option value="{{ $a->id }}">{{ $a->label }} · {{ $a->from_email }}</option>
                            @empty
                                <option value="">{{ __('Aucun compte SMTP') }}</option>
                            @endforelse
                        </select>
                        @error('testAccountId') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="rounded-md border border-slate-200">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm"><span class="text-slate-500">{{ __('Objet :') }}</span> <span class="font-medium text-slate-900">{{ $preview->subject }}</span></div>
                    <iframe srcdoc="{{ $preview->html }}" sandbox="" class="h-[480px] w-full bg-white"></iframe>
                </div>
                <details class="mt-3 text-sm">
                    <summary class="cursor-pointer text-slate-600">{{ __('Voir la version texte') }}</summary>
                    <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap rounded-md bg-slate-50 p-3 font-mono text-xs text-slate-700">{{ $preview->text }}</pre>
                </details>
            </div>

            <div class="card card-body">
                <label class="label">{{ __('Envoyer un test') }}</label>
                <div class="flex gap-2">
                    <input type="email" wire:model="testEmail" class="input" placeholder="{{ __('votre.adresse@exemple.dz') }}">
                    <button type="button" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest" class="btn-secondary whitespace-nowrap">
                        <span wire:loading.remove wire:target="sendTest">{{ __('Envoyer un test') }}</span>
                        <span wire:loading wire:target="sendTest">{{ __('Envoi…') }}</span>
                    </button>
                </div>
                @error('testEmail') <p class="error">{{ $message }}</p> @enderror
                <p class="help">{{ __('L\'objet est préfixé par [TEST]. Le contenu est rendu avec le contact sélectionné.') }}</p>
            </div>
        </div>
    </div>
</div>
