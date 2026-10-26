<?php

namespace App\Services;

use App\Contracts\DnsResolver;
use App\Models\Contact;
use App\Models\DomainCache;
use App\Support\EmailParser;

/**
 * Vérifie une adresse : syntaxe, domaine jetable, enregistrement MX (avec cache par domaine), adresse de rôle.
 */
class EmailVerifier
{
    /** @var array<string,true>|null */
    private ?array $disposable = null;

    /** @var array<string,DomainCache> cache mémoire pour le lot en cours */
    private array $domains = [];

    public function __construct(private DnsResolver $dns) {}

    /**
     * @return array{status: string, reason: string}
     */
    public function verify(string $email): array
    {
        $email = EmailParser::clean($email);

        if (! EmailParser::isValid($email)) {
            return ['status' => 'invalid', 'reason' => __('Syntaxe invalide')];
        }

        $domain = $this->domain(EmailParser::domain($email));

        if ($domain->is_disposable) {
            return ['status' => 'invalid', 'reason' => __('Domaine jetable')];
        }

        if (! $domain->has_mx) {
            return ['status' => 'invalid', 'reason' => __('Domaine sans enregistrement MX')];
        }

        $local = EmailParser::localPart($email);
        foreach (config('mailpilot.role_accounts') as $role) {
            if ($local === $role || str_starts_with($local, $role.'.') || str_starts_with($local, $role.'+')) {
                return ['status' => 'risky', 'reason' => __('Adresse de rôle (:role)', ['role' => $role])];
            }
        }

        return ['status' => 'valid', 'reason' => __('Domaine et syntaxe valides')];
    }

    public function verifyContact(Contact $contact): Contact
    {
        $result = $this->verify($contact->email);

        $contact->forceFill([
            'verify_status' => $result['status'],
            'verify_reason' => $result['reason'],
            'verified_at' => now(),
        ])->save();

        return $contact;
    }

    /**
     * Renvoie l'état d'un domaine. Une requête DNS n'est faite que si le domaine
     * est absent du cache ou vérifié il y a plus de 30 jours.
     */
    public function domain(string $domain): DomainCache
    {
        if (isset($this->domains[$domain])) {
            return $this->domains[$domain];
        }

        $cached = DomainCache::firstWhere('domain', $domain);
        $freshAfter = now()->subDays(config('mailpilot.mx_cache_days'));

        if ($cached === null || $cached->checked_at === null || $cached->checked_at->lt($freshAfter)) {
            $isDisposable = $this->isDisposable($domain);
            $cached = DomainCache::updateOrCreate(['domain' => $domain], [
                'is_disposable' => $isDisposable,
                'has_mx' => $isDisposable ? false : $this->dns->hasMx($domain),
                'checked_at' => now(),
            ]);
        }

        return $this->domains[$domain] = $cached;
    }

    public function isDisposable(string $domain): bool
    {
        if ($this->disposable === null) {
            $lines = file(config('mailpilot.disposable_domains_file'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $this->disposable = array_fill_keys(
                array_map('strtolower', array_filter(array_map('trim', $lines), fn ($l) => $l !== '' && $l[0] !== '#')),
                true
            );
        }

        // Couvre aussi les sous-domaines (ex. abc.mailinator.com).
        $parts = explode('.', $domain);
        while (count($parts) >= 2) {
            if (isset($this->disposable[implode('.', $parts)])) {
                return true;
            }
            array_shift($parts);
        }

        return false;
    }
}
