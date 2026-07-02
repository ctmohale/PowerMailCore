@php
    $isAdmin = auth()->user()->isAdmin();
@endphp

@forelse ($messages as $message)
    @php
        $isUnopened = blank($message->getRawOriginal('opened_at'));
        $subject = $message->subject ?: '(no subject)';
        $shortSubject = (string) str($subject)->limit(54);
        $replySubject = $message->subject ? 'Re: '.$message->subject : '';
        $forwardSubject = $message->subject ? 'Fwd: '.$message->subject : 'Fwd:';
        $senderName = $message->from_name ?: ($message->from_email ?: 'Sender');
        $replyData = json_encode([
            'name' => $senderName,
            'original_subject' => $message->subject,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $forwardedFrom = $message->from_name ? $message->from_name.' <'.$message->from_email.'>' : $message->from_email;
        $forwardedReceivedAt = $message->received_at?->format('Y-m-d H:i');
        $forwardedBody = (string) str($message->body_text ?: strip_tags((string) $message->body_html))->limit(2000);
        $forwardMessage = trim(implode("\n", array_filter([
            'Forwarded message',
            '',
            'From: '.$forwardedFrom,
            $message->to_email ? 'To: '.$message->to_email : null,
            $message->subject ? 'Subject: '.$message->subject : null,
            $forwardedReceivedAt ? 'Date: '.$forwardedReceivedAt : null,
            '',
            $forwardedBody,
        ], fn ($line) => $line !== null)));
        $forwardData = json_encode([
            'name' => 'Client',
            'message' => $forwardMessage,
            'forwarded_from' => $forwardedFrom,
            'forwarded_to' => $message->to_email,
            'forwarded_subject' => $message->subject,
            'forwarded_received_at' => $forwardedReceivedAt,
            'forwarded_body' => $forwardedBody,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    @endphp
    <tr @class(['unopened-row' => $isUnopened]) data-message-id="{{ $message->id }}" data-open-url="{{ route('inbox.show', $message) }}">
        <td class="date-column">
            <span class="date-cell compact">
                @if ($isUnopened)
                    <span class="unread-dot" title="Unopened"></span>
                @endif
                <span>
                    <strong>{{ $message->received_at?->format('d M') ?: '-' }}</strong>
                    @if ($message->received_at)
                        <small>{{ $message->received_at->format('H:i') }}</small>
                    @endif
                </span>
            </span>
        </td>
        @if ($isAdmin)
            <td class="client-column">{{ $message->client?->name }}</td>
        @endif
        <td class="wrap from-cell">{{ $message->from_name ? $message->from_name.' <'.$message->from_email.'>' : $message->from_email }}</td>
        <td class="wrap subject-cell">
            <span title="{{ $subject }}" @class(['message-subject' => true, 'unopened' => $isUnopened])>{{ $shortSubject }}</span>
        </td>
        <td class="status-cell"><span @class(['badge' => true, 'pending' => $isUnopened, 'opened' => ! $isUnopened])>{{ $isUnopened ? 'Unopened' : 'Opened' }}</span></td>
        <td class="actions-cell">
            <div class="inline-actions">
                <a class="mail-icon-action" href="{{ route('inbox.show', $message) }}" aria-label="Open" title="Open">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="3"/></svg>
                </a>
                @if ($canSendEmails && $message->from_email)
                    <button
                        class="mail-icon-action"
                        type="button"
                        aria-label="Reply"
                        title="Reply"
                        data-open-dialog="compose-email-dialog"
                        data-compose-to="{{ $message->from_email }}"
                        data-compose-subject="{{ $replySubject }}"
                        data-compose-data="{{ $replyData }}"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 10 4 15l5 5"/><path d="M4 15h10a6 6 0 0 0 6-6V5"/></svg>
                    </button>
                    <button
                        class="mail-icon-action"
                        type="button"
                        aria-label="Forward"
                        title="Forward"
                        data-open-dialog="compose-email-dialog"
                        data-compose-to=""
                        data-compose-subject="{{ $forwardSubject }}"
                        data-compose-data="{{ $forwardData }}"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 10 5 5-5 5"/><path d="M20 15H10a6 6 0 0 1-6-6V5"/></svg>
                    </button>
                @endif
                @if ($isUnopened)
                    <form method="POST" action="{{ route('inbox.mark-opened', $message) }}">
                        @csrf
                        @method('PATCH')
                        <button class="mail-icon-action" type="submit" aria-label="Read" title="Read">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('inbox.mark-unopened', $message) }}">
                        @csrf
                        @method('PATCH')
                        <button class="mail-icon-action" type="submit" aria-label="Unread" title="Unread">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8.5 12 14l8-5.5"/><path d="M5 6h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z"/></svg>
                        </button>
                    </form>
                @endif
                <form method="POST" action="{{ route('inbox.destroy', $message) }}" data-confirm="Delete this email from PowerMail inbox? This removes the local copy only.">
                    @csrf
                    @method('DELETE')
                    <button class="mail-icon-action danger" type="submit" aria-label="Delete" title="Delete">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 15h10l1-15"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    </button>
                </form>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="{{ $isAdmin ? 6 : 5 }}" class="muted">No received emails found for your linked inbox accounts.</td>
    </tr>
@endforelse
