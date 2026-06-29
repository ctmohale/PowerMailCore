<h1>Email Accounts</h1>

<section class="panel">
    <h2>Add SMTP Email Account</h2>
    <form method="POST" action="/email-accounts">
        <?= csrf_field() ?>
        <div class="form-grid three">
            <div class="field"><label for="client_id">Client</label><select id="client_id" name="client_id" required><option value="">Select client</option><?php foreach ($clients as $client): ?><option value="<?= e($client['id']) ?>"><?= e($client['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="domain_id">Domain</label><select id="domain_id" name="domain_id" required><option value="">Select domain</option><?php foreach ($domains as $domain): ?><option value="<?= e($domain['id']) ?>"><?= e($domain['domain']) ?> | <?= e($domain['client_name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="email">From Email</label><input id="email" name="email" type="email" value="<?= e(old('email')) ?>" placeholder="info@domain.co.za" required></div>
            <div class="field"><label for="from_name">From Name</label><input id="from_name" name="from_name" value="<?= e(old('from_name')) ?>"></div>
            <div class="field"><label for="smtp_host">SMTP Host</label><input id="smtp_host" name="smtp_host" value="<?= e(old('smtp_host')) ?>" placeholder="mail.domain.co.za" required></div>
            <div class="field"><label for="smtp_port">SMTP Port</label><input id="smtp_port" name="smtp_port" type="number" value="<?= e(old('smtp_port', 587)) ?>" min="1" max="65535" required></div>
            <div class="field"><label for="smtp_encryption">SMTP Encryption</label><select id="smtp_encryption" name="smtp_encryption"><option value="starttls">STARTTLS</option><option value="ssl">SSL</option><option value="none">None</option></select></div>
            <div class="field"><label for="smtp_username">SMTP Username</label><input id="smtp_username" name="smtp_username" value="<?= e(old('smtp_username')) ?>" required></div>
            <div class="field"><label for="smtp_password">SMTP Password</label><input id="smtp_password" name="smtp_password" type="password" required></div>
            <label class="field checkbox"><input name="is_active" type="checkbox" value="1" checked> Active</label>
            <label class="field checkbox"><input name="inbox_enabled" type="checkbox" value="1"> Enable inbox access</label>
            <div class="field"><label for="imap_host">IMAP Host</label><input id="imap_host" name="imap_host" value="<?= e(old('imap_host')) ?>" placeholder="mail.domain.co.za"></div>
            <div class="field"><label for="imap_port">IMAP Port</label><input id="imap_port" name="imap_port" type="number" value="<?= e(old('imap_port', 993)) ?>" min="1" max="65535"></div>
            <div class="field"><label for="imap_encryption">IMAP Encryption</label><select id="imap_encryption" name="imap_encryption"><option value="ssl">SSL</option><option value="starttls">STARTTLS</option><option value="none">None</option></select></div>
            <div class="field"><label for="imap_username">IMAP Username</label><input id="imap_username" name="imap_username" value="<?= e(old('imap_username')) ?>"></div>
            <div class="field"><label for="imap_password">IMAP Password</label><input id="imap_password" name="imap_password" type="password"></div>
        </div>
        <div class="actions"><button type="submit">Add Account</button></div>
    </form>
</section>

<section class="panel">
    <h2>Account List</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Email</th><th>Client</th><th>Domain</th><th>SMTP</th><th>Inbox</th><th>Last Sync</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($accounts as $account): ?>
                <tr>
                    <td><?= e($account['email']) ?></td>
                    <td><?= e($account['client_name']) ?></td>
                    <td><?= e($account['domain']) ?></td>
                    <td><?= e($account['smtp_host'].':'.$account['smtp_port']) ?></td>
                    <td><span class="badge <?= $account['inbox_enabled'] ? 'active' : 'pending' ?>"><?= $account['inbox_enabled'] ? 'Enabled' : 'Off' ?></span></td>
                    <td><?= e($account['inbox_last_synced_at'] ?: '-') ?></td>
                    <td><span class="badge <?= $account['is_active'] ? 'active' : 'failed' ?>"><?= $account['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $accounts): ?><tr><td colspan="7" class="muted">No email accounts yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Inbox Settings</h2>
    <?php foreach ($accounts as $account): ?>
        <form method="POST" action="/email-accounts/<?= e($account['id']) ?>/inbox" style="border-top: 1px solid var(--line); padding-top: 16px; margin-top: 16px;">
            <?= csrf_field() ?>
            <strong><?= e($account['email']) ?></strong>
            <div class="form-grid three" style="margin-top: 12px;">
                <label class="field checkbox"><input name="inbox_enabled" type="checkbox" value="1" <?= $account['inbox_enabled'] ? 'checked' : '' ?>> Enable inbox</label>
                <div class="field"><label>IMAP Host</label><input name="imap_host" value="<?= e($account['imap_host']) ?>" placeholder="mail.domain.co.za"></div>
                <div class="field"><label>IMAP Port</label><input name="imap_port" type="number" value="<?= e($account['imap_port'] ?: 993) ?>" min="1" max="65535"></div>
                <div class="field"><label>Encryption</label><select name="imap_encryption"><option value="ssl" <?= $account['imap_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option><option value="starttls" <?= $account['imap_encryption'] === 'starttls' ? 'selected' : '' ?>>STARTTLS</option><option value="none" <?= $account['imap_encryption'] === 'none' ? 'selected' : '' ?>>None</option></select></div>
                <div class="field"><label>Username</label><input name="imap_username" value="<?= e($account['imap_username'] ?: $account['email']) ?>"></div>
                <div class="field"><label>Password</label><input name="imap_password" type="password" placeholder="<?= $account['imap_password'] ? 'Leave blank to keep current password' : '' ?>"></div>
                <div class="actions"><button type="submit">Save Inbox</button></div>
            </div>
        </form>
    <?php endforeach; ?>
    <?php if (! $accounts): ?><p class="muted">Add an account first, then configure inbox access.</p><?php endif; ?>
</section>
