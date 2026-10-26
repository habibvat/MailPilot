<div>
    <x-page-header :title="$account ? 'Modifier le compte SMTP' : 'Nouveau compte SMTP'">
        <x-slot:actions>
            <a href="{{ route('smtp.index') }}" wire:navigate class="btn-secondary">{{ __('Retour') }}</a>
            <button type="button" wire:click="save" class="btn-primary">{{ __('Enregistrer') }}</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card card-body space-y-4">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Envoi (SMTP)') }}</h2>
            <div>
                <label class="label">{{ __('Libellé') }}</label>
                <input type="text" wire:model="form.label" class="input" placeholder="{{ __('Ex. Commercial – domaine principal') }}">
                @error('form.label') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label class="label">{{ __('Serveur') }}</label>
                    <input type="text" wire:model="form.host" class="input" placeholder="{{ __('smtp.votre-domaine.dz') }}">
                    @error('form.host') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">{{ __('Port') }}</label>
                    <input type="number" wire:model="form.port" class="input">
                    @error('form.port') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="label">{{ __('Chiffrement') }}</label>
                <select wire:model.live="form.encryption" class="input">
                    @foreach (\App\Models\SmtpAccount::ENCRYPTIONS as $value => $label)<option value="{{ $value }}">{{ __($label) }}</option>@endforeach
                </select>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">{{ __('Identifiant') }}</label>
                    <input type="text" wire:model="form.username" class="input" autocomplete="off">
                </div>
                <div>
                    <label class="label">{{ __('Mot de passe') }}</label>
                    <input type="password" wire:model="form.password" class="input" autocomplete="new-password" placeholder="{{ $account ? __('Inchangé si vide') : '' }}">
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">{{ __('Nom d\'expéditeur') }}</label>
                    <input type="text" wire:model="form.from_name" class="input">
                    @error('form.from_name') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">{{ __('Email d\'expéditeur') }}</label>
                    <input type="email" wire:model="form.from_email" class="input">
                    @error('form.from_email') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">{{ __('Quota horaire du compte') }}</label>
                    <input type="number" wire:model="form.hourly_limit" class="input">
                    @error('form.hourly_limit') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">{{ __('Quota journalier du compte') }}</label>
                    <input type="number" wire:model="form.daily_limit" class="input">
                    @error('form.daily_limit') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="help">{{ __('Montée en charge conseillée : 30 envois/jour la première semaine, puis paliers progressifs jusqu\'à 200/jour.') }}</p>
            <div class="flex items-center gap-3">
                <button type="button" wire:click="testSmtp" wire:loading.attr="disabled" wire:target="testSmtp" class="btn-secondary">
                    <span wire:loading.remove wire:target="testSmtp">{{ __('Tester la connexion SMTP') }}</span>
                    <span wire:loading wire:target="testSmtp">{{ __('Test en cours…') }}</span>
                </button>
            </div>
            @if ($smtpResult)
                <div class="rounded-md px-3 py-2 text-sm {{ $smtpResult['ok'] ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800' }}">{{ $smtpResult['message'] }}</div>
            @endif
        </div>

        <div class="card card-body space-y-4">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Réception (IMAP)') }}</h2>
            <p class="text-sm text-slate-500">{{ __('La boîte est relevée toutes les 15 minutes pour détecter les rebonds, désinscriptions et réponses. Son adresse sert aussi de lien de désinscription (mailto).') }}</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label class="label">{{ __('Serveur IMAP') }}</label>
                    <input type="text" wire:model="form.imap_host" class="input" placeholder="{{ __('imap.votre-domaine.dz') }}">
                </div>
                <div>
                    <label class="label">{{ __('Port') }}</label>
                    <input type="number" wire:model="form.imap_port" class="input">
                    <p class="help">{{ __('993 (SSL) ou 143 (STARTTLS)') }}</p>
                    @error('form.imap_port') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">{{ __('Identifiant (adresse email)') }}</label>
                    <input type="text" wire:model="form.imap_username" class="input" autocomplete="off">
                </div>
                <div>
                    <label class="label">{{ __('Mot de passe') }}</label>
                    <input type="password" wire:model="form.imap_password" class="input" autocomplete="new-password" placeholder="{{ $account ? __('Inchangé si vide') : '' }}">
                </div>
            </div>
            <div>
                <button type="button" wire:click="testImap" wire:loading.attr="disabled" wire:target="testImap" class="btn-secondary">
                    <span wire:loading.remove wire:target="testImap">{{ __('Tester la connexion IMAP') }}</span>
                    <span wire:loading wire:target="testImap">{{ __('Test en cours…') }}</span>
                </button>
            </div>
            @if ($imapResult)
                <div class="rounded-md px-3 py-2 text-sm {{ $imapResult['ok'] ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800' }}">{{ $imapResult['message'] }}</div>
            @endif
        </div>
    </div>
</div>
