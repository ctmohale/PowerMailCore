<h1>Domains</h1>

<section class="panel">
    <h2>Add Domain</h2>
    <form method="POST" action="/domains">
        <?= csrf_field() ?>
        <div class="form-grid three">
            <div class="field">
                <label for="client_id">Client</label>
                <select id="client_id" name="client_id" required>
                    <option value="">Select client</option>
                    <?php foreach ($clients as $client): ?><option value="<?= e($client['id']) ?>"><?= e($client['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label for="domain">Domain</label><input id="domain" name="domain" value="<?= e(old('domain')) ?>" placeholder="beestack.co.za" required></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="active">Active</option><option value="pending">Pending</option></select></div>
        </div>
        <div class="actions"><button type="submit">Add Domain</button></div>
    </form>
</section>

<section class="panel">
    <h2>Domain List</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Domain</th><th>Client</th><th>Status</th><th>Added</th></tr></thead>
            <tbody>
            <?php foreach ($domains as $domain): ?>
                <tr><td><?= e($domain['domain']) ?></td><td><?= e($domain['client_name']) ?></td><td><span class="badge <?= e($domain['status']) ?>"><?= e($domain['status']) ?></span></td><td><?= e($domain['created_at']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (! $domains): ?><tr><td colspan="4" class="muted">No domains yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
