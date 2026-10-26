<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Contact extends Model
{
    public const VERIFY_STATUSES = [
        'pending' => 'En attente',
        'valid' => 'Valide',
        'risky' => 'À risque',
        'invalid' => 'Invalide',
    ];

    public const STATUSES = [
        'active' => 'Actif',
        'unsubscribed' => 'Désinscrit',
        'bounced' => 'Rebond',
        'excluded' => 'Exclu',
    ];

    protected $fillable = [
        'company_name', 'email', 'phone', 'sector', 'wilaya', 'website', 'tags',
        'verify_status', 'verify_reason', 'verified_at', 'status',
        'last_contacted_at', 'replied_at', 'import_id',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'verified_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)),
        );
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class);
    }

    public function domain(): string
    {
        return substr(strrchr($this->email, '@') ?: '', 1);
    }

    /**
     * Contacts pouvant recevoir un email : valides, actifs, hors liste noire
     * et non contactés pendant la période de repos.
     */
    public function scopeEligible(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->where("$table.verify_status", 'valid')
            ->where("$table.status", 'active')
            ->whereNotExists(function ($sub) use ($table) {
                $sub->selectRaw('1')->from('suppressions')->whereColumn('suppressions.email', "$table.email");
            })
            ->where(function ($q) use ($table) {
                $q->whereNull("$table.last_contacted_at")
                    ->orWhere("$table.last_contacted_at", '<', now()->subDays(config('mailpilot.recontact_days')));
            });
    }

    /**
     * Liste des tags utilisés, triés.
     *
     * @return list<string>
     */
    public static function distinctTags(): array
    {
        $tags = match (DB::connection()->getDriverName()) {
            'sqlite' => DB::select('select distinct json_each.value as tag from contacts, json_each(contacts.tags) where contacts.tags is not null'),
            'mysql', 'mariadb' => DB::select("select distinct jt.tag from contacts, json_table(contacts.tags, '$[*]' columns (tag varchar(255) path '$')) jt"),
            default => static::whereNotNull('tags')->pluck('tags')->flatten()->unique()->map(fn ($tag) => (object) ['tag' => $tag])->all(),
        };

        $tags = array_values(array_unique(array_map(fn ($row) => (string) $row->tag, $tags)));
        sort($tags, SORT_NATURAL | SORT_FLAG_CASE);

        return $tags;
    }

    /**
     * @return list<string>
     */
    public static function distinctValues(string $column): array
    {
        return static::query()->whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column)->all();
    }
}
