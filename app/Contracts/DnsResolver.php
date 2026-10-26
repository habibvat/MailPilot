<?php

namespace App\Contracts;

interface DnsResolver
{
    /**
     * Indique si le domaine publie au moins un enregistrement MX.
     */
    public function hasMx(string $domain): bool;
}
