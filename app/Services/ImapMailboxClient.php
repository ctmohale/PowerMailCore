<?php

namespace App\Services;

use App\Models\EmailAccount;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use RuntimeException;

class ImapMailboxClient
{
    public const MAILBOX_INBOX = 'inbox';

    public const MAILBOX_SPAM = 'spam';

    public const MAILBOX_SENT = 'sent';

    public const MAILBOX_DRAFTS = 'drafts';

    public const MAILBOX_TRASH = 'trash';

    public const MAILBOX_ARCHIVE = 'archive';

    private const CONNECTION_TIMEOUT_SECONDS = 10;

    private const READ_TIMEOUT_SECONDS = 12;

    private const MAX_FETCH_LIMIT = 50;

    /**
     * Fetch the newest inbox messages from an IMAP mailbox.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchLatest(EmailAccount $account, int $limit = 25): array
    {
        return $this->fetchLatestMailbox($account, self::MAILBOX_INBOX, $limit);
    }

    /**
     * Fetch the newest messages from a specific mailbox folder.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchLatestMailbox(EmailAccount $account, string $mailboxType, int $limit = 25): array
    {
        return $this->fetchMessages($account, $limit, mailboxType: $mailboxType);
    }

    /**
     * Fetch messages older than an already-synced UID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchBeforeUid(EmailAccount $account, int $beforeUid, int $limit = 25): array
    {
        return $this->fetchBeforeUidFromMailbox($account, self::MAILBOX_INBOX, $beforeUid, $limit);
    }

    /**
     * Fetch older messages from a specific mailbox folder.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchBeforeUidFromMailbox(EmailAccount $account, string $mailboxType, int $beforeUid, int $limit = 25): array
    {
        return $this->fetchMessages(
            $account,
            $limit,
            fn (int $uid): bool => $uid < $beforeUid,
            $mailboxType,
        );
    }

    /**
     * @param  null|callable(int): bool  $filter
     * @return array<int, array<string, mixed>>
     */
    private function fetchMessages(EmailAccount $account, int $limit = 25, ?callable $filter = null, string $mailboxType = self::MAILBOX_INBOX): array
    {
        if (! function_exists('imap_open')) {
            throw new RuntimeException('The PHP IMAP extension is not enabled for the PHP runtime serving this app.');
        }

        $mailboxType = $this->normalizeMailboxType($mailboxType);
        $limit = max(1, min($limit, self::MAX_FETCH_LIMIT));
        $startedAt = microtime(true);
        $syncBudgetSeconds = max(60, min(300, 30 + ($limit * 6)));

        $this->configureTimeouts();
        @set_time_limit($syncBudgetSeconds + 10);

        [$connection, $mailbox] = $this->openMailbox($account, $mailboxType);

        try {
            $uids = imap_search($connection, 'ALL', SE_UID) ?: [];
            rsort($uids, SORT_NUMERIC);

            if ($filter) {
                $uids = array_values(array_filter(
                    $uids,
                    fn ($uid): bool => $filter((int) $uid),
                ));
            }

            $messages = [];

            foreach (array_slice($uids, 0, $limit) as $uid) {
                if ((microtime(true) - $startedAt) > $syncBudgetSeconds) {
                    throw new RuntimeException('Inbox sync timed out before all messages were fetched. Try a smaller fetch limit.');
                }

                $messages[] = $this->fetchMessage($connection, (int) $uid, $account, $mailbox, $mailboxType);
            }

            return $messages;
        } finally {
            imap_close($connection);
        }
    }

    /**
     * @return array{0: resource, 1: string}
     */
    private function openMailbox(EmailAccount $account, string $mailboxType): array
    {
        $lastError = null;
        $password = $this->imapPasswordFor($account);

        foreach ($this->mailboxCandidates($mailboxType) as $mailbox) {
            $connection = @imap_open(
                $this->mailboxString($account, $mailbox),
                (string) $account->imap_username,
                $password,
                OP_READONLY,
                1,
            );

            if ($connection) {
                return [$connection, $mailbox];
            }

            $lastError = $this->lastError('');
        }

        throw new RuntimeException(trim('Could not connect to the '.$this->mailboxTypeLabel($mailboxType).' mailbox. '.$lastError));
    }

    private function imapPasswordFor(EmailAccount $account): string
    {
        try {
            return (string) $account->imap_password;
        } catch (DecryptException) {
            throw new RuntimeException('The saved IMAP password could not be decrypted. Re-enter and save the IMAP password in Inbox Settings.');
        }
    }

