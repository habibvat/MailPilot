<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'legal_company' => '',
        'legal_address' => '',
        'legal_phone' => '',
        'default_hourly_limit' => '10',
        'default_daily_limit' => '30',
        'default_min_delay' => '60',
        'default_max_delay' => '180',
        'default_send_days' => '1,2,3,4,5',
        // Règles propres à l'Algérie, désactivées par défaut pour une base internationale.
        'import_algerian_regions' => '0',
        'import_restore_phone_zero' => '0',
    ];

    /** @var array<string,string|null>|null */
    protected static ?array $loaded = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        static::$loaded ??= static::query()->pluck('value', 'key')->all();

        return static::$loaded[$key] ?? $default ?? (static::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::$loaded = null;
    }

    public static function flushCache(): void
    {
        static::$loaded = null;
    }
}
