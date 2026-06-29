<h1>API Keys</h1>

<?php if (! empty($_SESSION['plain_api_key'])): ?>
    <div class="key-box"><?= e($_SESSION['plain_api_key']) ?></div>
    <?php unset($_SESSION['plain_api_key']); ?>
<?php endif; ?>

<section class="panel">
    <h2>Create API Key</h2>
    <form method="POST" action="/api-keys">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field"><label for="client_id">Client</label><select id="client_id" name="client_id" required><option value="">Select client</option><?php foreach ($clients as $client): ?><option value="<?= e($client['id']) ?>"><?= e($client['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="name">Key Name</label><input id="name" name="name" value="<?= e(old('name')) ?>" placeholder="Website API Key" required></div>
        </div>
        <div class="actions"><button type="submit">Create API Key</button></div>
    </form>
</section>

<section class="panel">
    <h2>API Key List</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Client</th><th>Prefix</th><th>Last Used</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($apiKeys as $apiKey): ?>
                <tr><td><?= e($apiKey['name']) ?></td><td><?= e($apiKey['client_name']) ?></td><td><?= e($apiKey['key_prefix']) ?></td><td><?= e($apiKey['last_used_at'] ?: '-') ?></td><td><span class="badge <?= $apiKey['is_active'] ? 'active' : 'failed' ?>"><?= $apiKey['is_active'] ? 'Active' : 'Inactive' ?></span></td></tr>
            <?php endforeach; ?>
            <?php if (! $apiKeys): ?><tr><td colspan="5" class="muted">No API keys yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
