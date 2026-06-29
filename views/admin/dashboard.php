<h1>Dashboard</h1>

<section class="grid">
    <div class="metric"><span class="muted">Clients</span><strong><?= e($counts['clients']) ?></strong></div>
    <div class="metric"><span class="muted">Domains</span><strong><?= e($counts['domains']) ?></strong></div>
    <div class="metric"><span class="muted">SMTP Accounts</span><strong><?= e($counts['accounts']) ?></strong></div>
    <div class="metric"><span class="muted">Templates</span><strong><?= e($counts['templates']) ?></strong></div>
    <div class="metric"><span class="muted">API Keys</span><strong><?= e($counts['apiKeys']) ?></strong></div>
    <div class="metric"><span class="muted">Logs</span><strong><?= e($counts['logs']) ?></strong></div>
    <div class="metric"><span class="muted">Received</span><strong><?= e($counts['received']) ?></strong></div>
    <div class="metric"><span class="muted">Sent</span><strong><?= e($counts['sent']) ?></strong></div>
</section>

<section class="panel">
    <h2>Recent Logs</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Status</th><th>Client</th><th>From</th><th>To</th><th>Subject</th><th>Time</th></tr></thead>
            <tbody>
            <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><span class="badge <?= e($log['status']) ?>"><?= e($log['status']) ?></span></td>
                    <td><?= e($log['client_name']) ?></td>
                    <td><?= e($log['from_email']) ?></td>
                    <td><?= e($log['to_email']) ?></td>
                    <td class="wrap"><?= e($log['subject']) ?></td>
                    <td><?= e($log['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $recentLogs): ?><tr><td colspan="6" class="muted">No email logs yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Recent Inbox</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Client</th><th>Inbox</th><th>From</th><th>Subject</th></tr></thead>
            <tbody>
            <?php foreach ($recentReceived as $message): ?>
                <tr>
                    <td><?= e($message['received_at'] ?: $message['created_at']) ?></td>
                    <td><?= e($message['client_name']) ?></td>
                    <td><?= e($message['account_email']) ?></td>
                    <td class="wrap"><?= e($message['from_email']) ?></td>
                    <td class="wrap"><a href="/inbox/<?= e($message['id']) ?>"><?= e($message['subject'] ?: '(no subject)') ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $recentReceived): ?><tr><td colspan="5" class="muted">No received emails yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
