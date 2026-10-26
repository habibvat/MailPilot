<div>
    <x-page-header :title="$campaign ? 'Modifier la campagne' : 'Nouvelle campagne'" :subtitle="__('Les contacts éligibles sont figés au démarrage de la campagne.')">
        <x-slot:actions>
            <a href="{{ $campaign ? route('campaigns.show', $campaign) : route('campaigns.index') }}" wire:navigate class="btn-secondary">{{ __('Annuler') }}</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Étapes --}}
    <ol class="mb-6 grid grid-cols-3 gap-3">
        @foreach ([1 => 'Segment', 2 => 'Template et compte', 3 => 'Planning et quotas'] as $number => $label)
            <li>
                <button type="button" wire:click="goTo({{ $number }})"
                        class="flex w-full items-center gap-3 rounded-lg border px-4 py-3 text-left text-sm {{ $step === $number ? 'border-brand-500 bg-brand-50' : 'border-slate-200 bg-white hover:bg-slate-50' }}">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $step >= $number ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-600' }}">{{ $number }}</span>
                    <span class="font-medium {{ $step === $number ? 'text-brand-700' : 'text-slate-700' }}">{{ __($label) }}</span>
                </button>
            </li>
        @endforeach
    </ol>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-body lg:col-span-2">
            @if ($step === 1)
                <div class="space-y-5">
                    <div>
                        <label class="label">{{ __('Nom de la campagne') }}</label>
                        <input type="text" wire:model="name" class="input" placeholder="{{ __('Ex. Offre BTP – Alger et Blida') }}">
                        @error('name') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-sm text-slate-500">{{ __('Laissez une liste vide pour ne pas filtrer sur ce critère. Plusieurs valeurs cochées = l\'une ou l\'autre.') }}</p>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="label">{{ __('Secteurs') }}</label>
                            <x-checkbox-list model="sectors" :options="$sectorOptions" />
                        </div>
                        <div>
                            <label class="label">{{ __('Régions') }}</label>
                            <x-checkbox-list model="wilayas" :options="$wilayaOptions" />
                        </div>
                        <div>
                            <label class="label">{{ __('Tags') }}</label>
                            <x-checkbox-list model="tags" :options="$tagOptions" :empty="__('Aucun tag. Ajoutez-en depuis la page Contacts.')" />
                        </div>
                    </div>
                </div>
            @elseif ($step === 2)
                <div class="space-y-5">
                    <div>
                        <label class="label">{{ __('Template') }}</label>
                        <select wire:model.live="template_id" class="input">
                            <option value="">{{ __('— Choisir —') }}</option>
                            @foreach ($templates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select>
                        @error('template_id') <p class="error">{{ $message }}</p> @enderror
                        @if ($template)
                            <p class="help">{{ __('Objet :') }} {{ $template->subject }} · <a href="{{ route('templates.edit', $template) }}" target="_blank" class="text-brand-600 underline">{{ __('ouvrir l\'aperçu') }}</a></p>
                        @endif
                    </div>
                    <div>
                        <label class="label">{{ __('Compte SMTP d\'envoi') }}</label>
                        <select wire:model.live="smtp_account_id" class="input">
                            <option value="">{{ __('— Choisir —') }}</option>
                            @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->label }} · {{ $a->from_email }}</option>@endforeach
                        </select>
                        @error('smtp_account_id') <p class="error">{{ $message }}</p> @enderror
                        @if ($accounts->isEmpty())
                            <p class="help"><a href="{{ route('smtp.create') }}" class="text-brand-600 underline">{{ __('Créer un compte SMTP') }}</a></p>
                        @elseif ($account && ! $account->hasImap())
                            <p class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ __('Ce compte n\'a pas de boîte IMAP : les rebonds, désinscriptions et réponses reçus par email ne seront pas détectés.') }}</p>
                        @endif
                    </div>
                </div>
            @else
                <div class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">{{ __('Quota horaire de la campagne') }}</label>
                            <input type="number" min="1" wire:model="hourly_limit" class="input">
                            @error('hourly_limit') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">{{ __('Quota journalier de la campagne') }}</label>
                            <input type="number" min="1" wire:model="daily_limit" class="input">
                            @error('daily_limit') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    @if ($account)
                        <p class="help -mt-3">{{ __('Le compte « :label » est limité à :hourly/h et :daily/jour, toutes campagnes confondues : la limite la plus basse s\'applique.', ['label' => $account->label, 'hourly' => $account->hourly_limit, 'daily' => $account->daily_limit]) }}</p>
                    @endif
                    <div>
                        <label class="label">{{ __('Jours d\'envoi') }}</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach (\App\Models\Campaign::DAYS as $value => $label)
                                <label class="flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-300 px-3 py-1.5 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                    <input type="checkbox" value="{{ $value }}" wire:model="send_days" class="rounded border-slate-300">{{ __($label) }}
                                </label>
                            @endforeach
                        </div>
                        @error('send_days') <p class="error">{{ $message }}</p> @enderror
                        <p class="help">{{ __('Les jours proposés par défaut se règlent dans les Paramètres.') }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">{{ __('Début d\'envoi') }}</label>
                            <input type="time" wire:model="send_start" class="input">
                            @error('send_start') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">{{ __('Fin d\'envoi') }}</label>
                            <input type="time" wire:model="send_end" class="input">
                            @error('send_end') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="help -mt-3">{{ __('Fuseau horaire : :timezone.', ['timezone' => config('mailpilot.timezone')]) }}</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">{{ __('Délai minimum entre deux emails (s)') }}</label>
                            <input type="number" min="0" wire:model="min_delay" class="input">
                            @error('min_delay') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">{{ __('Délai maximum entre deux emails (s)') }}</label>
                            <input type="number" min="0" wire:model="max_delay" class="input">
                            @error('max_delay') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-8 flex items-center justify-between border-t border-slate-200 pt-5">
                <button type="button" wire:click="previous" class="btn-secondary" @disabled($step === 1)>{{ __('Précédent') }}</button>
                <div class="flex gap-2">
                    @if ($step < 3)
                        <button type="button" wire:click="next" class="btn-primary">{{ __('Suivant') }}</button>
                    @else
                        <button type="button" wire:click="save(false)" class="btn-secondary">{{ __('Enregistrer le brouillon') }}</button>
                        <button type="button" wire:click="save(true)" wire:confirm="{{ __('Démarrer la campagne pour :count contact(s) éligible(s) ?', ['count' => $eligibleCount]) }}" class="btn-primary">{{ __('Enregistrer et démarrer') }}</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Compteur --}}
        <div class="space-y-4">
            <div class="card card-body">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Contacts éligibles') }}</div>
                <div class="mt-1 text-3xl font-semibold tabular-nums text-slate-900" wire:loading.class="opacity-50">{{ number_format($eligibleCount, 0, ',', ' ') }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ __('sur :count contact(s) dans le segment', ['count' => number_format($segmentCount, 0, ',', ' ')]) }}</div>
                <ul class="mt-4 space-y-1 text-xs text-slate-500">
                    <li>{{ __('✓ email vérifié valide') }}</li>
                    <li>{{ __('✓ contact actif (ni désinscrit, ni rebond, ni exclu)') }}</li>
                    <li>{{ __('✓ absent de la liste noire') }}</li>
                    <li>{{ __('✓ non contacté depuis :days jours', ['days' => config('mailpilot.recontact_days')]) }}</li>
                </ul>
            </div>
            @if ($eligibleCount > 0 && $daily_limit > 0)
                <div class="card card-body text-sm text-slate-600">
                    {!! __('Au rythme de :rate emails par jour d\'envoi, il faudra environ <strong>:days jour(s) d\'envoi</strong>.', [
                        'rate' => min($daily_limit, $account?->daily_limit ?? $daily_limit),
                        'days' => (int) ceil($eligibleCount / max(1, min($daily_limit, $account?->daily_limit ?? $daily_limit))),
                    ]) !!}
                </div>
            @endif
        </div>
    </div>
</div>
