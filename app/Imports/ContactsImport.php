<?php

namespace App\Imports;

use App\Models\Import;
use App\Services\ContactImporter;
use App\Services\VerificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;

/**
 * Import en tâche de fond, lu par blocs de lignes. Pour un classeur Excel, seule la première feuille est importée.
 */
class ContactsImport implements ShouldQueue, ToCollection, WithChunkReading, WithCustomCsvSettings, WithEvents, WithMultipleSheets, WithStartRow
{
    public function __construct(public int $importId) {}

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function startRow(): int
    {
        return ($this->import()->mapping['_has_header'] ?? true) ? 2 : 1;
    }

    public function collection(Collection $rows): void
    {
        $import = $this->import();
        app(ContactImporter::class)->importRows($import, $rows, $import->mapping ?? []);
    }

    public function chunkSize(): int
    {
        return config('mailpilot.import_chunk_size');
    }

    public function getCsvSettings(): array
    {
        $mapping = $this->import()->mapping ?? [];

        return [
            'delimiter' => $mapping['_delimiter'] ?? ';',
            'input_encoding' => $mapping['_encoding'] ?? 'UTF-8',
        ];
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function () {
                Import::whereKey($this->importId)->update(['status' => 'processing']);
            },
            AfterImport::class => function () {
                $import = $this->import();
                $import->update(['status' => 'completed']);
                Storage::disk('local')->delete($import->path);
                app(VerificationDispatcher::class)->dispatch($import->contacts()->where('verify_status', 'pending'));
            },
            ImportFailed::class => function (ImportFailed $event) {
                Import::whereKey($this->importId)->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($event->getException()->getMessage(), 0, 1000),
                ]);
            },
        ];
    }

    private function import(): Import
    {
        return Import::findOrFail($this->importId);
    }
}
