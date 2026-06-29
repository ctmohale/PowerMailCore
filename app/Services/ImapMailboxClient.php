<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class ImapMailboxClient
{
    public function fetchLatest(array $account, int $limit = 25): array
    {
        if (! function_exists('imap_open')) {
            throw new RuntimeException('The PHP IMAP extension is not enabled.');
        }

        $connection = @imap_open(
            $this->mailbox($account),
            (string) $account['imap_username'],
            (string) decrypt_secret($account['imap_password']),
            OP_READONLY,
            1,
        );

        if (! $connection) {
            $errors = imap_errors() ?: [];
            throw new RuntimeException('Could not connect to IMAP inbox. '.implode(' ', $errors));
        }

        try {
            $uids = imap_search($connection, 'ALL', SE_UID) ?: [];
            rsort($uids, SORT_NUMERIC);
            $messages = [];

            foreach (array_slice($uids, 0, $limit) as $uid) {
                $messages[] = $this->message($connection, (int) $uid, $account);
            }

            return $messages;
        } finally {
            imap_close($connection);
        }
    }

    private function mailbox(array $account): string
    {
        $flags = match (strtolower((string) $account['imap_encryption'])) {
            'starttls' => '/imap/tls',
            'none' => '/imap/notls',
            default => '/imap/ssl',
        };

        return sprintf('{%s:%d%s}INBOX', $account['imap_host'], (int) $account['imap_port'], $flags);
    }

    private function message($connection, int $uid, array $account): array
    {
        $overview = imap_fetch_overview($connection, (string) $uid, FT_UID)[0] ?? null;
        $structure = imap_fetchstructure($connection, (string) $uid, FT_UID);
        $bodies = $structure ? $this->bodies($connection, $uid, $structure) : ['text' => null, 'html' => null];
        $from = $this->address((string) ($overview->from ?? ''));
        $to = $this->address((string) ($overview->to ?? $account['email']));

        return [
            'uid' => $uid,
            'message_id' => trim((string) ($overview->message_id ?? ''), '<>') ?: null,
            'from_name' => $from['name'],
            'from_email' => $from['email'],
            'to_email' => $to['email'] ?: $account['email'],
            'subject' => $this->decodeMime((string) ($overview->subject ?? '(no subject)')),
            'body_text' => $bodies['text'],
            'body_html' => $bodies['html'],
            'raw_headers' => imap_fetchheader($connection, (string) $uid, FT_UID) ?: null,
            'size' => (int) ($overview->size ?? 0),
            'seen' => (int) ($overview->seen ?? 0) === 1,
            'received_at' => date('Y-m-d H:i:s', strtotime((string) ($overview->date ?? 'now')) ?: time()),
        ];
    }

    private function bodies($connection, int $uid, object $structure): array
    {
        $bodies = ['text' => null, 'html' => null];
        $this->walk($connection, $uid, $structure, '', $bodies);

        if ($bodies['text'] === null && $bodies['html'] === null) {
            $body = imap_body($connection, (string) $uid, FT_UID | FT_PEEK) ?: '';
            $bodies['text'] = trim($this->decodeBody($body, (int) ($structure->encoding ?? 0))) ?: null;
        }

        return $bodies;
    }

    private function walk($connection, int $uid, object $part, string $number, array &$bodies): void
    {
        if (isset($part->parts) && is_array($part->parts)) {
            foreach ($part->parts as $index => $child) {
                $this->walk($connection, $uid, $child, $number === '' ? (string) ($index + 1) : $number.'.'.($index + 1), $bodies);
            }

            return;
        }

        $subtype = strtolower((string) ($part->subtype ?? ''));

        if (! in_array($subtype, ['plain', 'html'], true)) {
            return;
        }

        $body = imap_fetchbody($connection, (string) $uid, $number ?: '1', FT_UID | FT_PEEK) ?: '';
        $body = trim($this->decodeBody($body, (int) ($part->encoding ?? 0)));

        if ($subtype === 'plain' && $bodies['text'] === null) {
            $bodies['text'] = $body ?: null;
        }

        if ($subtype === 'html' && $bodies['html'] === null) {
            $bodies['html'] = $body ?: null;
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

    private function address(string $address): array
    {
        $parsed = function_exists('imap_rfc822_parse_adrlist') ? imap_rfc822_parse_adrlist($address, '') : [];
        $first = $parsed[0] ?? null;

        if ($first && ! empty($first->mailbox) && ! empty($first->host)) {
            return [
                'name' => isset($first->personal) ? $this->decodeMime((string) $first->personal) : null,
                'email' => strtolower($first->mailbox.'@'.$first->host),
            ];
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
            $decoded .= (string) ($part->text ?? '');
        }

        return trim($decoded ?: $value);
    }
}
