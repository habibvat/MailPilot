<?php

namespace App\Livewire\SmtpAccounts;

use App\Models\Setting;
use App\Models\SmtpAccount;
use App\Services\Mail\ImapClientFactory;
use App\Services\Mail\Mailer;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?SmtpAccount $account = null;

    public array $form = [
        'label' => '',
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
        'from_name' => '',
        'from_email' => '',
        'hourly_limit' => 10,
        'daily_limit' => 30,
        'imap_host' => '',
        'imap_port' => 993,
        'imap_username' => '',
        'imap_password' => '',
    ];

    public ?array $smtpResult = null;

    public ?array $imapResult = null;

    public function mount(?SmtpAccount $account = null): void
    {
        if ($account?->exists) {
            $this->account = $account;
            foreach ($this->form as $key => $default) {
                if (! in_array($key, ['password', 'imap_password'], true)) {
                    $this->form[$key] = $account->{$key} ?? $default;
                }
            }
        } else {
            $this->form['hourly_limit'] = (int) Setting::get('default_hourly_limit');
            $this->form['daily_limit'] = (int) Setting::get('default_daily_limit');
        }
    }

    public function updatedFormEncryption(string $value): void
    {
        $this->form['port'] = match ($value) {
            'ssl' => 465,
            'tls' => 587,
            default => 25,
        };
    }

    public function save()
    {
        $data = $this->validate($this->rules(), [], $this->attributes())['form'];

        foreach (['password', 'imap_password'] as $secret) {
            if ($data[$secret] === '' || $data[$secret] === null) {
                unset($data[$secret]); // champ vide = mot de passe inchangé
            }
        }
        $data['imap_port'] = $data['imap_port'] ?: null;

        if ($this->account) {
            $this->account->update($data);
            $this->dispatch('notify', message: __('Compte enregistré.'));

            return null;
        }

        $account = SmtpAccount::create($data);
        session()->flash('notify', __('Compte créé.'));

        return $this->redirectRoute('smtp.edit', $account, navigate: true);
    }

    public function testSmtp(Mailer $mailer): void
    {
        $this->validate(array_intersect_key($this->rules(), array_flip(['form.host', 'form.port', 'form.encryption', 'form.from_email'])), [], $this->attributes());

        try {
            $mailer->testConnection($this->draftAccount());
            $this->smtpResult = ['ok' => true, 'message' => __('Connexion et authentification SMTP réussies.')];
        } catch (\Throwable $e) {
            $this->smtpResult = ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function testImap(ImapClientFactory $imap): void
    {
        if (blank($this->form['imap_host']) || blank($this->form['imap_username'])) {
            $this->imapResult = ['ok' => false, 'message' => __('Renseignez le serveur et l\'identifiant IMAP.')];

            return;
        }

        try {
            $count = $imap->test($this->draftAccount());
            $this->imapResult = ['ok' => true, 'message' => __('Connexion IMAP réussie : :count message(s) dans la boîte de réception.', ['count' => $count])];
        } catch (\Throwable $e) {
            $this->imapResult = ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function render()
    {
        return view('livewire.smtp.form')->title($this->account ? __('Modifier le compte SMTP') : __('Nouveau compte SMTP'));
    }

    /** Compte construit avec les valeurs saisies (mots de passe enregistrés si les champs sont vides). */
    private function draftAccount(): SmtpAccount
    {
        $account = new SmtpAccount(collect($this->form)->except(['password', 'imap_password'])->all());
        $account->password = $this->form['password'] !== '' ? $this->form['password'] : $this->account?->password;
        $account->imap_password = $this->form['imap_password'] !== '' ? $this->form['imap_password'] : $this->account?->imap_password;
        $account->port = (int) $this->form['port'];
        $account->imap_port = (int) $this->form['imap_port'] ?: null;

        return $account;
    }

    private function rules(): array
    {
        return [
            'form.label' => ['required', 'string', 'max:255'],
            'form.host' => ['required', 'string', 'max:255'],
            'form.port' => ['required', 'integer', 'between:1,65535'],
            'form.encryption' => ['required', Rule::in(array_keys(SmtpAccount::ENCRYPTIONS))],
            'form.username' => ['nullable', 'string', 'max:255'],
            'form.password' => ['nullable', 'string', 'max:1000'],
            'form.from_name' => ['required', 'string', 'max:255'],
            'form.from_email' => ['required', 'email', 'max:255'],
            'form.hourly_limit' => ['required', 'integer', 'min:1', 'max:10000'],
            'form.daily_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'form.imap_host' => ['nullable', 'string', 'max:255'],
            'form.imap_port' => ['nullable', 'integer', 'between:1,65535'],
            'form.imap_username' => ['nullable', 'string', 'max:255'],
            'form.imap_password' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function attributes(): array
    {
        return [
            'form.label' => __('libellé'), 'form.host' => __('serveur'), 'form.port' => 'port', 'form.from_name' => __('nom d\'expéditeur'),
            'form.from_email' => __('email d\'expéditeur'), 'form.hourly_limit' => __('quota horaire'), 'form.daily_limit' => __('quota journalier'),
        ];
    }
}
