<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Import;
use App\Models\Setting;
use App\Models\Suppression;
use App\Support\EmailParser;
use App\Support\WilayaNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Nettoie et insère un lot de lignes d'un fichier importé.
 * Une cellule contenant plusieurs emails produit plusieurs contacts.
 */
class ContactImporter
{
    public const FIELDS = [
        'company_name' => 'Raison sociale',
        'email' => 'Email',
        'phone' => 'Téléphone',
        'sector' => 'Secteur',
        'wilaya' => 'Région',
        'website' => 'Site web',
    ];

    /** Champs qu'un nouvel import peut compléter sur un contact existant. */
    private const ENRICHABLE = ['company_name', 'phone', 'sector', 'wilaya', 'website'];

    private const SUPPRESSION_STATUS = [
        'bounce' => 'bounced',
        'unsubscribe' => 'unsubscribed',
        'manual' => 'excluded',
    ];

    public function __construct(private WilayaNormalizer $wilayas) {}

    /**
     * @param  iterable<array<int,mixed>>  $rows  lignes indexées par numéro de colonne
     * @param  array<string,int|string|null>  $mapping  champ => index de colonne
     * @return array{total:int, imported:int, rejected:int, duplicates:int}
     */
    public function importRows(Import $import, iterable $rows, array $mapping): array
    {
        $stats = ['total' => 0, 'imported' => 0, 'rejected' => 0, 'duplicates' => 0, 'enriched' => 0];
        $algerianRegions = Setting::get('import_algerian_regions') === '1';
        $restorePhoneZero = Setting::get('import_restore_phone_zero') === '1';
        $candidates = [];

        foreach ($rows as $row) {
            $row = $row instanceof Collection ? $row->all() : (array) $row;
            if ($this->isEmptyRow($row)) {
                continue;
            }
            $stats['total']++;

            $emails = EmailParser::split($this->cell($row, $mapping, 'email'));
            if ($emails === []) {
                $stats['rejected']++;

                continue;
            }

            $attributes = [
                'company_name' => $this->text($this->cell($row, $mapping, 'company_name')),
                'phone' => $this->phone($this->cell($row, $mapping, 'phone'), $restorePhoneZero),
                'sector' => $this->text($this->cell($row, $mapping, 'sector')),
                'wilaya' => $algerianRegions
                    ? $this->wilayas->normalize($this->cell($row, $mapping, 'wilaya'))
                    : $this->text($this->cell($row, $mapping, 'wilaya')),
                'website' => $this->website($this->cell($row, $mapping, 'website')),
            ];

            foreach ($emails as $email) {
                if (! EmailParser::isValid($email)) {
                    $stats['rejected']++;
                } elseif (isset($candidates[$email])) {
                    $candidates[$email] = $this->fillBlanks($candidates[$email], $attributes);
                    $stats['duplicates']++;
                } else {
                    $candidates[$email] = $attributes;
                }
            }
        }

        // Un email déjà en base n'est pas recréé, mais ses champs vides sont complétés
        // (ex. une liste d'emails seuls importée avant le fichier complet).
        foreach (array_chunk(array_keys($candidates), 500) as $emails) {
            $existing = DB::table('contacts')->whereIn('email', $emails)
                ->get(['id', 'email', ...self::ENRICHABLE]);

            foreach ($existing as $contact) {
                $current = array_intersect_key((array) $contact, array_flip(self::ENRICHABLE));
                $missing = array_diff_key(
                    array_filter($candidates[$contact->email], fn ($value) => $value !== null),
                    array_filter($current, fn ($value) => $value !== null && $value !== '')
                );

                if ($missing !== []) {
                    DB::table('contacts')->where('id', $contact->id)->update($missing + ['updated_at' => now()]);
                    $stats['enriched']++;
                }

                unset($candidates[$contact->email]);
                $stats['duplicates']++;
            }
        }

        $suppressed = [];
        foreach (array_chunk(array_keys($candidates), 500) as $emails) {
            $suppressed += Suppression::whereIn('email', $emails)->pluck('reason', 'email')->all();
        }

        $now = now();
        $records = [];
        foreach ($candidates as $email => $attributes) {
            $records[] = $attributes + [
                'email' => $email,
                'verify_status' => 'pending',
                'status' => self::SUPPRESSION_STATUS[$suppressed[$email] ?? ''] ?? 'active',
                'import_id' => $import->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($records, 100) as $chunk) {
            $inserted = DB::table('contacts')->insertOrIgnore($chunk);
            $stats['imported'] += $inserted;
            $stats['duplicates'] += count($chunk) - $inserted;
        }

        Import::whereKey($import->id)->update([
            'total_rows' => DB::raw('total_rows + '.$stats['total']),
            'imported' => DB::raw('imported + '.$stats['imported']),
            'rejected' => DB::raw('rejected + '.$stats['rejected']),
            'duplicates' => DB::raw('duplicates + '.$stats['duplicates']),
            'enriched' => DB::raw('enriched + '.$stats['enriched']),
            'updated_at' => $now,
        ]);

        return $stats;
    }

    /**
     * @param  array<string,?string>  $kept
     * @param  array<string,?string>  $other
     * @return array<string,?string>
     */
    private function fillBlanks(array $kept, array $other): array
    {
        foreach ($other as $field => $value) {
            $kept[$field] ??= $value;
        }

        return $kept;
    }

    private function cell(array $row, array $mapping, string $field): mixed
    {
        $index = $mapping[$field] ?? null;
        if ($index === null || $index === '') {
            return null;
        }

        return $row[(int) $index] ?? null;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function text(mixed $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function phone(mixed $value, bool $restoreZero): ?string
    {
        if (is_float($value) && floor($value) === $value) {
            $value = (string) (int) $value;
        }
        $phone = $this->text($value);
        // Excel supprime le 0 initial des numéros nationaux (ex. 550123456 → 0550123456), si l'option est activée.
        if ($restoreZero && $phone !== null && preg_match('/^[2-7]\d{8}$/', $phone)) {
            $phone = '0'.$phone;
        }

        return $phone;
    }

    private function website(mixed $value): ?string
    {
        $website = $this->text($value);

        return $website === null ? null : mb_strtolower($website);
    }
}
