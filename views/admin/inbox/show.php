<h1>Inbox Message #<?= e($message['id']) ?></h1>

<section class="panel">
    <div class="table-wrap">
        <table><tbody>
            <tr><th>Client</th><td><?= e($message['client_name']) ?></td></tr>
            <tr><th>Inbox</th><td><?= e($message['account_email']) ?></td></tr>
            <tr><th>From</th><td><?= e($message['from_name'] ? $message['from_name'].' <'.$message['from_email'].'>' : $message['from_email']) ?></td></tr>
            <tr><th>To</th><td><?= e($message['to_email']) ?></td></tr>
            <tr><th>Subject</th><td class="wrap"><?= e($message['subject'] ?: '(no subject)') ?></td></tr>
            <tr><th>Received</th><td><?= e($message['received_at'] ?: '-') ?></td></tr>
            <tr><th>Message ID</th><td><?= e($message['message_id'] ?: '-') ?></td></tr>
            <tr><th>UID</th><td><?= e($message['uid']) ?></td></tr>
        </tbody></table>
    </div>
</section>

<section class="panel">
    <h2>Message</h2>
    <?php if ($message['body_html']): ?>
        <iframe title="Email body" sandbox style="background:#fff;border:1px solid var(--line);border-radius:8px;min-height:420px;width:100%;" srcdoc="<?= e($message['body_html']) ?>"></iframe>
    <?php else: ?>
        <div class="message-body"><?= nl2br(e($message['body_text'] ?: 'No readable body found.')) ?></div>
    <?php endif; ?>
</section>

<section class="panel"><h2>Headers</h2><pre><?= e($message['raw_headers'] ?: 'No headers stored.') ?></pre></section>
<a class="button secondary" href="/inbox">Back to Inbox</a>
