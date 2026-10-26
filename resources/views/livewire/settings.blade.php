<div>
    <x-page-header :title="__('Paramètres')" :subtitle="__('Identité de l\'expéditeur, valeurs par défaut et liste noire.')" />

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <form wire:submit="saveLegal" class="card card-body space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Identité légale de l\'expéditeur') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Affichée dans le pied de page obligatoire de chaque email, avec le lien de désinscription.') }}</p>
            </div>
            <div>
                <label class="label">{{ __('Raison sociale') }}</label>
                <input type="text" wire:model="legal.legal_company" class="input" placeholder="{{ __('Ex. SARL Mon Entreprise') }}">
                @error('legal.legal_company') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">{{ __('Adresse') }}</label>
                <input type="text" wire:model="legal.legal_address" class="input" placeholder="{{ __('Adresse, ville, pays') }}">
                @error('legal.legal_address') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">{{ __('Téléphone') }}</label>
                <input type="text" wire:model="legal.legal_phone" class="input" placeholder="{{ __('Ex. 023 00 00 00') }}">
                @error('legal.legal_phone') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end"><button type="submit" class="btn-primary">{{ __('Enregistrer') }}</button></div>
        </form>

        <form wire:submit="saveDefaults" class="card card-body space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Quotas par défaut') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Valeurs proposées à la création d\'un compte SMTP ou d\'une campagne.') }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">{{ __('Emails par heure') }}</label>
                    <input type="number" min="1" wire:model="defaults.default_hourly_limit" class="input">
                    @error('defaults.default_hourly_limit') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">{{ __('Emails par jour') }}</label>
                    <input type="number" min="1" wire:model="defaults.default_daily_limit" class="input">
                    @error('defaults.default_daily_limit') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">{{ __('Délai minimum (s)') }}</label>
                    <input type="number" min="0" wire:model="defaults.default_min_delay" class="input">
                    @error('defaults.default_min_delay') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">{{ __('Délai maximum (s)') }}</label>
                    <input type="number" min="0" wire:model="defaults.default_max_delay" class="input">
                    @error('defaults.default_max_delay') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="help">{{ __('Montée en charge conseillée : 30/jour la première semaine, puis 50, 80, 120 et jusqu\'à 200/jour par compte si les taux de rebond restent bas.') }}</p>
            <div class="flex justify-end"><button type="submit" class="btn-primary">{{ __('Enregistrer') }}</button></div>
        </form>
    </div>

    <form wire:submit="saveRegional" class="card card-body mb-6 space-y-5">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Préférences régionales') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Fuseau horaire des envois et des dates : :timezone. Il est détecté au premier lancement ; pour le changer, modifiez APP_TIMEZONE dans le fichier .env puis relancez MailPilot.', ['timezone' => config('mailpilot.timezone')]) }}</p>
        </div>
        <div>
            <label class="label">{{ __('Jours d\'envoi proposés pour une nouvelle campagne') }}</label>
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Models\Campaign::DAYS as $value => $label)
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-300 px-3 py-1.5 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                        <input type="checkbox" value="{{ $value }}" wire:model="sendDays" class="rounded border-slate-300">{{ __($label) }}
                    </label>
                @endforeach
            </div>
            @error('sendDays') <p class="error">{{ $message }}</p> @enderror
            <p class="help">{{ __('Par exemple du lundi au vendredi, ou du dimanche au jeudi en Algérie et dans plusieurs pays du Moyen-Orient.') }}</p>
        </div>
        <div class="space-y-3">
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" wire:model="algerianRegions" class="mt-0.5 rounded border-slate-300">
                <span>
                    <span class="font-medium text-slate-900">{{ __('Harmoniser les wilayas algériennes à l\'import') }}</span>
                    <span class="block text-slate-500">{{ __('Reconnaît les 58 wilayas, leurs codes (« 16 » → Alger) et leurs variantes d\'écriture. Laissez décoché si vos contacts sont hors d\'Algérie : la région est alors importée telle quelle.') }}</span>
                </span>
            </label>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" wire:model="restorePhoneZero" class="mt-0.5 rounded border-slate-300">
                <span>
                    <span class="font-medium text-slate-900">{{ __('Rétablir le 0 initial des numéros de téléphone à 9 chiffres') }}</span>
                    <span class="block text-slate-500">{{ __('Excel supprime le 0 des numéros nationaux (550 12 34 56 au lieu de 0550 12 34 56). Utile notamment pour l\'Algérie, le Maroc, la Tunisie ou la France ; à laisser décoché ailleurs.') }}</span>
                </span>
            </label>
        </div>
        <div class="flex justify-end"><button type="submit" class="btn-primary">{{ __('Enregistrer') }}</button></div>
    </form>

    <div class="card">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Liste noire') }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('Ces adresses ne reçoivent jamais d\'email.') }}
                @foreach (\App\Models\Suppression::REASONS as $key => $label) {{ __(':label : :value', ['label' => __($label), 'value' => $counts[$key] ?? 0]) }}@if (! $loop->last) ·@endif @endforeach
            </p>
        </div>
        <div class="grid gap-6 border-b border-slate-200 p-5 lg:grid-cols-2">
            <form wire:submit="addSuppression" class="space-y-3">
                <label class="label">{{ __('Ajouter une adresse') }}</label>
                <div class="flex gap-2">
                    <input type="email" wire:model="newEmail" class="input" placeholder="{{ __('adresse@exemple.dz') }}">
                    <select wire:model="newReason" class="input w-44">
                        @foreach (\App\Models\Suppression::REASONS as $key => $label)<option value="{{ $key }}">{{ __($label) }}</option>@endforeach
                    </select>
                    <button type="submit" class="btn-primary">{{ __('Ajouter') }}</button>
                </div>
                @error('newEmail') <p class="error">{{ $message }}</p> @enderror
            </form>
            <form wire:submit="importSuppressions" class="space-y-3">
                <label class="label">{{ __('Importer en masse') }}</label>
                <textarea wire:model="bulkEmails" rows="3" class="input font-mono text-xs" placeholder="{{ __('Collez des adresses (une par ligne, ou séparées par des virgules)…') }}"></textarea>
                <div class="flex items-center gap-3">
                    <input type="file" wire:model="suppressionFile" accept=".txt,.csv" class="text-sm text-slate-600">
                    <button type="submit" wire:loading.attr="disabled" class="btn-secondary ml-auto">{{ __('Importer') }}</button>
                </div>
                @error('suppressionFile') <p class="error">{{ $message }}</p> @enderror
            </form>
        </div>
        <div class="flex flex-wrap gap-3 px-5 py-3">
            <input type="search" wire:model.live.debounce.400ms="search" class="input max-w-xs" placeholder="{{ __('Rechercher une adresse…') }}">
            <select wire:model.live="reasonFilter" class="input w-48">
                <option value="">{{ __('Toutes les raisons') }}</option>
                @foreach (\App\Models\Suppression::REASONS as $key => $label)<option value="{{ $key }}">{{ __($label) }}</option>@endforeach
            </select>
        </div>
        <table class="table">
            <thead><tr><th>{{ __('Email') }}</th><th>{{ __('Raison') }}</th><th>{{ __('Ajoutée le') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($suppressions as $suppression)
                    <tr wire:key="sup-{{ $suppression->id }}">
                        <td class="font-mono text-xs">{{ $suppression->email }}</td>
                        <td>{{ __(\App\Models\Suppression::REASONS[$suppression->reason] ?? $suppression->reason) }}</td>
                        <td class="text-slate-500">{{ $suppression->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-right"><button type="button" wire:click="deleteSuppression({{ $suppression->id }})" wire:confirm="{{ __('Retirer cette adresse de la liste noire ?') }}" class="text-sm text-red-600 hover:underline">{{ __('Retirer') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500">{{ __('Liste noire vide.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-slate-200 px-5 py-3">{{ $suppressions->links() }}</div>
    </div>
</div>
