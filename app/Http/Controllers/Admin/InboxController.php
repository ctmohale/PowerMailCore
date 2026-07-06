<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Models\ReceivedEmail;
use App\Models\User;
use App\Services\ImapMailboxClient;
use App\Services\InboxSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use RuntimeException;

class InboxController extends Controller
{
    use ScopesTenantData;

    public function index(Request $request): View
    {
        return view('admin.inbox.index', $this->inboxViewData($request));
    }

    public function poll(Request $request, InboxSyncService $syncService): JsonResponse
    {
        $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'email_account_id' => ['nullable', 'exists:email_accounts,id'],
            'mailbox' => ['nullable', 'string', 'max:30'],
            'opened' => ['nullable', 'in:all,opened,unopened'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sync' => ['nullable', 'boolean'],
        ]);

        $selectedMailboxType = $this->selectedMailboxType($request);
        $syncSummary = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        if ($request->boolean('sync', true)) {
            $syncSummary = $this->syncLatestForRequest($request, $syncService, $selectedMailboxType);
        }

        $data = $this->inboxViewData($request);
        $folderCounts = collect(['all' => $data['folderCounts']->sum()]);

        foreach (array_keys($data['mailboxTypes']) as $mailboxType) {
            if ($mailboxType !== 'all') {
                $folderCounts[$mailboxType] = (int) ($data['folderCounts'][$mailboxType] ?? 0);
            }
        }

