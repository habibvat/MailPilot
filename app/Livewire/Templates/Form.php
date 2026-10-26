<?php

namespace App\Livewire\Templates;

use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Models\Template;
use App\Services\Mail\Mailer;
use App\Services\Mail\RenderedEmail;
use App\Services\Mail\TemplateRenderer;
use App\Support\Locale;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?Template $template = null;

    public string $name = '';

    public string $locale = 'fr';

    public string $subject = '';

    public string $body_html = '';

    public string $body_text = '';

    public ?int $previewContactId = null;

    public string $previewSearch = '';

    public string $testEmail = '';

    public ?int $testAccountId = null;

    public function mount(?Template $template = null): void
    {
        if ($template?->exists) {
            $this->template = $template;
            $this->fill($template->only(['name', 'locale', 'subject', 'body_html']));
            $this->body_text = (string) $template->body_text;
        } else {
            $this->locale = app()->getLocale();
        }

        $this->previewContactId = Contact::where('verify_status', 'valid')->value('id') ?? Contact::value('id');
        $this->testAccountId = SmtpAccount::value('id');
    }

    public function save()
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', Rule::in(array_keys(Locale::SUPPORTED))],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
        ], [], ['name' => __('nom'), 'subject' => __('objet'), 'body_html' => __('contenu HTML')]);

        if ($this->template) {
            $this->template->update($data);
            $this->dispatch('notify', message: __('Template enregistré.'));

            return null;
        }

        $template = Template::create($data);
        session()->flash('notify', __('Template créé.'));

        return $this->redirectRoute('templates.edit', $template, navigate: true);
    }

    public function generateText(TemplateRenderer $renderer): void
    {
        $this->body_text = $renderer->htmlToText($this->body_html);
    }

    public function sendTest(Mailer $mailer): void
    {
        $this->validate([
            'testEmail' => ['required', 'email'],
            'testAccountId' => ['required', 'exists:smtp_accounts,id'],
            'subject' => ['required'],
            'body_html' => ['required'],
        ], [
            'testAccountId.required' => __('Créez d\'abord un compte SMTP.'),
        ], ['testEmail' => __('adresse de test')]);

        $account = SmtpAccount::findOrFail($this->testAccountId);
        $preview = $this->preview($account);

        try {
            $mailer->send($account, $this->testEmail, new RenderedEmail('[TEST] '.$preview->subject, $preview->html, $preview->text));
            $this->dispatch('notify', message: __('Email de test envoyé à :email.', ['email' => $this->testEmail]));
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: __('Échec de l\'envoi : :error', ['error' => $e->getMessage()]));
        }
    }

    public function render()
    {
        $contacts = Contact::query()
            ->when($this->previewSearch !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('email', 'like', '%'.$this->previewSearch.'%')
                ->orWhere('company_name', 'like', '%'.$this->previewSearch.'%')))
            ->orderBy('company_name')->limit(30)->get(['id', 'company_name', 'email']);

        if ($this->previewContactId && ! $contacts->contains('id', $this->previewContactId)) {
            $contacts->prepend(Contact::find($this->previewContactId, ['id', 'company_name', 'email']));
        }

        return view('livewire.templates.form', [
            'preview' => $this->preview(SmtpAccount::find($this->testAccountId)),
            'contacts' => $contacts->filter(),
            'accounts' => SmtpAccount::orderBy('label')->get(['id', 'label', 'from_email']),
        ])->title($this->template ? __('Modifier le template') : __('Nouveau template'));
    }

    private function preview(?SmtpAccount $account): RenderedEmail
    {
        $contact = Contact::find($this->previewContactId) ?? new Contact($this->locale === 'en'
            ? ['company_name' => 'Example Ltd', 'email' => 'contact@example.com', 'sector' => 'Construction', 'wilaya' => 'London']
            : ['company_name' => 'SARL Exemple', 'email' => 'contact@exemple.com', 'sector' => 'BTP', 'wilaya' => 'Paris']);

        $account ??= new SmtpAccount(['from_email' => 'you@your-domain.com', 'from_name' => 'MailPilot']);

        return app(TemplateRenderer::class)->renderParts($this->subject, $this->body_html, $this->body_text, $contact, $account, $this->locale);
    }
}
