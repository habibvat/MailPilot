<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Filtres partagés entre le tableau des contacts, l'export CSV et les segments de campagne.
 * Chaque filtre accepte une valeur simple ou une liste de valeurs.
 */
final class ContactFilter
{
    public const KEYS = ['search', 'sector', 'wilaya', 'verify_status', 'status', 'tag'];

    public static function apply(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where('company_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        foreach (['sector', 'wilaya', 'verify_status', 'status'] as $column) {
            $values = self::values($filters[$column] ?? null);
            if ($values !== []) {
                $query->whereIn($column, $values);
            }
        }

        $tags = self::values($filters['tag'] ?? null);
        if ($tags !== []) {
            $query->where(function (Builder $q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public static function values(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $values), fn ($v) => $v !== ''));
    }
}
