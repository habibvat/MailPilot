<?php

namespace App\Livewire\Campaigns;

use App\Models\Campaign;
use App\Models\Contact;
use App\Livewire\Settings;
use App\Models\Setting;
use App\Models\SmtpAccount;
use App\Models\Template;
use App\Services\Campaigns\CampaignAudience;
use App\Services\Campaigns\CampaignManager;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Assistant en 3 étapes : segment → template → planning et quotas.
 */
class Wizard extends Component
{
    public ?Campaign $campaign = null;

    public int $step = 1;

    public string $name = '';

    public array $sectors = [];

    public array $wilayas = [];

    public array $tags = [];

    public ?int $template_id = null;

    public ?int $smtp_account_id = null;

    public int $hourly_limit = 10;

    public int $daily_limit = 30;

    public array $send_days = [];

    public string $send_start = '09:00';

    public string $send_end = '17:00';

    public int $min_delay = 60;

    public int $max_delay = 180;

    public function mount(?Campaign $campaign = null)
    {
        if ($campaign?->exists) {
            if ($campaign->status !== 'draft') {
                return $this->redirectRoute('campaigns.show', $campaign, navigate: true);
            }
            $this->campaign = $campaign;
            $filters = $campaign->segment_filters ?? [];
            $this->fill([
                'name' => $campaign->name,
                'sectors' => $filters['sector'] ?? [],
                'wilayas' => $filters['wilaya'] ?? [],
                'tags' => $filters['tag'] ?? [],
                'template_id' => $campaign->template_id,
                'smtp_account_id' => $campaign->smtp_account_id,
                'hourly_limit' => $campaign->hourly_limit,
                'daily_limit' => $campaign->daily_limit,
                'send_days' => $campaign->send_days ?? [],
                'send_start' => $campaign->send_start,
                'send_end' => $campaign->send_end,
                'min_delay' => $campaign->min_delay,
                'max_delay' => $campaign->max_delay,
            ]);

            return null;
        }

        $this->hourly_limit = (int) Setting::get('default_hourly_limit');
        $this->daily_limit = (int) Setting::get('default_daily_limit');
        $this->min_delay = (int) Setting::get('default_min_delay');
        $this->max_delay = (int) Setting::get('default_max_delay');
        $this->send_days = Settings::days(Setting::get('default_send_days'));
        $this->template_id = Template::value('id');
        $this->smtp_account_id = SmtpAccount::value('id');
        $this->updatedSmtpAccountId();

        return null;
    }

    public function updatedSmtpAccountId(): void
    {
        $account = SmtpAccount::find($this->smtp_account_id);
        if ($account) {
            $this->hourly_limit = min($this->hourly_limit, $account->hourly_limit);
            $this->daily_limit = min($this->daily_limit, $account->daily_limit);
        }
    }

    public function next(): void
    {
        $this->validateStep($this->step);
        $this->step = min(3, $this->step + 1);
    }

    public function previous(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function goTo(int $step): void
    {
        for ($i = 1; $i < $step; $i++) {
            $this->validateStep($i);
        }
        $this->step = $step;
    }

    public function save(bool $start = false)
    {
        foreach ([1, 2, 3] as $step) {
            try {
                $this->validateStep($step);
            } catch (ValidationException $e) {
                $this->step = $step;
                throw $e;
            }
        }

        $data = [
            'name' => $this->name,
            'segment_filters' => array_filter([
                'sector' => array_values($this->sectors),
                'wilaya' => array_values($this->wilayas),
                'tag' => array_values($this->tags),
            ]),
            'template_id' => $this->template_id,
            'smtp_account_id' => $this->smtp_account_id,
            'hourly_limit' => $this->hourly_limit,
            'daily_limit' => $this->daily_limit,
            'send_days' => array_values(array_map('intval', $this->send_days)),
            'send_start' => $this->send_start,
            'send_end' => $this->send_end,
            'min_delay' => $this->min_delay,
            'max_delay' => $this->max_delay,
        ];

        $campaign = $this->campaign ? tap($this->campaign)->update($data) : Campaign::create($data + ['status' => 'draft']);

        if ($start) {
            try {
                $count = app(CampaignManager::class)->start($campaign->fresh());
                session()->flash('notify', __('Campagne démarrée : :count contact(s) en file d\'envoi.', ['count' => $count]));
            } catch (\RuntimeException $e) {
                $this->campaign = $campaign;
                $this->dispatch('notify', type: 'error', message: $e->getMessage());

                return null;
            }
        } else {
            session()->flash('notify', __('Brouillon enregistré.'));
        }

        return $this->redirectRoute('campaigns.show', $campaign, navigate: true);
    }

    public function render(CampaignAudience $audience)
    {
        $filters = ['sector' => $this->sectors, 'wilaya' => $this->wilayas, 'tag' => $this->tags];
        $account = SmtpAccount::find($this->smtp_account_id);

        return view('livewire.campaigns.wizard', [
            'segmentCount' => $audience->segment($filters)->count(),
            'eligibleCount' => $audience->eligible($filters)->count(),
            'sectorOptions' => Contact::distinctValues('sector'),
            'wilayaOptions' => Contact::distinctValues('wilaya'),
            'tagOptions' => Contact::distinctTags(),
            'templates' => Template::orderBy('name')->get(['id', 'name', 'subject']),
            'accounts' => SmtpAccount::orderBy('label')->get(),
            'account' => $account,
            'template' => Template::find($this->template_id),
        ])->title($this->campaign ? __('Modifier la campagne') : __('Nouvelle campagne'));
    }

    private function validateStep(int $step): void
    {
        $rules = match ($step) {
            1 => ['name' => ['required', 'string', 'max:255']],
            2 => [
                'template_id' => ['required', 'exists:templates,id'],
                'smtp_account_id' => ['required', 'exists:smtp_accounts,id'],
            ],
            3 => [
                'hourly_limit' => ['required', 'integer', 'min:1', 'max:10000'],
                'daily_limit' => ['required', 'integer', 'min:1', 'max:100000'],
                'send_days' => ['required', 'array', 'min:1'],
                'send_days.*' => ['integer', 'between:1,7'],
                'send_start' => ['required', 'date_format:H:i'],
                'send_end' => ['required', 'date_format:H:i', 'after:send_start'],
                'min_delay' => ['required', 'integer', 'min:0', 'max:86400'],
                'max_delay' => ['required', 'integer', 'gte:min_delay', 'max:86400'],
            ],
        };

        $this->validate($rules, [
            'template_id.required' => __('Choisissez un template.'),
            'smtp_account_id.required' => __('Choisissez un compte SMTP.'),
            'send_days.required' => __('Choisissez au moins un jour d\'envoi.'),
            'send_end.after' => __('L\'heure de fin doit être après l\'heure de début.'),
            'max_delay.gte' => __('Le délai maximum doit être supérieur ou égal au délai minimum.'),
        ], [
            'name' => __('nom'), 'hourly_limit' => __('quota horaire'), 'daily_limit' => __('quota journalier'),
            'send_start' => __('heure de début'), 'send_end' => __('heure de fin'), 'min_delay' => __('délai minimum'), 'max_delay' => __('délai maximum'),
        ]);
    }
}
