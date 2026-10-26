<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignSend extends Model
{
    public const STATUSES = [
        'queued' => 'En file',
        'dispatched' => 'Planifié',
        'sending' => 'En cours d\'envoi',
        'sent' => 'Envoyé',
        'failed' => 'Échec',
        'bounced' => 'Rebond',
        'skipped' => 'Ignoré',
    ];

    public const STATUSES_KEYS = ['queued', 'dispatched', 'sending', 'sent', 'failed', 'bounced', 'skipped'];

    /** Statuts qui consomment du quota (un envoi a été planifié ou tenté). */
    public const QUOTA_STATUSES = ['dispatched', 'sending', 'sent', 'failed', 'bounced'];

    /** Statuts encore à traiter. */
    public const PENDING_STATUSES = ['queued', 'dispatched', 'sending'];

    protected $fillable = [
        'campaign_id', 'contact_id', 'status', 'error', 'message_id', 'dispatch_token',
        'attempts', 'scheduled_at', 'locked_at', 'sent_at', 'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'scheduled_at' => 'datetime',
            'locked_at' => 'datetime',
            'sent_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
