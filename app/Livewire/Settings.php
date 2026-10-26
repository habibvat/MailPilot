<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Models\Suppression;
use App\Support\EmailParser;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Paramètres')]
class Settings extends Component
{
    use WithFileUploads;
    use WithPagination;

    public array $legal = ['legal_company' => '', 'legal_address' => '', 'legal_phone' => ''];

    public array $defaults = [
        'default_hourly_limit' => 10,
        'default_daily_limit' => 30,
        'default_min_delay' => 60,
        'default_max_delay' => 180,
    ];

    /** @var list<int> */
    public array $sendDays = [];

    public bool $algerianRegions = false;

    public bool $restorePhoneZero = false;

    public string $newEmail = '';

    public string $newReason = 'manual';

    public string $bulkEmails = '';

    public $suppressionFile;

    public string $search = '';

    public string $reasonFilter = '';

    public function mount(): void
    {
        foreach (array_keys($this->legal) as $key) {
            $this->legal[$key] = (string) Setting::get($key);
        }
        foreach (array_keys($this->defaults) as $key) {
            $this->defaults[$key] = (int) Setting::get($key);
        }
        $this->sendDays = self::days(Setting::get('default_send_days'));
        $this->algerianRegions = Setting::get('import_algerian_regions') === '1';
        $this->restorePhoneZero = Setting::get('import_restore_phone_zero') === '1';
    }

    /**
     * @return list<int>
     */
    public static function days(?string $value): array
    {
        return array_values(array_map('intval', array_filter(explode(',', (string) $value))));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedReasonFilter(): void
    {
        $this->resetPage();
    }

    public function saveLegal(): void
    {
        $this->validate([
            'legal.legal_company' => ['required', 'string', 'max:255'],
            'legal.legal_address' => ['required', 'string', 'max:500'],
            'legal.legal_phone' => ['required', 'string', 'max:50'],
        ], [], ['legal.legal_company' => __('raison sociale'), 'legal.legal_address' => __('adresse'), 'legal.legal_phone' => __('téléphone')]);

        foreach ($this->legal as $key => $value) {
            Setting::put($key, trim($value));
        }
        $this->dispatch('notify', message: __('Identité légale enregistrée.'));
    }

    public function saveDefaults(): void
    {
        $this->validate([
            'defaults.default_hourly_limit' => ['required', 'integer', 'min:1'],
            'defaults.default_daily_limit' => ['required', 'integer', 'min:1'],
            'defaults.default_min_delay' => ['required', 'integer', 'min:0'],
            'defaults.default_max_delay' => ['required', 'integer', 'gte:defaults.default_min_delay'],
        ], ['defaults.default_max_delay.gte' => __('Le délai maximum doit être supérieur ou égal au délai minimum.')]);

        foreach ($this->defaults as $key => $value) {
            Setting::put($key, (string) $value);
        }
        $this->dispatch('notify', message: __('Valeurs par défaut enregistrées.'));
    }

    public function saveRegional(): void
    {
        $this->validate([
            'sendDays' => ['required', 'array', 'min:1'],
            'sendDays.*' => ['integer', 'between:1,7'],
        ], ['sendDays.required' => __('Choisissez au moins un jour d\'envoi.')]);

        $days = array_map('intval', $this->sendDays);
        sort($days);
        Setting::put('default_send_days', implode(',', array_unique($days)));
        Setting::put('import_algerian_regions', $this->algerianRegions ? '1' : '0');
        Setting::put('import_restore_phone_zero', $this->restorePhoneZero ? '1' : '0');
        $this->dispatch('notify', message: __('Préférences régionales enregistrées.'));
    }

    public function addSuppression(): void
    {
        $this->validate([
            'newEmail' => ['required', 'email'],
            'newReason' => ['required', 'in:'.implode(',', array_keys(Suppression::REASONS))],
        ], [], ['newEmail' => 'email']);

        Suppression::add($this->newEmail, $this->newReason);
        $this->newEmail = '';
        $this->dispatch('notify', message: __('Adresse ajoutée à la liste noire.'));
    }

    public function importSuppressions(): void
    {
        $this->validate(['suppressionFile' => ['nullable', 'file', 'extensions:txt,csv', 'max:20480']]);

        $content = $this->bulkEmails;
        if ($this->suppressionFile) {
            $content .= "\n".file_get_contents($this->suppressionFile->getRealPath());
        }

        preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $content, $matches);
        $emails = array_values(array_unique(array_filter(array_map(fn ($e) => EmailParser::clean($e), $matches[0]), fn ($e) => EmailParser::isValid($e))));

        $added = 0;
        $now = now();
        foreach (array_chunk($emails, 500) as $chunk) {
            $added += \DB::table('suppressions')->insertOrIgnore(
                array_map(fn ($email) => ['email' => $email, 'reason' => 'manual', 'created_at' => $now, 'updated_at' => $now], $chunk)
            );
        }

        $this->reset(['bulkEmails', 'suppressionFile']);
        $this->dispatch('notify', message: $emails === [] ? __('Aucune adresse valide trouvée.') : __(':added adresse(s) ajoutée(s) (:existing déjà présente(s)).', ['added' => $added, 'existing' => count($emails) - $added]));
    }

    public function deleteSuppression(int $id): void
    {
        Suppression::whereKey($id)->delete();
        $this->dispatch('notify', message: __('Adresse retirée de la liste noire. Réactivez le contact depuis la page Contacts si besoin.'));
    }

    public function render()
    {
        $suppressions = Suppression::query()
            ->when($this->search !== '', fn ($q) => $q->where('email', 'like', '%'.mb_strtolower($this->search).'%'))
            ->when($this->reasonFilter !== '', fn ($q) => $q->where('reason', $this->reasonFilter))
            ->latest('id')->paginate(20);

        return view('livewire.settings', [
            'suppressions' => $suppressions,
            'counts' => Suppression::selectRaw('reason, count(*) as total')->groupBy('reason')->pluck('total', 'reason')->all(),
        ]);
    }
}
