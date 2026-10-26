<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmtpAccount extends Model
{
    public const ENCRYPTIONS = ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'Aucun'];

    protected $fillable = [
        'label', 'host', 'port', 'encryption', 'username', 'password',
        'from_name', 'from_email', 'hourly_limit', 'daily_limit',
        'imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_last_uid', 'imap_checked_at',
    ];

    protected $hidden = ['password', 'imap_password'];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'imap_port' => 'integer',
            'hourly_limit' => 'integer',
            'daily_limit' => 'integer',
            'password' => 'encrypted',
            'imap_password' => 'encrypted',
            'imap_last_uid' => 'integer',
            'imap_checked_at' => 'datetime',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function hasImap(): bool
    {
        return filled($this->imap_host) && filled($this->imap_username);
    }

    /**
     * Adresse qui reçoit les demandes de désinscription (mailto).
     */
    public function unsubscribeAddress(): string
    {
        return filter_var($this->imap_username, FILTER_VALIDATE_EMAIL) ? $this->imap_username : $this->from_email;
    }
}