    private function configureTimeouts(): void
    {
        if (! function_exists('imap_timeout')) {
            return;
        }

        if (defined('IMAP_OPENTIMEOUT')) {
            imap_timeout(IMAP_OPENTIMEOUT, self::CONNECTION_TIMEOUT_SECONDS);
        }

        if (defined('IMAP_READTIMEOUT')) {
            imap_timeout(IMAP_READTIMEOUT, self::READ_TIMEOUT_SECONDS);
        }

        if (defined('IMAP_WRITETIMEOUT')) {
            imap_timeout(IMAP_WRITETIMEOUT, self::READ_TIMEOUT_SECONDS);
        }

        if (defined('IMAP_CLOSETIMEOUT')) {
            imap_timeout(IMAP_CLOSETIMEOUT, self::CONNECTION_TIMEOUT_SECONDS);
        }
    }

    private function mailboxString(EmailAccount $account, string $mailbox = 'INBOX'): string
    {
        $host = trim((string) $account->imap_host);
        $port = (int) ($account->imap_port ?: 993);
        $encryption = strtolower((string) ($account->imap_encryption ?: EmailAccount::ENCRYPTION_SSL));

        $flags = match ($encryption) {
            EmailAccount::ENCRYPTION_STARTTLS => '/imap/tls',
            EmailAccount::ENCRYPTION_NONE => '/imap/notls',
            default => '/imap/ssl',
        };

        return sprintf('{%s:%d%s}%s', $host, $port, $flags, $this->encodeMailboxName($mailbox));
    }

    /**
     * @param  resource  $connection
     * @return array<string, mixed>
     */
    private function fetchMessage($connection, int $uid, EmailAccount $account, string $mailbox, string $mailboxType): array
    {
        $overviewResult = @imap_fetch_overview($connection, (string) $uid, FT_UID);
        $overview = is_array($overviewResult) ? ($overviewResult[0] ?? null) : null;

        if (! $overview) {
            throw new RuntimeException($this->lastError('Could not fetch inbox message headers. Try a smaller fetch limit.'));
        }

        $structure = @imap_fetchstructure($connection, (string) $uid, FT_UID);

        $from = $this->parseAddress((string) ($overview->from ?? ''));
        $to = $this->parseAddress((string) ($overview->to ?? $account->email));
        $bodies = $structure
            ? $this->fetchBodies($connection, $uid, $structure)
            : ['text' => null, 'html' => null];

        return [
            'uid' => $uid,
            'mailbox' => $mailbox,
            'mailbox_type' => $mailboxType,
            'message_id' => trim((string) ($overview->message_id ?? ''), '<>') ?: null,
            'from_name' => $from['name'],
            'from_email' => $from['email'],
            'to_email' => $to['email'] ?: $account->email,
            'subject' => $this->decodeMime((string) ($overview->subject ?? '(no subject)')),
            'body_text' => $bodies['text'],
            'body_html' => $bodies['html'],
            'raw_headers' => imap_fetchheader($connection, (string) $uid, FT_UID) ?: null,
            'size' => (int) ($overview->size ?? 0),
            'seen' => str_contains((string) ($overview->seen ?? ''), '1'),
            'received_at' => $this->parseDate((string) ($overview->date ?? '')),
        ];
    }

    /**
     * @param  resource  $connection
     * @return array{text: ?string, html: ?string}
     */
    private function fetchBodies($connection, int $uid, object $structure): array
    {
        $bodies = ['text' => null, 'html' => null];

        $this->walkParts($connection, $uid, $structure, '', $bodies);

        if ($bodies['text'] === null && $bodies['html'] === null) {
            $rawBody = imap_body($connection, (string) $uid, FT_UID | FT_PEEK) ?: '';
            $bodies['text'] = trim($this->decodeBody($rawBody, (int) ($structure->encoding ?? 0))) ?: null;
        }

        return $bodies;
    }

    /**
     * @param  resource  $connection
     * @param  array{text: ?string, html: ?string}  $bodies
     */
    private function walkParts($connection, int $uid, object $part, string $partNumber, array &$bodies): void
    {
        if (isset($part->parts) && is_array($part->parts)) {
            foreach ($part->parts as $index => $childPart) {
                $childNumber = $partNumber === '' ? (string) ($index + 1) : $partNumber.'.'.($index + 1);
                $this->walkParts($connection, $uid, $childPart, $childNumber, $bodies);
            }

            return;
        }

        $subtype = strtolower((string) ($part->subtype ?? ''));

        if (! in_array($subtype, ['plain', 'html'], true)) {
            return;
        }

        $body = imap_fetchbody($connection, (string) $uid, $partNumber ?: '1', FT_UID | FT_PEEK) ?: '';
        $body = $this->decodeBody($body, (int) ($part->encoding ?? 0));
        $body = $this->convertCharset($body, $this->partCharset($part));

        if ($subtype === 'plain' && $bodies['text'] === null) {
            $bodies['text'] = trim($body) ?: null;
        }

        if ($subtype === 'html' && $bodies['html'] === null) {
            $bodies['html'] = trim($body) ?: null;
        }
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body, true) ?: $body,
            4 => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function convertCharset(string $body, ?string $charset): string
    {
        if (! $charset || strtoupper($charset) === 'UTF-8') {
            return $body;
        }

        $converted = @mb_convert_encoding($body, 'UTF-8', $charset);

        return is_string($converted) ? $converted : $body;
    }

