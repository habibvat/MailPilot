<?php

namespace App\Livewire\Imports;

use App\Imports\ContactsImport;
use App\Models\Import;
use App\Services\ContactImporter;
use App\Services\ImportFileReader;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

#[Title('Nouvel import')]
class Create extends Component
{
    use WithFileUploads;

    public $file;

    public ?string $storedPath = null;

    public ?string $originalName = null;

    public array $headers = [];

    public array $sampleRows = [];

    public ?string $delimiter = null;

    public ?string $encoding = null;

    public bool $hasHeader = true;

    /** @var array<string,string> champ => index de colonne */
    public array $mapping = [];

    private const GUESSES = [
        'company_name' => ['raison', 'entreprise', 'societe', 'company', 'denomination', 'nom'],
        'email' => ['email', 'mail', 'courriel'],
        'phone' => ['tel', 'phone', 'mobile', 'portable', 'fixe', 'gsm'],
        'sector' => ['secteur', 'activite', 'sector', 'domaine', 'branche'],
        'wilaya' => ['wilaya', 'region', 'ville', 'city', 'province', 'state', 'etat', 'departement', 'county', 'pays', 'country'],
        'website' => ['site', 'web', 'url', 'internet'],
    ];

    public function mount(): void
    {
        $this->mapping = array_fill_keys(array_keys(ContactImporter::FIELDS), '');
    }

    public function updatedFile(ImportFileReader $reader): void
    {
        $this->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx,xls', 'max:51200'],
        ], [
            'file.extensions' => __('Formats acceptés : CSV, XLSX ou XLS.'),
            'file.max' => __('Le fichier ne doit pas dépasser 50 Mo.'),
        ]);

        $this->discardStoredFile();

        $extension = strtolower($this->file->getClientOriginalExtension());
        $this->originalName = $this->file->getClientOriginalName();
        $this->storedPath = $this->file->storeAs('imports', Str::uuid().'.'.$extension, 'local');
        $this->file = null;

        try {
            $preview = $reader->preview(Storage::disk('local')->path($this->storedPath));
        } catch (\Throwable $e) {
            $this->discardStoredFile();
            $this->addError('file', __('Impossible de lire ce fichier : :error', ['error' => $e->getMessage()]));

            return;
        }

        $this->headers = $preview['headers'];
        $this->sampleRows = $preview['rows'];
        $this->delimiter = $preview['delimiter'];
        $this->encoding = $preview['encoding'];
        $this->guessMapping();
    }

    public function updatedHasHeader(): void
    {
        $this->guessMapping();
    }

    public function start()
    {
        $this->validate([
            'storedPath' => ['required'],
            'mapping.email' => ['required', 'integer'],
        ], [
            'storedPath.required' => __('Choisissez d\'abord un fichier.'),
            'mapping.email.required' => __('La colonne Email est obligatoire.'),
        ]);

        $import = Import::create([
            'filename' => $this->originalName,
            'path' => $this->storedPath,
            'status' => 'pending',
            'mapping' => array_map(fn ($v) => $v === '' ? null : (int) $v, $this->mapping) + [
                '_has_header' => $this->hasHeader,
                '_delimiter' => $this->delimiter,
                '_encoding' => $this->encoding,
            ],
        ]);

        Excel::queueImport(new ContactsImport($import->id), $this->storedPath, 'local');

        session()->flash('notify', __('Import de « :file » lancé en arrière-plan.', ['file' => $this->originalName]));

        return $this->redirectRoute('imports.index', navigate: true);
    }

    public function resetFile(): void
    {
        $this->discardStoredFile();
        $this->reset(['headers', 'sampleRows', 'delimiter', 'encoding', 'originalName']);
        $this->mount();
    }

    /** Libellés des colonnes pour les listes déroulantes. */
    public function columnOptions(): array
    {
        $options = [];
        foreach ($this->headers as $index => $header) {
            $letter = $this->columnLetter($index);
            $options[$index] = $this->hasHeader && $header !== '' ? "$letter — $header" : "Colonne $letter".($header !== '' ? " (ex. $header)" : '');
        }

        return $options;
    }

    public function render()
    {
        return view('livewire.imports.create', [
            'fields' => ContactImporter::FIELDS,
            'columns' => $this->columnOptions(),
        ]);
    }

    private function guessMapping(): void
    {
        $this->mapping = array_fill_keys(array_keys(ContactImporter::FIELDS), '');
        if (! $this->hasHeader) {
            return;
        }

        $used = [];
        foreach (self::GUESSES as $field => $keywords) {
            foreach ($this->headers as $index => $header) {
                $normalized = Str::ascii(mb_strtolower($header));
                if (isset($used[$index])) {
                    continue;
                }
                foreach ($keywords as $keyword) {
                    if (str_contains($normalized, $keyword)) {
                        $this->mapping[$field] = (string) $index;
                        $used[$index] = true;

                        continue 3;
                    }
                }
            }
        }
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letter = chr(65 + $i % 26).$letter;
        }

        return $letter;
    }

    private function discardStoredFile(): void
    {
        if ($this->storedPath) {
            Storage::disk('local')->delete($this->storedPath);
            $this->storedPath = null;
        }
    }
}
