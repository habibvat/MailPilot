<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    public const STATUSES = [
        'pending' => 'En attente',
        'processing' => 'En cours',
        'completed' => 'Terminé',
        'failed' => 'Échec',
    ];

    protected $fillable = [
        'filename', 'path', 'mapping', 'total_rows', 'imported', 'rejected',
        'duplicates', 'enriched', 'status', 'error_message',
    ];

    protected function casts(): array
    {
        return ['mapping' => 'array'];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
