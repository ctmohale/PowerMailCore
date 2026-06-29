<h1>Email Logs</h1>

<section class="panel">
    <h2>Filter Logs</h2>
    <form method="GET" action="/email-logs">
        <div class="form-grid three">
            <div class="field"><label>Client</label><select name="client_id"><option value="">All clients</option><?php foreach ($clients as $client): ?><option value="<?= e($client['id']) ?>" <?= ($_GET['client_id'] ?? '') == $client['id'] ? 'selected' : '' ?>><?= e($client['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Status</label><select name="status"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
            <div class="actions"><button type="submit">Filter</button><a class="button secondary" href="/email-logs">Reset</a></div>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Log List</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Status</th><th>Client</th><th>From</th><th>To</th><th>Subject</th><th>Error</th><th>Created</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><span class="badge <?= e($log['status']) ?>"><?= e($log['status']) ?></span></td>
                    <td><?= e($log['client_name']) ?></td>
                    <td><?= e($log['from_email']) ?></td>
                    <td><?= e($log['to_email']) ?></td>
                    <td class="wrap"><?= e($log['subject']) ?></td>
                    <td class="wrap"><?= e($log['error_message'] ? substr($log['error_message'], 0, 100) : '-') ?></td>
                    <td><?= e($log['created_at']) ?></td>
                    <td><a href="/email-logs/<?= e($log['id']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $logs): ?><tr><td colspan="8" class="muted">No logs yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
