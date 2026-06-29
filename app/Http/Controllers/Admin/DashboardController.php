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
                'templates' => EmailTemplate::count(),
                'apiKeys' => ApiKey::count(),
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
        ]);
    }
}
