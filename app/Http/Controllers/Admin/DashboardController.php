<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ReceivedEmail;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ScopesTenantData;

    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'isAdmin' => $this->isAdmin(),
            'counts' => [
                'clients' => $this->isAdmin() ? Client::count() : (int) filled($this->currentClientId()),
                'domains' => $this->scopeClient(Domain::query())->count(),
                'accounts' => $this->scopeEmailAccounts(EmailAccount::query())->count(),
                'activeAccounts' => $this->scopeEmailAccounts(EmailAccount::query())->where('is_active', true)->count(),
                'inboxAccounts' => $this->scopeEmailAccounts(EmailAccount::query())->where('inbox_enabled', true)->count(),
                'templates' => $this->scopeClient(EmailTemplate::query())->count(),
                'activeTemplates' => $this->scopeClient(EmailTemplate::query())->where('is_active', true)->count(),
                'apiKeys' => $this->scopeClient(ApiKey::query())->count(),
                'activeApiKeys' => $this->scopeClient(ApiKey::query())->where('is_active', true)->count(),
                'logs' => $this->scopeEmailAccountData(EmailLog::query())->count(),
                'received' => $this->scopeEmailAccountData(ReceivedEmail::query())->count(),
                'sent' => $this->scopeEmailAccountData(EmailLog::query())->where('status', EmailLog::STATUS_SENT)->count(),
                'failed' => $this->scopeEmailAccountData(EmailLog::query())->where('status', EmailLog::STATUS_FAILED)->count(),
                'pending' => $this->scopeEmailAccountData(EmailLog::query())->where('status', EmailLog::STATUS_PENDING)->count(),
            ],
            'recentLogs' => $this->scopeEmailAccountData(EmailLog::query())
                ->with(['client', 'emailAccount', 'emailTemplate'])
                ->latest()
                ->limit(10)
                ->get(),
            'recentReceived' => $this->scopeEmailAccountData(ReceivedEmail::query())
                ->with(['client', 'emailAccount'])
                ->latest('received_at')
                ->latest()
                ->limit(8)
                ->get(),
            'deliveryTrend' => collect(range(6, 0))->map(function (int $daysAgo): array {
                $date = Carbon::today()->subDays($daysAgo);

                return [
                    'label' => $date->format('M j'),
                    'sent' => $this->scopeEmailAccountData(EmailLog::query())->where('status', EmailLog::STATUS_SENT)->whereDate('created_at', $date)->count(),
                    'failed' => $this->scopeEmailAccountData(EmailLog::query())->where('status', EmailLog::STATUS_FAILED)->whereDate('created_at', $date)->count(),
                    'received' => $this->scopeEmailAccountData(ReceivedEmail::query())->whereDate('created_at', $date)->count(),
                ];
            }),
        ]);
    }
}
