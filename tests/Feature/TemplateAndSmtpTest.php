<?php

use App\Contracts\MailTransportFactory;
use App\Livewire\SmtpAccounts\Form as SmtpForm;
use App\Livewire\Templates\Form as TemplateForm;
use App\Models\Contact;
use App\Models\Setting;
use App\Models\SmtpAccount;
use App\Models\Template;
use App\Services\Mail\TemplateRenderer;
use Livewire\Livewire;
use Tests\Fakes\FakeMailTransportFactory;

beforeEach(function () {
    $this->transport = new FakeMailTransportFactory;
    $this->app->instance(MailTransportFactory::class, $this->transport);
    Setting::put('legal_company', 'Ma Société SARL');
    Setting::put('legal_address', '12 rue Didouche Mourad, Alger');
    Setting::put('legal_phone', '023 00 00 00');
    $this->account = SmtpAccount::create([
        'label' => 'Principal', 'host' => 'smtp.test', 'port' => 587, 'encryption' => 'tls',
        'username' => 'u', 'password' => 'secret', 'from_name' => 'Commercial', 'from_email' => 'commercial@moi.dz',
        'imap_host' => 'imap.test', 'imap_port' => 993, 'imap_username' => 'boite@moi.dz', 'imap_password' => 'x',
    ]);
});

it('remplace les variables, échappe le HTML et ajoute le pied de page légal', function () {
    $template = new Template(['subject' => 'Offre pour {{entreprise}}', 'body_html' => '<p>{{ entreprise }} à {{wilaya}} ({{secteur}}) – {{email}}</p>', 'body_text' => null]);
    $contact = new Contact(['company_name' => 'A&B <Sarl>', 'wilaya' => 'Oran', 'sector' => 'BTP', 'email' => 'a@b.dz']);

    $email = app(TemplateRenderer::class)->render($template, $contact, $this->account);

    expect($email->subject)->toBe('Offre pour A&B <Sarl>')
        ->and($email->html)->toContain('A&amp;B &lt;Sarl&gt; à Oran (BTP) – a@b.dz')
        ->and($email->html)->toContain('Ma Société SARL')
        ->and($email->html)->toContain('12 rue Didouche Mourad, Alger')
        ->and($email->html)->toContain('023 00 00 00')
        ->and($email->html)->toContain('mailto:boite@moi.dz?subject=D%C3%A9sinscription')
        ->and($email->text)->toContain('A&B <Sarl> à Oran')
        ->and($email->text)->toContain('boite@moi.dz');
});

it('utilise des valeurs par défaut quand une variable est vide', function () {
    $template = new Template(['subject' => 'Bonjour {{entreprise}}', 'body_html' => 'x']);
    expect(app(TemplateRenderer::class)->render($template, new Contact(['email' => 'a@b.dz']), $this->account)->subject)
        ->toBe('Bonjour votre entreprise');
});

it('rédige l\'email en anglais pour un template en anglais, avec les variables anglaises', function () {
    $template = new Template(['locale' => 'en', 'subject' => 'Hello {{company}}', 'body_html' => '<p>{{sector}} in {{region}} – {{entreprise}}</p>']);
    $contact = new Contact(['company_name' => 'Acme Ltd', 'sector' => 'Retail', 'email' => 'a@acme.co.uk']);

    $email = app(TemplateRenderer::class)->render($template, $contact, $this->account);

    expect($email->subject)->toBe('Hello Acme Ltd')
        ->and($email->html)->toContain('Retail in your region – Acme Ltd')
        ->and($email->html)->toContain('<html lang="en">')
        ->and($email->html)->toContain('click here to unsubscribe')
        ->and($email->html)->toContain('Tel.: 023 00 00 00')
        ->and($email->html)->toContain('mailto:boite@moi.dz?subject=Unsubscribe')
        ->and($email->html)->not->toContain('désinscri')
        ->and($email->text)->toContain('To stop receiving our emails, reply “STOP” or write to boite@moi.dz with the subject “Unsubscribe”.');
});

it('crée un template, affiche l\'aperçu et envoie un test', function () {
    Contact::create(['email' => 'client@x.dz', 'company_name' => 'Client X', 'verify_status' => 'valid']);

    Livewire::test(TemplateForm::class)
        ->set('name', 'Offre')
        ->set('subject', 'Pour {{entreprise}}')
        ->set('body_html', '<p>Bonjour {{entreprise}}</p>')
        ->assertSee('Pour Client X')
        ->set('testEmail', 'moi@test.dz')
        ->call('sendTest')
        ->assertHasNoErrors()
        ->call('save')
        ->assertRedirect();

    expect(Template::firstWhere('name', 'Offre'))->not->toBeNull();
    expect($this->transport->recipients())->toBe(['moi@test.dz']);
    $sent = $this->transport->sent[0];
    expect($sent->getSubject())->toBe('[TEST] Pour Client X')
        ->and($sent->getHeaders()->get('List-Unsubscribe')->getBodyAsString())->toBe('<mailto:boite@moi.dz?subject=unsubscribe>')
        ->and($sent->getHtmlBody())->toContain('Ma Société SARL');
});

it('chiffre les mots de passe et conserve l\'ancien si le champ est laissé vide', function () {
    Livewire::test(SmtpForm::class, ['account' => $this->account])
        ->set('form.label', 'Renommé')
        ->call('save')
        ->assertHasNoErrors();

    $account = $this->account->fresh();
    expect($account->label)->toBe('Renommé')->and($account->password)->toBe('secret');
    expect(DB::table('smtp_accounts')->value('password'))->not->toContain('secret');
});

it('teste la connexion SMTP', function () {
    Livewire::test(SmtpForm::class, ['account' => $this->account])
        ->call('testSmtp')
        ->assertSet('smtpResult.ok', true);
});

it('affiche les pages templates et SMTP', function () {
    $this->get('/templates')->assertOk();
    $this->get('/templates/nouveau')->assertOk();
    $this->get('/smtp')->assertOk()->assertSee('Principal');
    $this->get('/smtp/nouveau')->assertOk();
    $this->get('/smtp/'.$this->account->id)->assertOk();
});
