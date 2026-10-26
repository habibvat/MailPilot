<?php

use App\Livewire\Settings;
use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use App\Models\Setting;
use App\Models\SmtpAccount;
use App\Models\Suppression;
use App\Models\Template;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

it('affiche le tableau de bord avec les envois du jour et les taux par campagne', function () {
    $account = SmtpAccount::create(['label' => 'Compte A', 'host' => 'h', 'port' => 587, 'encryption' => 'tls', 'from_name' => 'M', 'from_email' => 'm@m.dz', 'daily_limit' => 30]);
    $campaign = Campaign::create(['name' => 'Campagne Oran', 'template_id' => Template::create(['name' => 'T', 'subject' => 'S', 'body_html' => 'B'])->id, 'smtp_account_id' => $account->id, 'status' => 'running', 'send_days' => [1]]);
    foreach (['sent', 'sent', 'sent', 'bounced', 'queued'] as $i => $status) {
        $contact = Contact::create(['email' => "c$i@x.dz", 'verify_status' => 'valid']);
        CampaignSend::create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'status' => $status, 'scheduled_at' => now(), 'sent_at' => $status === 'sent' ? now() : null]);
    }

    $this->get('/')
        ->assertOk()
        ->assertSee('Campagne Oran')
        ->assertSee('Compte A')
        ->assertSee('25 %')   // taux de rebond : 1 / (3 + 1)
        ->assertSee('80 %');  // progression : 4 / 5
});

it('enregistre l\'identité légale utilisée dans le pied de page', function () {
    Livewire::test(Settings::class)
        ->set('legal.legal_company', 'SARL Test')
        ->set('legal.legal_address', 'Alger')
        ->set('legal.legal_phone', '021')
        ->call('saveLegal')
        ->assertHasNoErrors()
        ->set('defaults.default_min_delay', 100)
        ->set('defaults.default_max_delay', 50)
        ->call('saveDefaults')
        ->assertHasErrors('defaults.default_max_delay');

    Setting::flushCache();
    expect(Setting::get('legal_company'))->toBe('SARL Test');
});

it('gère la liste noire : ajout, import en masse et suppression', function () {
    $file = UploadedFile::fake()->createWithContent('liste.csv', "email\nfichier@x.dz\nDOUBLON@x.dz\n");

    Livewire::test(Settings::class)
        ->set('newEmail', 'Manuel@X.dz')
        ->call('addSuppression')
        ->assertHasNoErrors()
        ->set('bulkEmails', "colle1@x.dz, colle2@x.dz\ndoublon@x.dz pas-un-email")
        ->set('suppressionFile', $file)
        ->call('importSuppressions')
        ->assertSee('fichier@x.dz');

    expect(Suppression::pluck('email')->sort()->values()->all())
        ->toBe(['colle1@x.dz', 'colle2@x.dz', 'doublon@x.dz', 'fichier@x.dz', 'manuel@x.dz']);

    Livewire::test(Settings::class)->call('deleteSuppression', Suppression::firstWhere('email', 'manuel@x.dz')->id);
    expect(Suppression::contains('manuel@x.dz'))->toBeFalse();
});

it('affiche la page paramètres', function () {
    $this->get('/parametres')->assertOk()->assertSee('Liste noire');
});

it('refuse les requêtes qui ne viennent pas de la machine locale', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])->get('/')->assertForbidden();
});

it('enregistre les préférences régionales et les applique à une nouvelle campagne', function () {
    Livewire::test(Settings::class)
        ->assertSet('sendDays', [1, 2, 3, 4, 5])
        ->assertSet('algerianRegions', false)
        ->set('sendDays', [])
        ->call('saveRegional')
        ->assertHasErrors('sendDays')
        ->set('sendDays', ['7', '1', '2', '3', '4'])
        ->set('algerianRegions', true)
        ->set('restorePhoneZero', true)
        ->call('saveRegional')
        ->assertHasNoErrors();

    expect(Setting::get('default_send_days'))->toBe('1,2,3,4,7')
        ->and(Setting::get('import_algerian_regions'))->toBe('1')
        ->and(Setting::get('import_restore_phone_zero'))->toBe('1');

    Livewire::test(\App\Livewire\Campaigns\Wizard::class)->assertSet('send_days', [1, 2, 3, 4, 7]);
});