        return response()->json([
            'rows_html' => view('admin.inbox.partials.table-body', $data)->render(),
            'meta_html' => view('admin.inbox.partials.meta', $data)->render(),
            'total' => $data['messages']->total(),
            'unopened_count' => $data['unopenedCount'],
            'junk_count' => $data['junkCount'],
            'folder_counts' => $folderCounts,
            'account_counts' => collect(['all' => $data['accounts']->sum('received_emails_count')])
                ->merge($data['accounts']->mapWithKeys(fn (EmailAccount $account): array => [
                    $account->id => (int) $account->received_emails_count,
                ])),
            'synced_at' => now()->format('H:i:s'),
            'sync_imported' => $syncSummary['imported'],
            'sync_skipped' => $syncSummary['skipped'],
            'sync_error' => implode(' ', $syncSummary['errors']),
        ]);
    }

    public function sync(Request $request, InboxSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'email_account_id' => ['required', 'exists:email_accounts,id'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
            'mailbox' => ['nullable', 'string', 'max:30'],
        ]);

        $account = $this->scopeEmailAccounts(EmailAccount::query())->findOrFail($validated['email_account_id']);
        $mailboxType = $this->selectedMailboxValue((string) ($validated['mailbox'] ?? ImapMailboxClient::MAILBOX_INBOX));

        try {
            $result = $mailboxType === 'all'
                ? $syncService->syncAccountAllMailboxes($account, (int) ($validated['limit'] ?? 10))
                : $syncService->syncAccountMailbox($account, $mailboxType, (int) ($validated['limit'] ?? 10));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['inbox' => $exception->getMessage()]);
        }

        $mailboxLabel = $this->mailboxLabel($mailboxType);

        return back()->with(
            'success',
            "{$mailboxLabel} synced. Imported {$result['imported']} new message(s), skipped {$result['skipped']} existing message(s).",
        );
    }

    public function syncAll(Request $request, InboxSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
            'mailbox' => ['nullable', 'string', 'max:30'],
        ]);
        $mailboxType = $this->selectedMailboxValue((string) ($validated['mailbox'] ?? ImapMailboxClient::MAILBOX_INBOX));

        $accountsQuery = $this->scopeEmailAccounts(EmailAccount::query())
            ->where('inbox_enabled', true)
            ->orderBy('email');

        if ($this->isAdmin() && ! empty($validated['client_id'])) {
            $accountsQuery->where('client_id', $validated['client_id']);
        }

        $accounts = $accountsQuery->get();

        if ($accounts->isEmpty()) {
            return back()->withErrors(['inbox' => 'No inbox-enabled email accounts found.']);
        }

        $imported = 0;
        $skipped = 0;
        $failures = [];

        foreach ($accounts as $account) {
            try {
                $result = $mailboxType === 'all'
                    ? $syncService->syncAccountAllMailboxes($account, (int) ($validated['limit'] ?? 10))
                    : $syncService->syncAccountMailbox($account, $mailboxType, (int) ($validated['limit'] ?? 10));
                $imported += $result['imported'];
                $skipped += $result['skipped'];
            } catch (RuntimeException $exception) {
                $failures[] = $account->email.': '.$exception->getMessage();
            }
        }

        $mailboxLabel = $this->mailboxLabel($mailboxType);

        $redirect = back()->with(
            'success',
            "All {$mailboxLabel} mailboxes synced. Imported {$imported} new message(s), skipped {$skipped} existing message(s).",
        );

        if ($failures !== []) {
            return $redirect->withErrors(['inbox' => 'Some accounts failed. '.implode(' ', $failures)]);
        }

        return $redirect;
    }

    public function syncOlder(Request $request, InboxSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'email_account_id' => ['nullable', 'exists:email_accounts,id'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
            'next_page' => ['nullable', 'integer', 'min:1'],
            'mailbox' => ['nullable', 'string', 'max:30'],
        ]);
        $mailboxType = $this->normalizeMailboxType((string) ($validated['mailbox'] ?? ImapMailboxClient::MAILBOX_INBOX));

        $accountsQuery = $this->scopeEmailAccounts(EmailAccount::query())
            ->where('inbox_enabled', true)
            ->orderBy('email');

        if ($this->isAdmin() && ! empty($validated['client_id'])) {
            $accountsQuery->where('client_id', $validated['client_id']);
        }

        if (! empty($validated['email_account_id'])) {
            $accountsQuery->whereKey($validated['email_account_id']);
        }

        $accounts = $accountsQuery->get();

        if ($accounts->isEmpty()) {
            return back()->withErrors(['inbox' => 'No inbox-enabled email accounts found.']);
        }

        $imported = 0;
        $skipped = 0;
        $failures = [];

        foreach ($accounts as $account) {
            try {
                $result = $syncService->syncOlderAccountMailbox($account, $mailboxType, (int) ($validated['limit'] ?? 10));
                $imported += $result['imported'];
                $skipped += $result['skipped'];
            } catch (RuntimeException $exception) {
                $failures[] = $account->email.': '.$exception->getMessage();
            }
        }

        $redirectParams = array_filter([
            'client_id' => $this->isAdmin() ? ($validated['client_id'] ?? null) : null,
            'email_account_id' => $validated['email_account_id'] ?? null,
            'mailbox' => $mailboxType,
        ], fn ($value): bool => filled($value));

        if ($imported > 0 && ! empty($validated['next_page'])) {
            $redirectParams['page'] = $validated['next_page'];
        }

        $redirect = redirect()
            ->route('inbox.index', $redirectParams)
            ->with(
                'success',
                $imported > 0
                    ? "Older {$this->mailboxLabel($mailboxType)} mail fetched. Imported {$imported} new message(s), skipped {$skipped} existing message(s)."
                    : "No older {$this->mailboxLabel($mailboxType)} mail found. Skipped {$skipped} existing message(s).",
            );

        if ($failures !== []) {
            return $redirect->withErrors(['inbox' => 'Some accounts failed. '.implode(' ', $failures)]);
        }

        return $redirect;
    }

    public function show(Request $request, ReceivedEmail $receivedEmail): View
    {
        $this->abortUnlessEmailAccountAllowed($receivedEmail->client_id, $receivedEmail->email_account_id);

        if (! $receivedEmail->opened_at) {
            $receivedEmail->forceFill([
                'opened_at' => now(),
                'seen' => true,
            ])->save();

            Cache::forget('layout.unopened_count.'.$request->user()->id);
        }

        $canSendEmails = $request->user()->canAccess(User::PERMISSION_SEND_EMAILS);
        $composeAccounts = collect();
        $templates = collect();
        $defaultTemplateId = null;
        $selectedComposeAccountId = null;

        if ($canSendEmails) {
            $composeAccounts = $this->scopeEmailAccounts(EmailAccount::query())
                ->with('client')
                ->where('client_id', $receivedEmail->client_id)
                ->where('is_active', true)
                ->orderBy('email')
                ->get();

            $templates = $this->scopeClient(EmailTemplate::query())
                ->with('client')
                ->where('client_id', $receivedEmail->client_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $defaultTemplateId = $templates->contains('id', $request->user()->default_email_template_id)
                ? $request->user()->default_email_template_id
                : null;
            $selectedComposeAccountId = $composeAccounts->contains('id', $receivedEmail->email_account_id)
                ? $receivedEmail->email_account_id
                : $composeAccounts->first()?->id;
        }

        $inboxQuery = $this->inboxQueryParameters($request);
        $prevMessage = $this->adjacentMessage($request, $receivedEmail, 'previous');
        $nextMessage = $this->adjacentMessage($request, $receivedEmail, 'next');

        return view('admin.inbox.show', [
            'message' => $receivedEmail->load(['client', 'domain', 'emailAccount']),
            'canSendEmails' => $canSendEmails,
            'composeAccounts' => $composeAccounts,
            'templates' => $templates,
            'defaultTemplateId' => $defaultTemplateId,
            'selectedComposeAccountId' => $selectedComposeAccountId,
            'selectedTemplateId' => $defaultTemplateId,
            'prevMessage' => $prevMessage,
            'nextMessage' => $nextMessage,
            'inboxQuery' => $inboxQuery,
            'prevMessageUrl' => $prevMessage ? route('inbox.show', [$prevMessage] + $inboxQuery) : null,
            'nextMessageUrl' => $nextMessage ? route('inbox.show', [$nextMessage] + $inboxQuery) : null,
            'inboxIndexUrl' => route('inbox.index', $inboxQuery),
        ]);
    }

    public function markOpened(Request $request, ReceivedEmail $receivedEmail): RedirectResponse
    {
        $this->abortUnlessEmailAccountAllowed($receivedEmail->client_id, $receivedEmail->email_account_id);

        $receivedEmail->forceFill([
            'opened_at' => $receivedEmail->opened_at ?: now(),
            'seen' => true,
        ])->save();

        Cache::forget('layout.unopened_count.'.$request->user()->id);

        return back()->with('success', 'Email marked as opened.');
    }

    public function markUnopened(Request $request, ReceivedEmail $receivedEmail): RedirectResponse
    {
        $this->abortUnlessEmailAccountAllowed($receivedEmail->client_id, $receivedEmail->email_account_id);

        $receivedEmail->forceFill([
            'opened_at' => null,
            'seen' => false,
        ])->save();

        Cache::forget('layout.unopened_count.'.$request->user()->id);

        return back()->with('success', 'Email marked as unopened.');
    }

    public function destroy(ReceivedEmail $receivedEmail): RedirectResponse
    {
        $this->abortUnlessEmailAccountAllowed($receivedEmail->client_id, $receivedEmail->email_account_id);

        $receivedEmail->delete();

        return back()->with('success', 'Email deleted from PowerMail inbox.');
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer'],
        ]);

        $ids = array_map('intval', $request->input('ids'));

        $deleted = $this->scopeEmailAccountData(ReceivedEmail::query())
            ->whereIn('id', $ids)
            ->delete();

        return back()->with('success', "Deleted {$deleted} email(s) from PowerMail inbox.");
    }

    /**
     * @return array<string, mixed>
     */
    private function inboxViewData(Request $request): array
    {
        $selectedMailboxType = $this->selectedMailboxType($request);
        $query = $this->messageQuery($request, $selectedMailboxType)
            ->with(['client', 'emailAccount'])
            ->latest('received_at')
            ->latest();
        $unopenedCount = (clone $query)->whereNull('opened_at')->count();

        $accountsQuery = $this->scopeEmailAccounts(EmailAccount::query())
            ->with('client')
            ->withCount(['receivedEmails' => function ($query) use ($selectedMailboxType): void {
                if ($selectedMailboxType !== 'all') {
                    $query->where('mailbox_type', $selectedMailboxType);
                }
            }])
            ->where('inbox_enabled', true)
            ->orderBy('email');

        $configurableAccountsQuery = $this->scopeEmailAccounts(EmailAccount::query())
            ->with('client')
            ->orderBy('email');

        if ($this->isAdmin() && $request->filled('client_id')) {
            $accountsQuery->where('client_id', $request->integer('client_id'));
            $configurableAccountsQuery->where('client_id', $request->integer('client_id'));
        }

        $folderCountsQuery = $this->messageQuery($request, 'all')
            ->selectRaw('mailbox_type, count(*) as aggregate')
            ->groupBy('mailbox_type');
        $canSendEmails = $request->user()->canAccess(User::PERMISSION_SEND_EMAILS);
        $composeAccounts = collect();
        $templates = collect();
        $defaultTemplateId = null;

        if ($canSendEmails) {
            $composeAccountsQuery = $this->scopeEmailAccounts(EmailAccount::query())
                ->with('client')
                ->where('is_active', true)
                ->orderBy('email');
            $templatesQuery = $this->scopeClient(EmailTemplate::query())
                ->with('client')
                ->where('is_active', true)
                ->orderBy('name');

            if ($this->isAdmin() && $request->filled('client_id')) {
                $composeAccountsQuery->where('client_id', $request->integer('client_id'));
                $templatesQuery->where('client_id', $request->integer('client_id'));
            }

            $composeAccounts = $composeAccountsQuery->get();
            $templates = $templatesQuery->get();
            $defaultTemplateId = $templates->contains('id', $request->user()->default_email_template_id)
                ? $request->user()->default_email_template_id
                : null;
        }

        $accounts = $accountsQuery->get();

        return [
            'clients' => $this->clientsForUser(),
            'accounts' => $accounts,
            'configurableAccounts' => $request->user()->canAccess(User::PERMISSION_MANAGE_ACCOUNTS)
                ? $configurableAccountsQuery->get()
                : collect(),
            'composeAccounts' => $composeAccounts,
            'templates' => $templates,
            'defaultTemplateId' => $defaultTemplateId,
            'canSendEmails' => $canSendEmails,
            'inboxQuery' => $this->inboxQueryParameters($request),
            'mailboxTypes' => ['all' => 'All mail'] + ImapMailboxClient::mailboxTypeOptions(),
            'selectedMailboxType' => $selectedMailboxType,
            'folderCounts' => $folderCountsQuery->pluck('aggregate', 'mailbox_type'),
            'unopenedCount' => $unopenedCount,
            'junkCount' => $this->scopeEmailAccountData(ReceivedEmail::query())->where('is_junk', true)->count(),
            'selectedJunk' => $request->input('junk', ''),
            'messages' => $query->paginate(10)->withQueryString(),
            'imapEnabled' => function_exists('imap_open'),
            'imapDiagnostics' => [
                'php_version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'ini' => php_ini_loaded_file() ?: 'No php.ini loaded',
                'binary' => PHP_BINARY ?: 'Unknown',
                'extension_loaded' => extension_loaded('imap'),
                'function_available' => function_exists('imap_open'),
            ],
        ];
    }

    private function messageQuery(Request $request, string $selectedMailboxType)
    {
        $query = $this->scopeEmailAccountData(ReceivedEmail::query());

        if ($this->isAdmin() && $request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('email_account_id')) {
            $query->where('email_account_id', $request->integer('email_account_id'));
        }

        if ($selectedMailboxType !== 'all') {
            $query->where('mailbox_type', $selectedMailboxType);
        }

        match ($request->input('opened')) {
            'opened' => $query->whereNotNull('opened_at'),
            'unopened' => $query->whereNull('opened_at'),
            default => null,
        };

        // Default: hide junk. Pass ?junk=1 to show junk only, ?junk=all to show everything.
        match ($request->input('junk')) {
            '1'   => $query->where('is_junk', true),
            'all' => null,
            default => $query->where('is_junk', false),
        };

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private function inboxQueryParameters(Request $request): array
    {
        return array_filter([
            'client_id' => $this->isAdmin() ? $request->input('client_id') : null,
            'email_account_id' => $request->input('email_account_id'),
            'mailbox' => $request->input('mailbox'),
            'opened' => $request->input('opened'),
            'junk' => $request->input('junk'),
            'page' => $request->input('page'),
        ], fn ($value): bool => filled($value));
    }

    private function adjacentMessage(Request $request, ReceivedEmail $receivedEmail, string $direction): ?ReceivedEmail
    {
        $query = $this->messageQuery($request, $this->selectedMailboxType($request))
            ->whereKeyNot($receivedEmail->id);

        if ($receivedEmail->received_at) {
            $operator = $direction === 'previous' ? '>' : '<';
            $idOperator = $direction === 'previous' ? '>' : '<';

            $query->where(function ($query) use ($receivedEmail, $operator, $idOperator): void {
                $query->where('received_at', $operator, $receivedEmail->received_at)
                    ->orWhere(function ($query) use ($receivedEmail, $idOperator): void {
                        $query->where('received_at', $receivedEmail->received_at)
                            ->where('id', $idOperator, $receivedEmail->id);
                    });
            });
        } else {
            $operator = $direction === 'previous' ? '>' : '<';
            $query->where('id', $operator, $receivedEmail->id);
        }

        return $direction === 'previous'
            ? $query->orderBy('received_at')->orderBy('id')->first()
            : $query->orderByDesc('received_at')->orderByDesc('id')->first();
    }

    /**
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    private function syncLatestForRequest(Request $request, InboxSyncService $syncService, string $mailboxType): array
    {
        $accountsQuery = $this->scopeEmailAccounts(EmailAccount::query())
            ->where('inbox_enabled', true)
            ->orderBy('email');

        if ($this->isAdmin() && $request->filled('client_id')) {
            $accountsQuery->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('email_account_id')) {
            $accountsQuery->whereKey($request->integer('email_account_id'));
        }

        $summary = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($accountsQuery->get() as $account) {
            try {
                $result = $mailboxType === 'all'
                    ? $syncService->syncAccountAllMailboxes($account, 5)
                    : $syncService->syncAccountMailbox($account, $mailboxType, 5);
                $summary['imported'] += $result['imported'];
                $summary['skipped'] += $result['skipped'];
            } catch (RuntimeException $exception) {
                $summary['errors'][] = $account->email.': '.$exception->getMessage();
            }
        }

        return $summary;
    }

    private function selectedMailboxType(Request $request): string
    {
        return $this->selectedMailboxValue((string) $request->input('mailbox', 'all'));
    }

    private function selectedMailboxValue(string $mailboxType): string
    {
        $mailboxType = strtolower(trim($mailboxType));

        return $mailboxType === 'all' ? 'all' : $this->normalizeMailboxType($mailboxType);
    }

    private function normalizeMailboxType(string $mailboxType): string
    {
        $mailboxType = strtolower(trim($mailboxType));

        return array_key_exists($mailboxType, ImapMailboxClient::mailboxTypeOptions())
            ? $mailboxType
            : ImapMailboxClient::MAILBOX_INBOX;
    }

    private function mailboxLabel(string $mailboxType): string
    {
        if ($mailboxType === 'all') {
            return 'mail';
        }

        return ImapMailboxClient::mailboxTypeOptions()[$this->normalizeMailboxType($mailboxType)] ?? 'Inbox';
    }
}
