<?php

namespace App\Livewire;

use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Services\Campaigns\CampaignStats;
use App\Services\Campaigns\QuotaCalculator;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tableau de bord')]
class Dashboard extends Component
{
    public function render(CampaignStats $stats, QuotaCalculator $quotas)
    {
        $now = now();
        $startOfDay = $now->copy()->setTimezone(config('mailpilot.timezone'))->startOfDay();

        $verify = Contact::selectRaw('verify_status, count(*) as total')->groupBy('verify_status')->pluck('total', 'verify_status')->all();
        $status = Contact::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        $accounts = SmtpAccount::orderBy('label')->get()->each(function (SmtpAccount $account) use ($quotas, $now) {
            $account->used_today = $quotas->sentTodayForAccount($account->id, $now);
        });

        $campaigns = Campaign::whereIn('status', ['running', 'paused', 'stopped', 'completed'])
            ->with('smtpAccount:id,label')->latest('started_at')->limit(12)->get();

        return view('livewire.dashboard', [
            'totalContacts' => array_sum($verify),
            'verify' => $verify,
            'status' => $status,
            'sentToday' => CampaignSend::where('status', 'sent')->where('sent_at', '>=', $startOfDay)->count(),
            'plannedToday' => CampaignSend::whereIn('status', ['dispatched', 'sending'])->count(),
            'dailyQuota' => (int) $accounts->sum('daily_limit'),
            'accounts' => $accounts,
            'campaigns' => $campaigns,
            'stats' => $stats->forMany($campaigns),
            'runningCount' => $campaigns->where('status', 'running')->count(),
        ]);
    }
}
