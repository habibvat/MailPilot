<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Lit l'en-tête et les premières lignes d'un fichier CSV / XLSX pour l'écran de mapping.
 */
class ImportFileReader
{
    public const DELIMITERS = [';', ',', "\t", '|'];

    /**
     * @return array{headers: list<string>, rows: list<list<string>>, delimiter: ?string, encoding: ?string}
     */
    public function preview(string $path, int $lines = 6): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['csv', 'txt'], true)
            ? $this->previewCsv($path, $lines)
            : $this->previewSpreadsheet($path, $lines);
    }

    private function previewCsv(string $path, int $lines): array
    {
        $sample = (string) file_get_contents($path, false, null, 0, 65536);
        $encoding = mb_check_encoding($this->trimIncompleteUtf8($sample), 'UTF-8') ? 'UTF-8' : 'Windows-1252';
        $delimiter = $this->detectDelimiter($sample);

        $handle = fopen($path, 'r');
        $data = [];
        while (count($data) < $lines && ($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $data[] = array_map(fn ($value) => $this->toUtf8((string) $value, $encoding), $row);
        }
        fclose($handle);

        if (isset($data[0][0])) {
            $data[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0][0]);
        }

        return $this->shape($data) + ['delimiter' => $delimiter, 'encoding' => $encoding];
    }

    private function previewSpreadsheet(string $path, int $lines): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class($lines) implements IReadFilter
        {
            public function __construct(private int $lines) {}

            // Signature sans types : imposée par l'interface IReadFilter de PhpSpreadsheet 1.x.
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= $this->lines;
            }
        });

        $sheet = $reader->load($path)->getSheet(0);
        $data = array_slice($sheet->toArray(null, true, false, false), 0, $lines);

        return $this->shape(array_map(fn ($row) => array_map(fn ($v) => trim((string) $v), $row), $data))
            + ['delimiter' => null, 'encoding' => null];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    private function shape(array $data): array
    {
        $width = max(array_map('count', $data ?: [[]]));
        $data = array_map(fn ($row) => array_pad(array_values($row), $width, ''), $data);

        return ['headers' => $data[0] ?? [], 'rows' => array_slice($data, 1)];
    }

    private function detectDelimiter(string $sample): string
    {
        $firstLine = strtok($sample, "\r\n") ?: '';
        $best = ';';
        $bestCount = 0;
        foreach (self::DELIMITERS as $delimiter) {
            $count = substr_count($firstLine, $delimiter);
            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function toUtf8(string $value, string $encoding): string
    {
        return trim($encoding === 'UTF-8' ? $value : mb_convert_encoding($value, 'UTF-8', $encoding));
    }

    /** Évite qu'un caractère multi-octets coupé en fin d'échantillon fausse la détection. */
    private function trimIncompleteUtf8(string $sample): string
    {
        return strlen($sample) < 65536 ? $sample : substr($sample, 0, strrpos($sample, "\n") ?: strlen($sample));
    }
}
