<?php

use App\Contracts\MailTransportFactory;
use App\Jobs\SendCampaignEmailJob;
use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Models\Suppression;
use App\Models\Template;
use App\Services\Campaigns\CampaignManager;
use App\Services\Campaigns\SendDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\Fakes\FakeMailTransportFactory;

beforeEach(function () {
    // Lundi 10:00 à Alger.
    $this->travelTo(Carbon::parse('2026-11-02 10:00:00', 'Africa/Algiers'));

    $this->transport = new FakeMailTransportFactory;
    $this->app->instance(MailTransportFactory::class, $this->transport);
    Queue::fake();

    $this->account = SmtpAccount::create([
        'label' => 'Compte', 'host' => 'smtp.test', 'port' => 587, 'encryption' => 'tls',
        'from_name' => 'Moi', 'from_email' => 'moi@moi.dz', 'hourly_limit' => 1000, 'daily_limit' => 1000,
    ]);
    $this->template = Template::create(['name' => 'T', 'subject' => 'Bonjour {{entreprise}}', 'body_html' => '<p>Offre pour {{entreprise}}</p>']);
});

function contacts(int $count, array $attributes = []): void
{
    foreach (range(1, $count) as $i) {
        Contact::create($attributes + [
            'email' => "contact$i@societe$i.dz",
            'company_name' => "Société $i",
            'verify_status' => 'valid',
            'status' => 'active',
        ]);
    }
}

function campaign(array $attributes = []): Campaign
{
    return Campaign::create($attributes + [
        'name' => 'Test',
        'template_id' => test()->template->id,
        'smtp_account_id' => test()->account->id,
        'hourly_limit' => 1000,
        'daily_limit' => 1000,
        'send_days' => [1, 2, 3, 4, 5, 6, 7],
        'send_start' => '08:00',
        'send_end' => '18:00',
        'min_delay' => 0,
        'max_delay' => 0,
        'status' => 'draft',
    ]);
}

/** Exécute les jobs d'envoi capturés (éventuellement plusieurs fois). */
function runSendJobs(?array $jobs = null): void
{
    foreach ($jobs ?? Queue::pushed(SendCampaignEmailJob::class)->all() as $job) {
        app()->call([$job, 'handle']);
    }
}

function tick(): array
{
    return app(SendDispatcher::class)->tick();
}

// --- Quotas -------------------------------------------------------------

it('respecte le quota horaire de la campagne', function () {
    contacts(20);
    $campaign = campaign(['hourly_limit' => 5]);
    app(CampaignManager::class)->start($campaign);

    expect(tick()[$campaign->id])->toBe(5);
    expect(tick()[$campaign->id])->toBe(0);
    runSendJobs();
    expect($this->transport->sent)->toHaveCount(5);

    $this->travel(30)->minutes();
    expect(tick()[$campaign->id])->toBe(0);

    // Fenêtre glissante d'une heure : de nouveaux envois sont autorisés après 60 minutes.
    $this->travel(31)->minutes();
    expect(tick()[$campaign->id])->toBe(5);
    expect(CampaignSend::whereIn('status', CampaignSend::QUOTA_STATUSES)->count())->toBe(10);
});

it('applique le quota horaire du compte SMTP à toutes ses campagnes', function () {
    contacts(10);
    $this->account->update(['hourly_limit' => 3]);
    $first = campaign(['name' => 'A', 'segment_filters' => ['tag' => []]]);
    app(CampaignManager::class)->start($first);

    expect(tick()[$first->id])->toBe(3);

    $this->travel(5)->minutes();
    expect(tick()[$first->id])->toBe(0);
});

it('respecte le quota journalier', function () {
    contacts(10);
    $campaign = campaign(['hourly_limit' => 100, 'daily_limit' => 4]);
    app(CampaignManager::class)->start($campaign);

    expect(tick()[$campaign->id])->toBe(4);
    runSendJobs();
    $this->travel(2)->hours();
    expect(tick()[$campaign->id])->toBe(0);

    $this->travelTo(Carbon::parse('2026-11-03 09:00:00', 'Africa/Algiers'));
    expect(tick()[$campaign->id])->toBe(4);
});

it('espace les envois avec un délai aléatoire', function () {
    contacts(10);
    $campaign = campaign(['min_delay' => 20, 'max_delay' => 25]);
    app(CampaignManager::class)->start($campaign);

    // Horizon d'une minute : 3 envois max (t=0, ~20-25 s, ~40-50 s).
    expect(tick()[$campaign->id])->toBe(3);
    $times = CampaignSend::where('status', 'dispatched')->orderBy('scheduled_at')->pluck('scheduled_at');
    expect($times[0]->diffInSeconds($times[1]))->toBeBetween(20, 25)
        ->and($times[1]->diffInSeconds($times[2]))->toBeBetween(20, 25);
});

