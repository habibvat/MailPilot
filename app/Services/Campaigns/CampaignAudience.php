<?php

namespace App\Services\Campaigns;

use App\Models\Contact;
use App\Support\ContactFilter;
use Illuminate\Database\Eloquent\Builder;

class CampaignAudience
{
    public const SEGMENT_KEYS = ['sector', 'wilaya', 'tag'];

    /** Contacts du segment, sans règle d'éligibilité. */
    public function segment(array $filters): Builder
    {
        return ContactFilter::apply(Contact::query(), array_intersect_key($filters, array_flip(self::SEGMENT_KEYS)));
    }

    /** Contacts du segment pouvant recevoir la campagne. */
    public function eligible(array $filters): Builder
    {
        return $this->segment($filters)->eligible();
    }
}
