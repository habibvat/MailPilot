<?php

namespace App\Services\Dns;

use App\Contracts\DnsResolver;

class NativeDnsResolver implements DnsResolver
{
    public function hasMx(string $domain): bool
    {
        $records = @dns_get_record($domain.'.', DNS_MX);

        if (is_array($records)) {
            return $records !== [];
        }

        // dns_get_record échoue sur certains résolveurs : on retente avec checkdnsrr.
        return checkdnsrr($domain.'.', 'MX');
    }
}
