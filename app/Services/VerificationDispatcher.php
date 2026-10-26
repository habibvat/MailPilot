<?php

namespace App\Services;

use App\Jobs\VerifyContactsJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class VerificationDispatcher
{
    /**
     * Découpe les contacts de la requête en lots et place un job de vérification par lot.
     *
     * @return int nombre de contacts envoyés en vérification
     */
    public function dispatch(Builder|Relation $query): int
    {
        $total = 0;
        $query->select('contacts.id')->orderBy('contacts.id')
            ->chunkById(config('mailpilot.verify_batch_size'), function ($contacts) use (&$total) {
                VerifyContactsJob::dispatch($contacts->pluck('id')->all());
                $total += $contacts->count();
            }, 'contacts.id', 'id');

        return $total;
    }
}
