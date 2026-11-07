<?php

namespace App\Providers;

use App\Contracts\DnsResolver;
use App\Contracts\MailTransportFactory;
use App\Models\Setting;
use App\Services\Dns\NativeDnsResolver;
use App\Services\EmailVerifier;
use App\Services\Mail\SmtpTransportFactory;
use App\Support\Locale;
use App\Support\SystemTimezone;
use App\Support\WilayaNormalizer;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsResolver::class, NativeDnsResolver::class);
        $this->app->bind(MailTransportFactory::class, SmtpTransportFactory::class);
        // Caches mémoire réinitialisés à chaque job de la file d'attente.
        $this->app->scoped(WilayaNormalizer::class);
        $this->app->scoped(EmailVerifier::class);

        if (config('nativephp-internal.running')) {
            $this->useDesktopAppKey(config('nativephp-internal.storage_path'));
            $this->useDesktopTimezone(config('nativephp-internal.storage_path'));
        }
    }

    /**
     * Application desktop : le .env embarqué est en lecture seule et contient UTC, donc
     * mailpilot:configure-env ne peut pas s'appliquer. Comme lui, on détecte le fuseau du
     * système au premier lancement puis on le garde : les dates sont enregistrées en heure
     * locale, elles seraient décalées si le fuseau changeait ensuite.
     */
    protected function useDesktopTimezone(string $storagePath): void
    {
        $path = $storagePath.DIRECTORY_SEPARATOR.'timezone';
        $timezone = is_file($path) ? trim(file_get_contents($path)) : null;

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = SystemTimezone::detect();
            @file_put_contents($path, $timezone);
        }

        config(['app.timezone' => $timezone, 'mailpilot.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }

    /**
     * Application desktop : l'APP_KEY du .env embarqué est la même pour tous et lisible
     * dans le paquet. Chaque installation chiffre donc ses mots de passe SMTP avec sa
     * propre clé, créée au premier lancement dans le dossier de données de l'utilisateur.
     */
    protected function useDesktopAppKey(string $storagePath): void
    {
        $path = $storagePath.DIRECTORY_SEPARATOR.'app.key';

        if (! is_file($path)) {
            if (! is_dir($storagePath)) {
                @mkdir($storagePath, 0700, true);
            }
            // Serveur, workers et planificateur démarrent ensemble : link() est atomique et
            // échoue si un autre processus a déjà créé la clé, que l'on relit alors.
            $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
            file_put_contents($tmp, 'base64:'.base64_encode(random_bytes(32)));
            @chmod($tmp, 0600);
            @link($tmp, $path);
            @unlink($tmp);
        }

        config(['app.key' => trim(file_get_contents($path))]);
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination::tailwind');

        // Le worker tourne longtemps : on relit les paramètres (dont la langue) avant chaque job.
        Event::listen(JobProcessing::class, function () {
            Setting::flushCache();
            Locale::apply();
        });
    }
}
