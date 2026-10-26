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
