<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainCache extends Model
{
    protected $table = 'domain_cache';

    public $timestamps = false;

    protected $fillable = ['domain', 'has_mx', 'is_disposable', 'checked_at'];

    protected function casts(): array
    {
        return [
            'has_mx' => 'boolean',
            'is_disposable' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }
}
