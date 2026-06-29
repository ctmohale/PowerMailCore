<h1>Email Log #<?= e($log['id']) ?></h1>

<section class="panel">
    <div class="table-wrap">
        <table><tbody>
            <tr><th>Status</th><td><span class="badge <?= e($log['status']) ?>"><?= e($log['status']) ?></span></td></tr>
            <tr><th>Client</th><td><?= e($log['client_name']) ?></td></tr>
            <tr><th>Domain</th><td><?= e($log['domain'] ?: '-') ?></td></tr>
            <tr><th>API Key</th><td><?= e($log['api_key_name'] ?: '-') ?></td></tr>
            <tr><th>Template</th><td><?= e($log['template_name'] ?: '-') ?></td></tr>
            <tr><th>From</th><td><?= e($log['from_email']) ?></td></tr>
            <tr><th>To</th><td><?= e($log['to_email']) ?></td></tr>
            <tr><th>Subject</th><td class="wrap"><?= e($log['subject']) ?></td></tr>
            <tr><th>Message ID</th><td><?= e($log['provider_message_id'] ?: '-') ?></td></tr>
            <tr><th>Error</th><td class="wrap"><?= e($log['error_message'] ?: '-') ?></td></tr>
            <tr><th>Sent At</th><td><?= e($log['sent_at'] ?: '-') ?></td></tr>
        </tbody></table>
    </div>
</section>

<section class="panel"><h2>Payload</h2><pre><?= e(json_encode(json_decode($log['payload'] ?: '{}', true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></section>
<a class="button secondary" href="/email-logs">Back to Logs</a>
