<?php
$defaultHtmlBody = '<p>Hello {{ name }},</p><p>Welcome to PowerMail Core.</p>';
$defaultTextBody = "Hello {{ name }},\n\nWelcome to PowerMail Core.";
?>
<h1>Templates</h1>

<section class="panel">
    <h2>Create Template</h2>
    <form method="POST" action="/email-templates">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="client_id">Client</label>
                <select id="client_id" name="client_id" required><option value="">Select client</option><?php foreach ($clients as $client): ?><option value="<?= e($client['id']) ?>"><?= e($client['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="field"><label for="key">Template Key</label><input id="key" name="key" value="<?= e(old('key')) ?>" placeholder="welcome" required></div>
            <div class="field"><label for="name">Name</label><input id="name" name="name" value="<?= e(old('name')) ?>" placeholder="Welcome Email" required></div>
            <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" value="<?= e(old('subject')) ?>" placeholder="Welcome to {{ name }}" required></div>
            <div class="field full"><label for="body_html">HTML Body</label><textarea id="body_html" name="body_html" required><?= e(old('body_html', $defaultHtmlBody)) ?></textarea></div>
            <div class="field full"><label for="body_text">Text Body</label><textarea id="body_text" name="body_text"><?= e(old('body_text', $defaultTextBody)) ?></textarea></div>
            <label class="field checkbox"><input name="is_active" type="checkbox" value="1" checked> Active</label>
        </div>
        <div class="actions"><button type="submit">Create Template</button></div>
    </form>
</section>

<section class="panel">
    <h2>Template List</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Key</th><th>Name</th><th>Client</th><th>Subject</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($templates as $template): ?>
                <tr><td><?= e($template['key']) ?></td><td><?= e($template['name']) ?></td><td><?= e($template['client_name']) ?></td><td class="wrap"><?= e($template['subject']) ?></td><td><span class="badge <?= $template['is_active'] ? 'active' : 'failed' ?>"><?= $template['is_active'] ? 'Active' : 'Inactive' ?></span></td></tr>
            <?php endforeach; ?>
            <?php if (! $templates): ?><tr><td colspan="5" class="muted">No templates yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
