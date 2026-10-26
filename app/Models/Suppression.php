<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suppression extends Model
{
    public const REASONS = [
        'unsubscribe' => 'Désinscription',
        'bounce' => 'Rebond',
        'manual' => 'Manuel',
    ];

    protected $fillable = ['email', 'reason'];

    /**
     * Ajoute une adresse à la liste noire (sans écraser une raison existante).
     */
    public static function add(string $email, string $reason): self
    {
        return static::firstOrCreate(['email' => mb_strtolower(trim($email))], ['reason' => $reason]);
    }

    public static function contains(string $email): bool
    {
        return static::where('email', mb_strtolower(trim($email)))->exists();
    }
}
