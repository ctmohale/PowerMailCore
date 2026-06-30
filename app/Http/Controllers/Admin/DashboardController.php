<?php

namespace App\Http\Controllers\Admin;

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
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'counts' => [
                'clients' => Client::count(),
                'domains' => Domain::count(),
                'accounts' => EmailAccount::count(),
                'activeAccounts' => EmailAccount::where('is_active', true)->count(),
                'inboxAccounts' => EmailAccount::where('inbox_enabled', true)->count(),
                'templates' => EmailTemplate::count(),
                'activeTemplates' => EmailTemplate::where('is_active', true)->count(),
                'apiKeys' => ApiKey::count(),
                'activeApiKeys' => ApiKey::where('is_active', true)->count(),
                'logs' => EmailLog::count(),
                'received' => ReceivedEmail::count(),
                'sent' => EmailLog::where('status', EmailLog::STATUS_SENT)->count(),
                'failed' => EmailLog::where('status', EmailLog::STATUS_FAILED)->count(),
                'pending' => EmailLog::where('status', EmailLog::STATUS_PENDING)->count(),
            ],
            'recentLogs' => EmailLog::query()
                ->with(['client', 'emailAccount', 'emailTemplate'])
                ->latest()
                ->limit(10)
                ->get(),
            'recentReceived' => ReceivedEmail::query()
                ->with(['client', 'emailAccount'])
                ->latest('received_at')
                ->latest()
                ->limit(8)
                ->get(),
            'deliveryTrend' => collect(range(6, 0))->map(function (int $daysAgo): array {
                $date = Carbon::today()->subDays($daysAgo);

                return [
                    'label' => $date->format('M j'),
                    'sent' => EmailLog::where('status', EmailLog::STATUS_SENT)->whereDate('created_at', $date)->count(),
                    'failed' => EmailLog::where('status', EmailLog::STATUS_FAILED)->whereDate('created_at', $date)->count(),
                    'received' => ReceivedEmail::whereDate('created_at', $date)->count(),
                ];
            }),
        ]);
    }
}
