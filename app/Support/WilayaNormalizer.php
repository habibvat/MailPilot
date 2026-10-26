<?php

namespace App\Support;

use App\Models\Wilaya;
use Illuminate\Support\Str;

/**
 * Ramène les différentes graphies d'une wilaya (« ALGER », « 16 - Alger », « Bejaia », « Wilaya de Tizi-Ouzou »…)
 * vers son nom officiel.
 */
class WilayaNormalizer
{
    /** @var array<string,string>|null clé normalisée => nom officiel */
    private ?array $names = null;

    /** @var array<int,string> */
    private array $codes = [];

    public function normalize(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $this->load();

        $withoutPrefix = preg_replace('/^\s*wilaya\s+(de\s+|d[\'’]\s*)?/iu', '', $value);

        // « 16 », « 16 - Alger », « 06/Béjaïa » : le nom prime, sinon le code.
        if (preg_match('/^(\d{1,2})\s*[-–—:.\/)]?\s*(.*)$/u', trim($withoutPrefix), $m)) {
            if (trim($m[2]) !== '' && ($name = $this->lookup($m[2])) !== null) {
                return $name;
            }
            if (isset($this->codes[(int) $m[1]])) {
                return $this->codes[(int) $m[1]];
            }
        }

        return $this->lookup($withoutPrefix) ?? Str::title(mb_strtolower($value));
    }

    private function lookup(string $value): ?string
    {
        $key = self::key($value);

        return $key === '' ? null : ($this->names[$key] ?? null);
    }

    private function load(): void
    {
        if ($this->names !== null) {
            return;
        }

        $this->names = [];
        foreach (Wilaya::all() as $wilaya) {
            $this->codes[$wilaya->code] = $wilaya->name;
            foreach ([$wilaya->name, ...($wilaya->aliases ?? [])] as $variant) {
                $this->names[self::key($variant)] = $wilaya->name;
            }
        }
    }

    public static function key(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower($value)));
    }
}
