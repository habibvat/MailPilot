<?php

use App\Contracts\DnsResolver;
use App\Jobs\VerifyContactsJob;
use App\Models\Contact;
use App\Models\DomainCache;
use App\Services\EmailVerifier;
use App\Services\VerificationDispatcher;
use Illuminate\Support\Facades\Queue;

/**
 * Résolveur DNS factice qui compte les requêtes par domaine.
 */
class CountingDnsResolver implements DnsResolver
{
    public array $calls = [];

    public function __construct(private array $withoutMx = []) {}

    public function hasMx(string $domain): bool
    {
        $this->calls[$domain] = ($this->calls[$domain] ?? 0) + 1;

        return ! in_array($domain, $this->withoutMx, true);
    }
}

beforeEach(function () {
    $this->dns = new CountingDnsResolver(['sans-mx.dz']);
    $this->app->instance(DnsResolver::class, $this->dns);
});

it('classe les adresses en valide, à risque ou invalide avec une raison', function (string $email, string $status, string $reason) {
    $result = app(EmailVerifier::class)->verify($email);

    expect($result['status'])->toBe($status)->and($result['reason'])->toContain($reason);
})->with([
    ['contact@entreprise.dz', 'valid', 'valides'],
    ['pas un email', 'invalid', 'Syntaxe'],
    ['a@b', 'invalid', 'Syntaxe'],
    ['test@yopmail.com', 'invalid', 'jetable'],
    ['test@x.mailinator.com', 'invalid', 'jetable'],
    ['contact@sans-mx.dz', 'invalid', 'MX'],
    ['noreply@entreprise.dz', 'risky', 'rôle'],
    ['no-reply@entreprise.dz', 'risky', 'rôle'],
    ['postmaster@entreprise.dz', 'risky', 'rôle'],
    ['abuse@entreprise.dz', 'risky', 'rôle'],
    ['mailer-daemon@entreprise.dz', 'risky', 'rôle'],
]);

it('ne refait jamais de requête DNS pour un domaine en cache de moins de 30 jours', function () {
    Contact::create(['email' => 'a@cache.dz']);
    Contact::create(['email' => 'b@cache.dz']);
    Contact::create(['email' => 'c@cache.dz']);

    // Premier passage : une seule requête pour trois contacts du même domaine.
    app(VerificationDispatcher::class)->dispatch(Contact::query());
    expect($this->dns->calls['cache.dz'])->toBe(1);
    expect(DomainCache::firstWhere('domain', 'cache.dz')->has_mx)->toBeTrue();

    // Nouveau job (nouveau verifier, cache mémoire vide) : le cache base suffit.
    $this->travel(29)->days();
    $this->app->forgetScopedInstances();
    (new VerifyContactsJob(Contact::pluck('id')->all()))->handle(app(EmailVerifier::class));
    expect($this->dns->calls['cache.dz'])->toBe(1);

    // Au-delà de 30 jours, le domaine est revérifié une fois.
    $this->travel(2)->days();
    $this->app->forgetScopedInstances();
    (new VerifyContactsJob(Contact::pluck('id')->all()))->handle(app(EmailVerifier::class));
    expect($this->dns->calls['cache.dz'])->toBe(2);
    expect(Contact::where('verify_status', 'valid')->count())->toBe(3);
});

it('ne consulte pas le DNS pour un domaine jetable', function () {
    app(EmailVerifier::class)->verify('x@yopmail.com');

    expect($this->dns->calls)->toBe([])
        ->and(DomainCache::firstWhere('domain', 'yopmail.com')->is_disposable)->toBeTrue();
});

it('découpe la vérification en lots', function () {
    Queue::fake();
    config(['mailpilot.verify_batch_size' => 2]);
    foreach (range(1, 5) as $i) {
        Contact::create(['email' => "c$i@lot.dz"]);
    }

    expect(app(VerificationDispatcher::class)->dispatch(Contact::query()))->toBe(5);
    Queue::assertPushed(VerifyContactsJob::class, 3);
});
