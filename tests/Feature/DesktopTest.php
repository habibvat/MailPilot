<?php

use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->storage = sys_get_temp_dir().'/mailpilot-desktop-'.bin2hex(random_bytes(4));
    $this->useDesktopKey = fn () => (fn ($path) => $this->useDesktopAppKey($path))
        ->call(new AppServiceProvider($this->app), $this->storage);
});

afterEach(function () {
    @unlink($this->storage.'/app.key');
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