    private function partCharset(object $part): ?string
    {
        foreach (['parameters', 'dparameters'] as $property) {
            foreach (($part->{$property} ?? []) as $parameter) {
                if (strtolower((string) ($parameter->attribute ?? '')) === 'charset') {
                    return (string) $parameter->value;
                }
            }
        }

        return null;
    }

    /**
     * @return array{name: ?string, email: ?string}
     */
    private function parseAddress(string $address): array
    {
        if (function_exists('imap_rfc822_parse_adrlist')) {
            $parsed = imap_rfc822_parse_adrlist($address, '');
            $first = $parsed[0] ?? null;

            if ($first && ! empty($first->mailbox) && ! empty($first->host)) {
                return [
                    'name' => isset($first->personal) ? $this->decodeMime((string) $first->personal) : null,
                    'email' => strtolower($first->mailbox.'@'.$first->host),
                ];
            }
        }

        preg_match('/(?:"?([^"<]*)"?\s)?<?([^<>\s]+@[^<>\s]+)>?/', $address, $matches);

        return [
            'name' => isset($matches[1]) ? trim($this->decodeMime($matches[1])) ?: null : null,
            'email' => isset($matches[2]) ? strtolower(trim($matches[2])) : null,
        ];
    }

    private function normalizeMailboxType(string $mailboxType): string
    {
        $mailboxType = strtolower(trim($mailboxType));

        return in_array($mailboxType, array_keys(self::mailboxTypeOptions()), true)
            ? $mailboxType
            : self::MAILBOX_INBOX;
    }

    /**
     * @return array<string, string>
     */
    public static function mailboxTypeOptions(): array
    {
        return [
            self::MAILBOX_INBOX => 'Inbox',
            self::MAILBOX_SPAM => 'Spam / Junk',
            self::MAILBOX_SENT => 'Sent',
            self::MAILBOX_DRAFTS => 'Drafts',
            self::MAILBOX_TRASH => 'Trash',
            self::MAILBOX_ARCHIVE => 'Archive',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function mailboxCandidates(string $mailboxType): array
    {
        return match ($this->normalizeMailboxType($mailboxType)) {
            self::MAILBOX_SPAM => ['INBOX.spam', 'INBOX.Spam', 'Spam', 'spam', 'Junk', 'INBOX.Junk', 'Junk E-mail', '[Gmail]/Spam'],
            self::MAILBOX_SENT => ['INBOX.Sent', 'Sent', 'sent', 'Sent Items', 'INBOX.Sent Items', '[Gmail]/Sent Mail'],
            self::MAILBOX_DRAFTS => ['INBOX.Drafts', 'Drafts', 'drafts', '[Gmail]/Drafts'],
            self::MAILBOX_TRASH => ['INBOX.Trash', 'Trash', 'trash', 'Deleted Items', 'INBOX.Deleted Items', '[Gmail]/Trash'],
            self::MAILBOX_ARCHIVE => ['INBOX.Archive', 'Archive', 'archive', '[Gmail]/All Mail'],
            default => ['INBOX'],
        };
    }

    private function mailboxTypeLabel(string $mailboxType): string
    {
        return self::mailboxTypeOptions()[$this->normalizeMailboxType($mailboxType)] ?? 'Inbox';
    }

    private function encodeMailboxName(string $mailbox): string
    {
        return function_exists('imap_utf7_encode') ? imap_utf7_encode($mailbox) : $mailbox;
    }

    private function decodeMime(string $value): string
    {
        if (! function_exists('imap_mime_header_decode')) {
            return trim($value);
        }

        $decoded = '';

        foreach (imap_mime_header_decode($value) ?: [] as $part) {
            $charset = strtoupper((string) ($part->charset ?? 'UTF-8'));
            $text = (string) ($part->text ?? '');
            $decoded .= $charset === 'DEFAULT' || $charset === 'UTF-8'
                ? $text
                : $this->convertCharset($text, $charset);
        }

        return trim($decoded ?: $value);
    }

    private function parseDate(string $date): ?CarbonImmutable
    {
        if ($date === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    private function lastError(string $fallback): string
    {
        $errors = function_exists('imap_errors') ? imap_errors() : null;

        if (! $errors) {
            return $fallback;
        }

        return $fallback.' '.implode(' ', array_filter($errors));
    }
}
