<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\View\View;

class EmailLogController extends Controller
{
    use ScopesTenantData;

    public function index(): View
    {
        return view('admin.email-logs.index');
    }

    public function show(EmailLog $emailLog): View
    {
        $this->abortUnlessEmailAccountAllowed($emailLog->client_id, $emailLog->email_account_id);

        return view('admin.email-logs.show', [
            'log' => $emailLog->load(['client', 'domain', 'emailTemplate', 'apiKey', 'marketingContact']),
        ]);
    }
}
