<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    public const STATUSES = [
        'draft' => 'Brouillon',
        'running' => 'En cours',
        'paused' => 'En pause',
        'completed' => 'Terminée',
        'stopped' => 'Arrêtée',
    ];

    public const DAYS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];

    protected $fillable = [
        'name', 'template_id', 'smtp_account_id', 'segment_filters', 'hourly_limit',
        'daily_limit', 'send_days', 'send_start', 'send_end', 'min_delay', 'max_delay',
        'status', 'stop_reason', 'consecutive_failures', 'next_send_at', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'segment_filters' => 'array',
            'send_days' => 'array',
            'hourly_limit' => 'integer',
            'daily_limit' => 'integer',
            'min_delay' => 'integer',
            'max_delay' => 'integer',
            'consecutive_failures' => 'integer',
            'next_send_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function smtpAccount(): BelongsTo
    {
        return $this->belongsTo(SmtpAccount::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * @return array<string,int> nombre d'envois par statut
     */
    public function sendCounts(): array
    {
        $counts = $this->sends()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        return array_map('intval', $counts + array_fill_keys(CampaignSend::STATUSES_KEYS, 0));
    }
}
