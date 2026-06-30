<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailLogController extends Controller
{
    public function index(Request $request): View
    {
        $statuses = [
            EmailLog::STATUS_PENDING,
            EmailLog::STATUS_SENT,
            EmailLog::STATUS_FAILED,
            EmailLog::STATUS_OPENED,
            EmailLog::STATUS_CLICKED,
        ];

        $query = EmailLog::query()
            ->with(['client', 'emailAccount', 'emailTemplate', 'apiKey'])
            ->latest();

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('status') && in_array($request->input('status'), $statuses, true)) {
            $query->where('status', $request->input('status'));
        }

        return view('admin.email-logs.index', [
            'clients' => Client::orderBy('name')->get(),
            'statuses' => $statuses,
            'logs' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function show(EmailLog $emailLog): View
    {
        return view('admin.email-logs.show', [
            'log' => $emailLog->load(['client', 'domain', 'emailAccount', 'emailTemplate', 'apiKey']),
        ]);
    }
}
