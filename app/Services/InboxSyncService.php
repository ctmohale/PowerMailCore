<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\ReceivedEmail;
use RuntimeException;

class InboxSyncService
{
    public function __construct(private readonly ImapMailboxClient $mailboxClient) {}

    /**
     * Sync the latest messages for one configured inbox.
     *
     * @return array{imported: int, skipped: int, total: int}
     */
    public function syncAccount(EmailAccount $account, int $limit = 25): array
    {
        if (! $account->inbox_enabled) {
            throw new RuntimeException('Inbox access is not enabled for this email account.');
        }

        if (! $account->imap_host || ! $account->imap_username || ! $account->imap_password) {
            throw new RuntimeException('This email account is missing IMAP host, username, or password.');
        }

        $messages = $this->mailboxClient->fetchLatest($account, $limit);
        $imported = 0;
        $skipped = 0;
        $latestUid = $account->last_inbound_uid;

        foreach ($messages as $message) {
            $receivedEmail = ReceivedEmail::updateOrCreate(
                [
                    'email_account_id' => $account->id,
                    'uid' => $message['uid'],
                ],
                [
                    'client_id' => $account->client_id,
                    'domain_id' => $account->domain_id,
                    'message_id' => $message['message_id'] ?? null,
                    'from_name' => $message['from_name'] ?? null,
                    'from_email' => $message['from_email'] ?? null,
                    'to_email' => $message['to_email'] ?? $account->email,
                    'subject' => $message['subject'] ?? null,
                    'body_text' => $message['body_text'] ?? null,
                    'body_html' => $message['body_html'] ?? null,
                    'raw_headers' => $message['raw_headers'] ?? null,
                    'size' => $message['size'] ?? 0,
                    'seen' => $message['seen'] ?? false,
                    'received_at' => $message['received_at'] ?? null,
                    'fetched_at' => now(),
                ],
            );

            $receivedEmail->wasRecentlyCreated ? $imported++ : $skipped++;
            $latestUid = max((int) $latestUid, (int) $message['uid']);
        }

        $account->forceFill([
            'last_inbound_uid' => $latestUid,
            'inbox_last_synced_at' => now(),
        ])->save();

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => count($messages),
        ];
    }
}
