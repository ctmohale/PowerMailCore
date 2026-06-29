<h1>Clients</h1>

<section class="panel">
    <h2>Add Client</h2>
    <form method="POST" action="/clients">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field"><label for="name">Client Name</label><input id="name" name="name" value="<?= e(old('name')) ?>" required></div>
            <div class="field"><label for="contact_email">Contact Email</label><input id="contact_email" name="contact_email" type="email" value="<?= e(old('contact_email')) ?>"></div>
        </div>
        <div class="actions"><button type="submit">Add Client</button></div>
    </form>
</section>

<section class="panel">
    <h2>Client List</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Slug</th><th>Contact</th><th>Domains</th><th>Accounts</th><th>Templates</th><th>API Keys</th></tr></thead>
            <tbody>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td><?= e($client['name']) ?></td>
                    <td><?= e($client['slug']) ?></td>
                    <td><?= e($client['contact_email'] ?: '-') ?></td>
                    <td><?= e($client['domains_count']) ?></td>
                    <td><?= e($client['accounts_count']) ?></td>
                    <td><?= e($client['templates_count']) ?></td>
                    <td><?= e($client['api_keys_count']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $clients): ?><tr><td colspan="7" class="muted">No clients yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
