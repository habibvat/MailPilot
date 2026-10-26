<?php

namespace App\Providers;

use App\Contracts\DnsResolver;
use App\Contracts\MailTransportFactory;
use App\Models\Setting;
use App\Services\Dns\NativeDnsResolver;
use App\Services\EmailVerifier;
use App\Services\Mail\SmtpTransportFactory;
use App\Support\Locale;
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
