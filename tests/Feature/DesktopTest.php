<?php

use App\Providers\AppServiceProvider;
use App\Support\SystemTimezone;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->storage = sys_get_temp_dir().'/mailpilot-desktop-'.bin2hex(random_bytes(4));
    $this->useDesktopKey = fn () => (fn ($path) => $this->useDesktopAppKey($path))
        ->call(new AppServiceProvider($this->app), $this->storage);
    $this->useDesktopTimezone = fn () => (fn ($path) => $this->useDesktopTimezone($path))
        ->call(new AppServiceProvider($this->app), $this->storage);
    $this->defaultTimezone = date_default_timezone_get();
});

afterEach(function () {
    date_default_timezone_set($this->defaultTimezone);
    @unlink($this->storage.'/app.key');
    @unlink($this->storage.'/timezone');
    @rmdir($this->storage);
});

it("crée une clé propre à l'installation desktop et la réutilise ensuite", function () {
    $bundled = config('app.key');

    ($this->useDesktopKey)();
    $key = config('app.key');

    expect($key)->not->toBe($bundled)
        ->and($key)->toStartWith('base64:')
        ->and(strlen(base64_decode(substr($key, 7))))->toBe(32)
        ->and(file_get_contents($this->storage.'/app.key'))->toBe($key);

    config(['app.key' => $bundled]);
    ($this->useDesktopKey)();
    expect(config('app.key'))->toBe($key);
});

it("chiffre les mots de passe SMTP avec la clé de l'installation, pas celle du paquet", function () {
    $bundled = base64_decode(substr(config('app.key'), 7));
    ($this->useDesktopKey)();
    $this->app->forgetInstance('encrypter');
    Crypt::clearResolvedInstances();

    $encrypted = Crypt::encryptString('secret');

    expect(Crypt::decryptString($encrypted))->toBe('secret')
        ->and(fn () => (new Encrypter($bundled, config('app.cipher')))->decryptString($encrypted))
        ->toThrow(DecryptException::class);
});

it('utilise le fuseau du système détecté au premier lancement, puis le garde', function () {
    @mkdir($this->storage, 0700, true);
    config(['app.timezone' => 'UTC', 'mailpilot.timezone' => 'UTC']);

    ($this->useDesktopTimezone)();
    $detected = SystemTimezone::detect();

    expect(config('mailpilot.timezone'))->toBe($detected)
        ->and(config('app.timezone'))->toBe($detected)
        ->and(date_default_timezone_get())->toBe($detected)
        ->and(file_get_contents($this->storage.'/timezone'))->toBe($detected);

    // Les dates sont enregistrées en heure locale : le fuseau retenu ne change plus ensuite.
    file_put_contents($this->storage.'/timezone', 'Asia/Tokyo');
    ($this->useDesktopTimezone)();
    expect(config('mailpilot.timezone'))->toBe('Asia/Tokyo');
});

it("lève la limite d'exécution des requêtes web pour le worker et le planificateur", function () {
    set_time_limit(300);

    (new AppServiceProvider($this->app))->register();

    expect(ini_get('max_execution_time'))->toBe('0');
});
