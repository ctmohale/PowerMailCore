<?php

namespace App\Services;

use App\Models\EmailAccount;
use Carbon\CarbonImmutable;
use RuntimeException;

class ImapMailboxClient
{
    /**
     * Fetch the newest inbox messages from an IMAP mailbox.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchLatest(EmailAccount $account, int $limit = 25): array
    {
        if (! function_exists('imap_open')) {
            throw new RuntimeException('The PHP IMAP extension is not enabled. Enable imap in cPanel PHP extensions to sync inbox mail.');
        }

        $connection = @imap_open(
            $this->mailboxString($account),
            (string) $account->imap_username,
            (string) $account->imap_password,
            OP_READONLY,
            1,
        );

        if (! $connection) {
            throw new RuntimeException($this->lastError('Could not connect to the IMAP inbox.'));
        }

        try {
            $uids = imap_search($connection, 'ALL', SE_UID) ?: [];
            rsort($uids, SORT_NUMERIC);

            $messages = [];

            foreach (array_slice($uids, 0, $limit) as $uid) {
                $messages[] = $this->fetchMessage($connection, (int) $uid, $account);
            }

            return $messages;
        } finally {
            imap_close($connection);
        }
    }

    private function mailboxString(EmailAccount $account): string
    {
        $host = trim((string) $account->imap_host);
        $port = (int) ($account->imap_port ?: 993);
        $encryption = strtolower((string) ($account->imap_encryption ?: EmailAccount::ENCRYPTION_SSL));

        $flags = match ($encryption) {
            EmailAccount::ENCRYPTION_STARTTLS => '/imap/tls',
            EmailAccount::ENCRYPTION_NONE => '/imap/notls',
            default => '/imap/ssl',
        };

        return sprintf('{%s:%d%s}INBOX', $host, $port, $flags);
    }

    /**
     * @param  resource  $connection
     * @return array<string, mixed>
     */
    private function fetchMessage($connection, int $uid, EmailAccount $account): array
    {
        $overview = imap_fetch_overview($connection, (string) $uid, FT_UID)[0] ?? null;
        $structure = imap_fetchstructure($connection, (string) $uid, FT_UID);

        $from = $this->parseAddress((string) ($overview->from ?? ''));
        $to = $this->parseAddress((string) ($overview->to ?? $account->email));
        $bodies = $structure
            ? $this->fetchBodies($connection, $uid, $structure)
            : ['text' => null, 'html' => null];

        return [
            'uid' => $uid,
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
