<h1>Inbox</h1>

<?php if (! $imapEnabled): ?><div class="notice">PHP IMAP is not enabled on this server. You can configure inbox accounts now, but syncing requires the PHP IMAP extension.</div><?php endif; ?>

<section class="panel">
    <h2>Sync Inbox</h2>
    <div class="form-grid">
        <form method="POST" action="/inbox/sync">
            <?= csrf_field() ?>
            <div class="field"><label>Email Account</label><select name="email_account_id" required><option value="">Select inbox account</option><?php foreach ($accounts as $account): ?><option value="<?= e($account['id']) ?>"><?= e($account['email']) ?> | <?= e($account['client_name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Fetch Limit</label><input name="limit" type="number" min="1" max="100" value="25"></div>
            <div class="actions"><button type="submit">Sync Selected</button></div>
        </form>
        <form method="POST" action="/inbox/sync-all">
            <?= csrf_field() ?>
            <div class="field"><label>Fetch Limit Per Account</label><input name="limit" type="number" min="1" max="100" value="25"></div>
            <div class="actions"><button type="submit">Sync All Accounts</button><span class="muted"><?= count($accounts) ?> connected inbox account(s)</span></div>
        </form>
    </div>
</section>

<section class="panel">
    <h2>Filter Messages</h2>
    <form method="GET" action="/inbox">
        <div class="form-grid three">
            <div class="field"><label>Client</label><select name="client_id"><option value="">All clients</option><?php foreach ($clients as $client): ?><option value="<?= e($client['id']) ?>" <?= ($_GET['client_id'] ?? '') == $client['id'] ? 'selected' : '' ?>><?= e($client['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Email Account</label><select name="email_account_id"><option value="">All accounts</option><?php foreach ($accounts as $account): ?><option value="<?= e($account['id']) ?>" <?= ($_GET['email_account_id'] ?? '') == $account['id'] ? 'selected' : '' ?>><?= e($account['email']) ?></option><?php endforeach; ?></select></div>
            <div class="actions"><button type="submit">Filter</button><a class="button secondary" href="/inbox">Reset</a></div>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Received Emails</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Client</th><th>Inbox</th><th>From</th><th>Subject</th><th>Size</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($messages as $message): ?>
                <tr>
                    <td><?= e($message['received_at'] ?: '-') ?></td>
                    <td><?= e($message['client_name']) ?></td>
                    <td><?= e($message['account_email']) ?></td>
                    <td class="wrap"><?= e($message['from_name'] ? $message['from_name'].' <'.$message['from_email'].'>' : $message['from_email']) ?></td>
                    <td class="wrap"><?= e($message['subject'] ?: '(no subject)') ?></td>
                    <td><?= e(number_format(((int) $message['size']) / 1024, 1)) ?> KB</td>
                    <td><a href="/inbox/<?= e($message['id']) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $messages): ?><tr><td colspan="7" class="muted">No received emails yet. Configure IMAP on an account, then sync.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
