<?php

use App\Livewire\Campaigns\Index;
use App\Livewire\Campaigns\Show;
use App\Livewire\Campaigns\Wizard;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Models\Template;
use Livewire\Livewire;

beforeEach(function () {
    SmtpAccount::create(['label' => 'Compte', 'host' => 'h', 'port' => 587, 'encryption' => 'tls', 'from_name' => 'Moi', 'from_email' => 'moi@moi.dz', 'hourly_limit' => 8, 'daily_limit' => 20]);
    Template::create(['name' => 'T', 'subject' => 'S', 'body_html' => 'B']);
    Contact::create(['email' => 'a@a.dz', 'verify_status' => 'valid', 'sector' => 'BTP', 'wilaya' => 'Alger']);
    Contact::create(['email' => 'b@b.dz', 'verify_status' => 'valid', 'sector' => 'Santé', 'wilaya' => 'Oran']);
    Contact::create(['email' => 'c@c.dz', 'verify_status' => 'pending', 'sector' => 'BTP', 'wilaya' => 'Alger']);
});

it('crée une campagne en 3 étapes avec un compteur de contacts éligibles', function () {
    Livewire::test(Wizard::class)
        ->call('next')
        ->assertHasErrors('name')
        ->set('name', 'BTP Alger')
        ->set('sectors', ['BTP'])
        ->assertViewHas('eligibleCount', 1)
        ->assertViewHas('segmentCount', 2)
        ->call('next')
        ->assertSet('step', 2)
        ->call('next')
        ->assertSet('step', 3)
        ->set('send_start', '18:00')
        ->set('send_end', '09:00')
        ->call('save', true)
        ->assertHasErrors('send_end')
        ->set('send_start', '09:00')
        ->set('send_end', '17:00')
        ->call('save', true)
        ->assertRedirect();

    $campaign = Campaign::firstWhere('name', 'BTP Alger');
    expect($campaign->status)->toBe('running')
        ->and($campaign->segment_filters)->toBe(['sector' => ['BTP']])
        ->and($campaign->hourly_limit)->toBe(8) // plafonné au quota du compte
        ->and($campaign->sends()->count())->toBe(1);
});

it('pilote une campagne depuis la liste', function () {
    $campaign = Campaign::create(['name' => 'C', 'template_id' => Template::value('id'), 'smtp_account_id' => SmtpAccount::value('id'), 'send_days' => [1, 2, 3, 4, 5, 6, 7]]);

    Livewire::test(Index::class)
        ->call('startCampaign', $campaign->id)
        ->assertDispatched('notify')
        ->call('pauseCampaign', $campaign->id)
        ->call('resumeCampaign', $campaign->id)
        ->call('stopCampaign', $campaign->id);

    expect($campaign->fresh()->status)->toBe('stopped')->and($campaign->fresh()->stop_reason)->toBe('Arrêtée manuellement.');
});

it('refuse de démarrer une campagne sans contact éligible', function () {
    $campaign = Campaign::create(['name' => 'Vide', 'template_id' => Template::value('id'), 'smtp_account_id' => SmtpAccount::value('id'), 'segment_filters' => ['wilaya' => ['Adrar']], 'send_days' => [1]]);

    Livewire::test(Index::class)->call('startCampaign', $campaign->id)->assertDispatched('notify', type: 'error');
    expect($campaign->fresh()->status)->toBe('draft');
});

it('affiche les pages campagnes', function () {
    $campaign = Campaign::create(['name' => 'Visible', 'template_id' => Template::value('id'), 'smtp_account_id' => SmtpAccount::value('id'), 'send_days' => [1]]);

    $this->get('/campagnes')->assertOk()->assertSee('Visible');
    $this->get('/campagnes/nouvelle')->assertOk();
    $this->get("/campagnes/{$campaign->id}")->assertOk();
    $this->get("/campagnes/{$campaign->id}/modifier")->assertOk();
    Livewire::test(Show::class, ['campaign' => $campaign])->assertSee('Journal des envois');
});

it('prévient quand les quotas de la campagne dépassent ceux du compte SMTP', function () {
    $account = SmtpAccount::create(['label' => 'Petit compte', 'host' => 'h', 'port' => 587, 'encryption' => 'tls', 'from_name' => 'M', 'from_email' => 'm@m.dz', 'hourly_limit' => 10, 'daily_limit' => 30]);

    Livewire::test(\App\Livewire\Campaigns\Wizard::class)
        ->set('smtp_account_id', $account->id)
        ->set('step', 3)
        ->set('hourly_limit', 10)
        ->set('daily_limit', 30)
        ->assertDontSee('la campagne n\'enverra pas plus')
        ->set('hourly_limit', 100)
        ->set('daily_limit', 500)
        ->assertSee('Le compte « Petit compte » est limité à 10/h et 30/jour : la campagne n\'enverra pas plus');
});

it('modifie les réglages d\'une campagne démarrée sans toucher à ses destinataires', function () {
    $campaign = Campaign::create(['name' => 'En cours', 'template_id' => Template::value('id'), 'smtp_account_id' => SmtpAccount::value('id'), 'segment_filters' => ['sector' => ['BTP']], 'send_days' => [1, 2, 3, 4, 5]]);
    app(\App\Services\Campaigns\CampaignManager::class)->start($campaign->fresh());
    $other = Template::create(['name' => 'T2', 'subject' => 'S2', 'body_html' => 'B2']);

    Livewire::test(Wizard::class, ['campaign' => $campaign->fresh()])
        ->assertSee('Le segment ne peut plus être modifié')
        ->set('step', 3)
        ->assertSee('Enregistrer les modifications')
        ->assertDontSee('Enregistrer et démarrer')
        ->set('name', 'Renommée')
        ->set('sectors', ['Santé'])
        ->set('template_id', $other->id)
        ->set('hourly_limit', 5)
        ->set('send_days', [6, 7])
        ->set('send_start', '10:00')
        ->set('send_end', '12:00')
        ->call('save')
        ->assertRedirect(route('campaigns.show', $campaign));

    $campaign->refresh();
    expect($campaign->status)->toBe('running')
        ->and($campaign->name)->toBe('Renommée')
        ->and($campaign->template_id)->toBe($other->id)
        ->and($campaign->hourly_limit)->toBe(5)
        ->and($campaign->send_days)->toBe([6, 7])
        ->and($campaign->send_start)->toBe('10:00')
        ->and($campaign->segment_filters)->toBe(['sector' => ['BTP']])
        ->and($campaign->sends()->count())->toBe(1);

    Livewire::test(Show::class, ['campaign' => $campaign])->assertSee(route('campaigns.edit', $campaign));
});

it('ne permet plus de modifier une campagne terminée', function () {
    $campaign = Campaign::create(['name' => 'Finie', 'template_id' => Template::value('id'), 'smtp_account_id' => SmtpAccount::value('id'), 'send_days' => [1], 'status' => 'completed']);

    $this->get("/campagnes/{$campaign->id}/modifier")->assertRedirect(route('campaigns.show', $campaign));
});
