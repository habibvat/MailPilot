<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wilaya extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['code', 'name', 'aliases'];

    protected function casts(): array
    {
        return ['aliases' => 'array'];
    }
}
