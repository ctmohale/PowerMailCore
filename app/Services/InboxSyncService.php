<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

class InboxSyncService
{
    private readonly ImapMailboxClient $client;

    public function __construct(?ImapMailboxClient $client = null)
    {
        $this->client = $client ?? new ImapMailboxClient();
    }

    public function syncAccount(array $account, int $limit = 25): array
    {
        if ((int) $account['inbox_enabled'] !== 1) {
            throw new RuntimeException('Inbox access is not enabled for '.$account['email'].'.');
        }

        if (! $account['imap_host'] || ! $account['imap_username'] || ! $account['imap_password']) {
            throw new RuntimeException('Missing IMAP settings for '.$account['email'].'.');
        }

        $messages = $this->client->fetchLatest($account, $limit);
        $imported = 0;
        $skipped = 0;
        $latestUid = (int) ($account['last_inbound_uid'] ?? 0);

        foreach ($messages as $message) {
            $existing = Database::fetch(
                'select id from received_emails where email_account_id = ? and uid = ?',
                [$account['id'], $message['uid']],
            );

            if ($existing) {
                $skipped++;
            } else {
                Database::insert(
                    'insert into received_emails (client_id, domain_id, email_account_id, uid, message_id, from_name, from_email, to_email, subject, body_text, body_html, raw_headers, size, seen, received_at, fetched_at, created_at, updated_at)
                     values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, now(), now(), now())',
                    [
                        $account['client_id'],
                        $account['domain_id'],
                        $account['id'],
                        $message['uid'],
                        $message['message_id'],
                        $message['from_name'],
                        $message['from_email'],
                        $message['to_email'],
                        $message['subject'],
                        $message['body_text'],
                        $message['body_html'],
                        $message['raw_headers'],
                        $message['size'],
                        $message['seen'] ? 1 : 0,
                        $message['received_at'],
                    ],
                );
                $imported++;
            }

            $latestUid = max($latestUid, (int) $message['uid']);
        }

        Database::execute(
            'update email_accounts set last_inbound_uid = ?, inbox_last_synced_at = now(), updated_at = now() where id = ?',
            [$latestUid, $account['id']],
        );

        return ['imported' => $imported, 'skipped' => $skipped, 'total' => count($messages)];
    }
}
