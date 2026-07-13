<?php

namespace App\Livewire\Admin;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Models\EmailAccount;
use App\Models\ReceivedEmail;
use App\Models\User;
use App\Services\ImapMailboxClient;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class InboxSidebar extends Component
{
    use ScopesTenantData;

    public ?string $clientId = '';

    public ?string $emailAccountId = '';

    public string $mailbox = 'all';

    public string $opened = 'all';

    public function mount(?string $clientId = null, ?string $emailAccountId = null, string $mailbox = 'all', string $opened = 'all'): void
    {
        $this->clientId = (string) $clientId;
        $this->emailAccountId = (string) $emailAccountId;
        $this->mailbox = $this->selectedMailboxValue($mailbox);
        $this->opened = in_array($opened, ['all', 'opened', 'unopened'], true) ? $opened : 'all';
    }

    public function placeholder(array $params = []): View
    {
        return view('livewire.admin.inbox-sidebar-placeholder', [
            'canSendEmails' => $this->currentUser()->canAccess(User::PERMISSION_SEND_EMAILS),
            'canManageAccounts' => $this->currentUser()->canAccess(User::PERMISSION_MANAGE_ACCOUNTS),
            'hasUnreadableImapPassword' => $this->hasUnreadableImapPassword((string) ($params['clientId'] ?? '')),
        ]);
    }

    public function render(): View
    {
        $selectedMailboxType = $this->selectedMailboxValue($this->mailbox);

        $accountsQuery = $this->scopeEmailAccounts(EmailAccount::query())
            ->with('client')
            ->withCount(['receivedEmails' => function ($query) use ($selectedMailboxType): void {
                $this->applyInboxMessageFilters($query, $selectedMailboxType, includeAccountFilter: false);
            }])
            ->where('inbox_enabled', true)
            ->orderBy('email');

        $configurableAccountsQuery = $this->scopeEmailAccounts(EmailAccount::query())
            ->with('client')
            ->orderBy('email');

        if ($this->isAdmin() && filled($this->clientId)) {
            $accountsQuery->where('client_id', (int) $this->clientId);
            $configurableAccountsQuery->where('client_id', (int) $this->clientId);
        }

        $folderCounts = $this->messageQuery('all')
            ->selectRaw('mailbox_type, count(*) as aggregate')
            ->groupBy('mailbox_type')
            ->pluck('aggregate', 'mailbox_type');
        $accounts = $accountsQuery->get();

        return view('livewire.admin.inbox-sidebar', [
            'accounts' => $accounts,
            'clients' => $this->clientsForUser(),
            'configurableAccounts' => $this->currentUser()->canAccess(User::PERMISSION_MANAGE_ACCOUNTS)
                ? $configurableAccountsQuery->get()
                : collect(),
            'canManageAccounts' => $this->currentUser()->canAccess(User::PERMISSION_MANAGE_ACCOUNTS),
            'canSendEmails' => $this->currentUser()->canAccess(User::PERMISSION_SEND_EMAILS),
            'mailboxTypes' => ['all' => 'All mail'] + ImapMailboxClient::mailboxTypeOptions(),
            'selectedAccountId' => $this->emailAccountId,
            'selectedMailboxType' => $selectedMailboxType,
            'selectedOpenedFilter' => $this->opened,
            'folderCounts' => $folderCounts,
            'folderTotal' => $folderCounts->sum(),
            'accountTotal' => $accounts->sum('received_emails_count'),
        ]);
    }

    private function messageQuery(string $selectedMailboxType): Builder
    {
        $query = $this->scopeEmailAccountData(ReceivedEmail::query());

        if ($this->isAdmin() && filled($this->clientId)) {
            $query->where('client_id', (int) $this->clientId);
        }

        $this->applyInboxMessageFilters($query, $selectedMailboxType);

        return $query;
    }

    private function applyInboxMessageFilters(Builder $query, string $selectedMailboxType, bool $includeAccountFilter = true): void
    {
        if ($includeAccountFilter && filled($this->emailAccountId)) {
            $query->where('email_account_id', (int) $this->emailAccountId);
        }

        if ($selectedMailboxType !== 'all') {
            $query->where('mailbox_type', $selectedMailboxType);
        }

        match ($this->opened) {
            'opened' => $query->whereNotNull('opened_at'),
            'unopened' => $query->whereNull('opened_at'),
            default => null,
        };

        $query->where('is_junk', false);
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

    private function hasUnreadableImapPassword(string $clientId): bool
    {
        if (! $this->currentUser()->canAccess(User::PERMISSION_MANAGE_ACCOUNTS)) {
            return false;
        }

        $query = $this->scopeEmailAccounts(EmailAccount::query())
            ->whereNotNull('imap_password')
            ->orderBy('email');

        if ($this->isAdmin() && filled($clientId)) {
            $query->where('client_id', (int) $clientId);
        }

        return $query->get()->contains(fn (EmailAccount $account): bool => $account->hasImapPassword() && ! $account->hasUsableImapPassword());
    }
}
