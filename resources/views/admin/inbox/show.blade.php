@extends('layouts.app')

@section('title', 'Inbox Message | PowerMail Core')

@section('content')
    @php
        $subject = $message->subject ?: '(no subject)';
        $senderName = $message->from_name ?: $message->from_email ?: 'Unknown sender';
        $senderEmail = $message->from_email;
        $senderInitial = strtoupper(substr(trim($senderName), 0, 1)) ?: '?';
        $replySubject = str_starts_with(strtolower($subject), 're:') ? $subject : 'Re: '.$subject;
        $replyData = json_encode([
            'name' => $senderName,
            'original_subject' => $subject,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    @endphp

    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Inbound Detail</p>
            <h1>{{ $subject }}</h1>
            <p class="lede">Opened from {{ $message->emailAccount?->email ?: 'linked inbox' }}</p>
        </div>
        <div class="inline-actions">
            @if ($canSendEmails && $senderEmail)
                <button
                    type="button"
                    data-open-dialog="compose-email-dialog"
                    data-compose-to="{{ $senderEmail }}"
                    data-compose-subject="{{ $replySubject }}"
                    data-compose-data="{{ $replyData }}"
                >Reply</button>
            @endif
            @if ($prevMessageUrl)
                <a class="button secondary" href="{{ $prevMessageUrl }}" title="Newer email">&#8592; Prev</a>
            @endif
            @if ($nextMessageUrl)
                <a class="button secondary" href="{{ $nextMessageUrl }}" title="Older email">Next &#8594;</a>
            @endif
            <a class="button secondary" href="{{ $inboxIndexUrl }}">Back to Inbox</a>
        </div>
    </div>

    <section class="panel email-reader">
        <div class="email-reader-header">
            <div class="sender-avatar">{{ $senderInitial }}</div>
            <div class="sender-summary">
                <div class="sender-line">
                    <strong>{{ $senderName }}</strong>
                    @if ($senderEmail && $senderEmail !== $senderName)
                        <span>&lt;{{ $senderEmail }}&gt;</span>
                    @endif
                </div>
                <div class="recipient-line">
                    @if ($message->to_email)
                        <span>to {{ $message->to_email }}</span>
                    @endif
                    @if ($message->emailAccount?->email)
                        <span>{{ $message->emailAccount->email }}</span>
                    @endif
                </div>
            </div>
            @if ($message->received_at)
                <time class="email-received" datetime="{{ $message->received_at->toIso8601String() }}">
                    {{ $message->received_at->format('Y-m-d H:i') }}
                </time>
            @endif
        </div>

        @if ($message->body_html)
            <iframe
                title="Email body"
                sandbox
                class="message-frame"
                srcdoc="{{ $message->body_html }}"
            ></iframe>
        @else
            <div class="message-body">
                {!! nl2br(e($message->body_text ?: 'No readable body found.')) !!}
            </div>
        @endif
    </section>

    @include('admin.inbox.partials.compose-dialog', [
        'composeContext' => 'inbox-detail',
        'composeTitle' => 'Reply to Email',
        'composeDescription' => 'Send a reply to this sender.',
        'composeTo' => $senderEmail,
        'composeSubject' => $replySubject,
        'composeDataJson' => $replyData,
    ])
@endsection
