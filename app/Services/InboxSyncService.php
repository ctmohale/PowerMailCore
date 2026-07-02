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
        $this->validateAccount($account);

        $messages = $this->mailboxClient->fetchLatest($account, $limit);

        return $this->storeMessages($account, $messages, ImapMailboxClient::MAILBOX_INBOX);
    }

    /**
     * Sync the latest messages for one configured mailbox folder.
     *
     * @return array{imported: int, skipped: int, total: int}
     */
    public function syncAccountMailbox(EmailAccount $account, string $mailboxType, int $limit = 25): array
    {
        $this->validateAccount($account);

        $mailboxType = $this->normalizeMailboxType($mailboxType);
        $messages = $mailboxType === ImapMailboxClient::MAILBOX_INBOX
            ? $this->mailboxClient->fetchLatest($account, $limit)
            : $this->mailboxClient->fetchLatestMailbox($account, $mailboxType, $limit);

        return $this->storeMessages($account, $messages, $mailboxType);
    }

    /**
     * Sync the latest messages for every supported mailbox folder.
     *
     * @return array{imported: int, skipped: int, total: int}
     */
    public function syncAccountAllMailboxes(EmailAccount $account, int $limit = 10): array
    {
        $total = ['imported' => 0, 'skipped' => 0, 'total' => 0];

        foreach (array_keys(ImapMailboxClient::mailboxTypeOptions()) as $mailboxType) {
            $result = $this->syncAccountMailbox($account, $mailboxType, $limit);

            $total['imported'] += $result['imported'];
            $total['skipped'] += $result['skipped'];
            $total['total'] += $result['total'];
        }

        return $total;
    }

    /**
     * Fetch older messages than the oldest local message for one inbox.
     *
     * @return array{imported: int, skipped: int, total: int}
     */
    public function syncOlderAccount(EmailAccount $account, int $limit = 25): array
    {
        $this->validateAccount($account);

        $oldestUid = ReceivedEmail::query()
            ->where('email_account_id', $account->id)
            ->where('mailbox_type', ImapMailboxClient::MAILBOX_INBOX)
            ->min('uid');

        if (! $oldestUid) {
            return $this->syncAccount($account, $limit);
        }

        $messages = $this->mailboxClient->fetchBeforeUid($account, (int) $oldestUid, $limit);

        return $this->storeMessages($account, $messages, ImapMailboxClient::MAILBOX_INBOX);
    }

    /**
     * Fetch older messages than the oldest local message for one mailbox folder.
     *
     * @return array{imported: int, skipped: int, total: int}
     */
    public function syncOlderAccountMailbox(EmailAccount $account, string $mailboxType, int $limit = 25): array
    {
        $this->validateAccount($account);

        $mailboxType = $this->normalizeMailboxType($mailboxType);
        $oldestUid = ReceivedEmail::query()
            ->where('email_account_id', $account->id)
            ->where('mailbox_type', $mailboxType)
            ->min('uid');

        if (! $oldestUid) {
            return $this->syncAccountMailbox($account, $mailboxType, $limit);
        }

        $messages = $mailboxType === ImapMailboxClient::MAILBOX_INBOX
            ? $this->mailboxClient->fetchBeforeUid($account, (int) $oldestUid, $limit)
            : $this->mailboxClient->fetchBeforeUidFromMailbox($account, $mailboxType, (int) $oldestUid, $limit);

        return $this->storeMessages($account, $messages, $mailboxType);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{imported: int, skipped: int, total: int}
     */
    private function storeMessages(EmailAccount $account, array $messages, string $mailboxType): array
    {
        $imported = 0;
        $skipped = 0;
        $latestUid = $account->last_inbound_uid;
        $mailboxType = $this->normalizeMailboxType($mailboxType);

        foreach ($messages as $message) {
            $mailbox = (string) ($message['mailbox'] ?? 'INBOX');

            $receivedEmail = ReceivedEmail::updateOrCreate(
                [
                    'email_account_id' => $account->id,
                    'mailbox' => $mailbox,
                    'uid' => $message['uid'],
                ],
                [
                    'client_id' => $account->client_id,
                    'domain_id' => $account->domain_id,
                    'mailbox' => $mailbox,
                    'mailbox_type' => $this->normalizeMailboxType((string) ($message['mailbox_type'] ?? $mailboxType)),
                    'source' => 'imap',
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

    private function validateAccount(EmailAccount $account): void
    {
        if (! $account->inbox_enabled) {
            throw new RuntimeException('Inbox access is not enabled for this email account.');
        }

        if (! $account->imap_host || ! $account->imap_username || ! $account->hasImapPassword()) {
            throw new RuntimeException('This email account is missing IMAP host, username, or password.');
        }

        if (! $account->hasUsableImapPassword()) {
            throw new RuntimeException('The saved IMAP password could not be decrypted. Re-enter and save the IMAP password in Inbox Settings.');
        }
    }

    private function normalizeMailboxType(string $mailboxType): string
    {
        $mailboxType = strtolower(trim($mailboxType));

        return array_key_exists($mailboxType, ImapMailboxClient::mailboxTypeOptions())
            ? $mailboxType
            : ImapMailboxClient::MAILBOX_INBOX;
    }
}