it('n\'envoie pas hors des jours et heures d\'envoi', function () {
    contacts(3);
    $campaign = campaign(['send_days' => [1], 'send_start' => '14:00', 'send_end' => '16:00']);
    app(CampaignManager::class)->start($campaign);

    expect(tick())->not->toHaveKey($campaign->id);
    $this->travelTo(Carbon::parse('2026-11-02 14:30:00', 'Africa/Algiers'));
    expect(tick()[$campaign->id])->toBe(3);
    $this->travelTo(Carbon::parse('2026-11-03 14:30:00', 'Africa/Algiers')); // mardi
    expect(tick())->not->toHaveKey($campaign->id);
});

// --- Éligibilité ----------------------------------------------------------

it('exclut à l\'activation les contacts supprimés, non valides, inactifs ou contactés récemment', function () {
    contacts(3);
    Contact::create(['email' => 'banni@x.dz', 'verify_status' => 'valid']);
    Suppression::add('banni@x.dz', 'unsubscribe');
    Contact::create(['email' => 'risque@x.dz', 'verify_status' => 'risky']);
    Contact::create(['email' => 'rebond@x.dz', 'verify_status' => 'valid', 'status' => 'bounced']);
    Contact::create(['email' => 'recent@x.dz', 'verify_status' => 'valid', 'last_contacted_at' => now()->subDays(10)]);
    Contact::create(['email' => 'ancien@x.dz', 'verify_status' => 'valid', 'last_contacted_at' => now()->subDays(61)]);

    $count = app(CampaignManager::class)->start(campaign());

    expect($count)->toBe(4);
    expect(CampaignSend::with('contact')->get()->pluck('contact.email')->all())
        ->not->toContain('banni@x.dz', 'risque@x.dz', 'rebond@x.dz', 'recent@x.dz')
        ->toContain('ancien@x.dz');
});

it('n\'envoie pas à un contact ajouté à la liste noire après l\'activation', function () {
    contacts(2);
    $campaign = campaign();
    app(CampaignManager::class)->start($campaign);
    Suppression::add('contact1@societe1.dz', 'manual');

    tick();
    runSendJobs();

    expect($this->transport->recipients())->toBe(['contact2@societe2.dz']);
    expect(CampaignSend::where('status', 'skipped')->first()->error)->toBe('Adresse en liste noire');
    expect($campaign->fresh()->status)->toBe('completed');
});

it('applique les filtres de segment', function () {
    contacts(2, ['sector' => 'BTP', 'wilaya' => 'Alger']);
    Contact::create(['email' => 'oran@x.dz', 'verify_status' => 'valid', 'sector' => 'BTP', 'wilaya' => 'Oran']);
    Contact::create(['email' => 'info@x.dz', 'verify_status' => 'valid', 'sector' => 'Informatique', 'wilaya' => 'Alger', 'tags' => ['vip']]);

    expect(app(CampaignManager::class)->start(campaign(['segment_filters' => ['wilaya' => ['Alger'], 'sector' => ['BTP']]])))->toBe(2);
    expect(app(CampaignManager::class)->start(campaign(['segment_filters' => ['tag' => ['vip']]])))->toBe(1);
});

// --- Envoi et absence de doublon -------------------------------------------

it('envoie un email par destinataire avec variables, en-têtes et suivi', function () {
    contacts(1);
    $campaign = campaign();
    app(CampaignManager::class)->start($campaign);
    tick();
    runSendJobs();

    $email = $this->transport->sent[0];
    $send = CampaignSend::first();
    expect($email->getTo())->toHaveCount(1)
        ->and($email->getSubject())->toBe('Bonjour Société 1')
        ->and($email->getHtmlBody())->toContain('Offre pour Société 1')
        ->and($email->getHeaders()->get('List-Unsubscribe')->getBodyAsString())->toContain('mailto:moi@moi.dz')
        ->and($email->getHeaders()->get('X-MailPilot-Send')->getBodyAsString())->toBe((string) $send->id)
        ->and($send->status)->toBe('sent')
        ->and($send->message_id)->toEndWith('@moi.dz')
        ->and(Contact::first()->last_contacted_at)->not->toBeNull();
});

it('n\'envoie jamais deux fois le même email si un job est exécuté plusieurs fois', function () {
    contacts(3);
    app(CampaignManager::class)->start(campaign());
    tick();

    runSendJobs();
    runSendJobs();
    runSendJobs();

    expect($this->transport->sent)->toHaveCount(3);
});

