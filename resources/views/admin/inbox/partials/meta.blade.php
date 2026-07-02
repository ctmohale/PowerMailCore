{{ number_format($messages->total()) }} message{{ $messages->total() === 1 ? '' : 's' }} found
@if ($unopenedCount > 0)
    <span class="mail-meta-dot"></span>{{ number_format($unopenedCount) }} unopened
@endif
@if (request('email_account_id'))
    from selected account
@endif
@if ($selectedMailboxType !== 'all')
    in {{ $mailboxTypes[$selectedMailboxType] ?? 'Inbox' }}
@endif
