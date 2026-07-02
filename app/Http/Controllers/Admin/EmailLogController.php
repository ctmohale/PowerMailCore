<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\MarketingContact;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailLogController extends Controller
{
    use ScopesTenantData;

    public function index(Request $request): View
    {
        $statuses = [
            EmailLog::STATUS_PENDING,
            EmailLog::STATUS_SENT,
            EmailLog::STATUS_FAILED,
            EmailLog::STATUS_OPENED,
            EmailLog::STATUS_CLICKED,
        ];

        $query = $this->scopeEmailAccountData(EmailLog::query())
            ->with(['client', 'emailTemplate', 'apiKey', 'marketingContact'])
            ->latest();

        if ($this->isAdmin() && $request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('status') && in_array($request->input('status'), $statuses, true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('opened')) {
            match ($request->input('opened')) {
                'opened' => $query->whereNotNull('opened_at'),
                'not_opened' => $query->whereNull('opened_at'),
                default => null,
            };
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));

            $query->where(function ($query) use ($search): void {
                $query->where('from_email', 'like', "%{$search}%")
                    ->orWhere('to_email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('provider_message_id', 'like', "%{$search}%")
                    ->orWhereHas('marketingContact', function ($query) use ($search): void {
                        $query->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('company', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('contact_id')) {
            $contact = $this->scopeClient(MarketingContact::query())->find($request->integer('contact_id'));

            $query->where('marketing_contact_id', $contact?->id ?? 0);
        }

        return view('admin.email-logs.index', [
            'clients' => $this->clientsForUser(),
            'statuses' => $statuses,
            'logs' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function show(EmailLog $emailLog): View
    {
        $this->abortUnlessEmailAccountAllowed($emailLog->client_id, $emailLog->email_account_id);

        return view('admin.email-logs.show', [
            'log' => $emailLog->load(['client', 'domain', 'emailTemplate', 'apiKey', 'marketingContact']),
        ]);
    }
}