it('reprend après une fermeture de l\'application sans aucun doublon', function () {
    contacts(5);
    $campaign = campaign();
    app(CampaignManager::class)->start($campaign);

    tick();
    $jobs = Queue::pushed(SendCampaignEmailJob::class)->all();
    expect($jobs)->toHaveCount(5);

    // Avant la fermeture : le job 1 a envoyé, le job 2 a été coupé en pleine session SMTP.
    runSendJobs([$jobs[0]]);
    CampaignSend::whereKey($jobs[1]->sendId)->update(['status' => 'sending', 'locked_at' => now()]);

    // L'application reste fermée deux heures. Au redémarrage, le planificateur repasse...
    $this->travel(2)->hours();
    tick();

    // ...et le worker exécute tous les anciens jobs restés dans la file puis les nouveaux.
    runSendJobs($jobs);
    tick();
    runSendJobs();
    tick();
    runSendJobs();

    $recipients = $this->transport->recipients();
    expect($recipients)->toHaveCount(count(array_unique($recipients)));
    // Le contact 2 n'est pas renvoyé (envoi peut-être parti) ; les 3 autres partent une seule fois.
    expect($recipients)->toHaveCount(4)->not->toContain('contact2@societe2.dz');
    expect(CampaignSend::find($jobs[1]->sendId)->status)->toBe('failed');
    expect($campaign->fresh()->status)->toBe('completed');
});

it('remet en file un job exécuté en retard au lieu d\'envoyer en rafale', function () {
    contacts(3);
    $campaign = campaign(['min_delay' => 10, 'max_delay' => 10]);
    app(CampaignManager::class)->start($campaign);
    tick();
    $jobs = Queue::pushed(SendCampaignEmailJob::class)->all();

    $this->travel(10)->minutes();
    runSendJobs($jobs);

    expect($this->transport->sent)->toBe([]);
    expect(CampaignSend::where('status', 'queued')->count())->toBe(3);
});

it('ne fait rien pendant une pause et reprend ensuite', function () {
    contacts(2);
    $campaign = campaign();
    $manager = app(CampaignManager::class);
    $manager->start($campaign);
    tick();
    $manager->pause($campaign->fresh());

    runSendJobs();
    expect($this->transport->sent)->toBe([]);
    expect(tick())->not->toHaveKey($campaign->id);

    $manager->resume($campaign->fresh());
    tick();
    runSendJobs();
    expect($this->transport->recipients())->toHaveCount(2);
});

// --- Disjoncteurs -----------------------------------------------------------

it('arrête la campagne après 10 échecs SMTP consécutifs', function () {
    contacts(15);
    $campaign = campaign();
    app(CampaignManager::class)->start($campaign);
    $this->transport->failWithCode = 535;

    tick();
    runSendJobs();

    $campaign->refresh();
    expect($campaign->status)->toBe('stopped')
        ->and($campaign->stop_reason)->toContain('10 échecs SMTP consécutifs')
        ->and(CampaignSend::where('status', 'failed')->count())->toBe(10)
        // Les jobs restants voient la campagne arrêtée et remettent leur envoi en file.
        ->and(CampaignSend::where('status', 'queued')->count())->toBe(5);
});

it('remet en file une erreur temporaire puis abandonne après 3 tentatives', function () {
    contacts(1);
    $campaign = campaign();
    app(CampaignManager::class)->start($campaign);
    $this->transport->failWithCode = 421;

    foreach (range(1, 3) as $attempt) {
        tick();
        runSendJobs(array_slice(Queue::pushed(SendCampaignEmailJob::class)->all(), -1));
    }

    $send = CampaignSend::first();
    expect($send->status)->toBe('failed')->and($send->attempts)->toBe(3);
});

it('arrête la campagne si le taux de rebond dépasse 5 % au-delà de 50 envois', function () {
    contacts(60);
    $campaign = campaign();
    app(CampaignManager::class)->start($campaign);

    // 47 envois réussis puis 3 rebonds (6 % de 50).
    $sends = CampaignSend::orderBy('id')->get();
    $sends->take(47)->each->update(['status' => 'sent', 'scheduled_at' => now(), 'sent_at' => now()]);
    $sends->slice(47, 2)->each->update(['status' => 'bounced', 'scheduled_at' => now()]);
    tick();
    expect($campaign->fresh()->status)->toBe('running'); // 49 envois : pas encore évalué

    $sends->slice(49, 1)->each->update(['status' => 'bounced', 'scheduled_at' => now()]);
    tick();
    expect($campaign->fresh()->status)->toBe('stopped')
        ->and($campaign->fresh()->stop_reason)->toContain('taux de rebond de 6,0 %');
});

it('traite un refus définitif du destinataire comme un rebond', function () {
    contacts(1);
    app(CampaignManager::class)->start(campaign());
    $this->transport->failWithCode = 550;
    // Le message doit indiquer un problème de destinataire.
    $this->app->instance(MailTransportFactory::class, new class extends FakeMailTransportFactory
    {
        public function make(SmtpAccount $account): TransportInterface
        {
            return new class implements TransportInterface
            {
                public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
                {
                    throw new TransportException('550 5.1.1 User unknown', 550);
                }

                public function __toString(): string
                {
                    return 'fake://';
                }
            };
        }
    });

    tick();
    runSendJobs();

    expect(CampaignSend::first()->status)->toBe('bounced')
        ->and(Contact::first()->status)->toBe('bounced')
        ->and(Suppression::contains('contact1@societe1.dz'))->toBeTrue();
});
