<?php

namespace App\Livewire\SmtpAccounts;

use App\Models\SmtpAccount;
use App\Services\Campaigns\QuotaCalculator;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Comptes SMTP')]
class Index extends Component
{
    public function delete(int $id): void
    {
        $account = SmtpAccount::withCount(['campaigns' => fn ($q) => $q->whereIn('status', ['running', 'paused'])])->findOrFail($id);
        if ($account->campaigns_count > 0) {
            $this->dispatch('notify', type: 'error', message: __('Ce compte est utilisé par une campagne en cours ou en pause.'));

            return;
        }

        $account->delete();
        $this->dispatch('notify', message: __('Compte supprimé.'));
    }

    public function render(QuotaCalculator $quotas)
    {
        return view('livewire.smtp.index', [
            'accounts' => SmtpAccount::orderBy('label')->get()->each(function (SmtpAccount $account) use ($quotas) {
                $account->sent_today = $quotas->sentTodayForAccount($account->id, now());
            }),
        ]);
    }
}
