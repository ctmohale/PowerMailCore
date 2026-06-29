<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EmailAccount;
use App\Models\ReceivedEmail;
use App\Services\InboxSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class InboxController extends Controller
{
    public function index(Request $request): View
    {
        $query = ReceivedEmail::query()
            ->with(['client', 'emailAccount'])
            ->latest('received_at')
            ->latest();

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('email_account_id')) {
            $query->where('email_account_id', $request->integer('email_account_id'));
        }

        return view('admin.inbox.index', [
            'clients' => Client::orderBy('name')->get(),
            'accounts' => EmailAccount::query()
                ->with('client')
                ->where('inbox_enabled', true)
                ->orderBy('email')
                ->get(),
            'messages' => $query->paginate(25)->withQueryString(),
            'imapEnabled' => function_exists('imap_open'),
        ]);
    }

    public function sync(Request $request, InboxSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'email_account_id' => ['required', 'exists:email_accounts,id'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $account = EmailAccount::findOrFail($validated['email_account_id']);

        try {
            $result = $syncService->syncAccount($account, (int) ($validated['limit'] ?? 25));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['inbox' => $exception->getMessage()]);
        }

        return back()->with(
            'success',
            "Inbox synced. Imported {$result['imported']} new message(s), skipped {$result['skipped']} existing message(s).",
        );
    }

    public function syncAll(Request $request, InboxSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $accounts = EmailAccount::query()
            ->where('inbox_enabled', true)
            ->orderBy('email')
            ->get();

        if ($accounts->isEmpty()) {
            return back()->withErrors(['inbox' => 'No inbox-enabled email accounts found.']);
        }

        $imported = 0;
        $skipped = 0;
        $failures = [];

        foreach ($accounts as $account) {
            try {
                $result = $syncService->syncAccount($account, (int) ($validated['limit'] ?? 25));
                $imported += $result['imported'];
                $skipped += $result['skipped'];
            } catch (RuntimeException $exception) {
                $failures[] = $account->email.': '.$exception->getMessage();
            }
        }

        $redirect = back()->with(
            'success',
            "All inboxes synced. Imported {$imported} new message(s), skipped {$skipped} existing message(s).",
        );

        if ($failures !== []) {
            return $redirect->withErrors(['inbox' => 'Some accounts failed. '.implode(' ', $failures)]);
        }

        return $redirect;
    }

    public function show(ReceivedEmail $receivedEmail): View
    {
        return view('admin.inbox.show', [
            'message' => $receivedEmail->load(['client', 'domain', 'emailAccount']),
        ]);
    }
}
