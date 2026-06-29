<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(config_value('app.name', 'PowerMail Core')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="/dashboard">PowerMail Core</a>
            <?php if (current_user()): ?>
                <?php $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
                <nav class="nav" aria-label="Primary">
                    <a href="/dashboard" class="<?= $path === '/dashboard' || $path === '/' ? 'active' : '' ?>">Dashboard</a>
                    <a href="/clients" class="<?= str_starts_with($path, '/clients') ? 'active' : '' ?>">Clients</a>
                    <a href="/domains" class="<?= str_starts_with($path, '/domains') ? 'active' : '' ?>">Domains</a>
                    <a href="/email-accounts" class="<?= str_starts_with($path, '/email-accounts') ? 'active' : '' ?>">Accounts</a>
                    <a href="/email-templates" class="<?= str_starts_with($path, '/email-templates') ? 'active' : '' ?>">Templates</a>
                    <a href="/api-keys" class="<?= str_starts_with($path, '/api-keys') ? 'active' : '' ?>">API Keys</a>
                    <a href="/inbox" class="<?= str_starts_with($path, '/inbox') ? 'active' : '' ?>">Inbox</a>
                    <a href="/email-logs" class="<?= str_starts_with($path, '/email-logs') ? 'active' : '' ?>">Logs</a>
                </nav>
                <div class="user-actions">
                    <span class="muted"><?= e(current_user()['email']) ?></span>
                    <form method="POST" action="/logout">
                        <?= csrf_field() ?>
                        <button class="secondary" type="submit">Log out</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="<?= current_user() ? 'container' : 'auth-container' ?>">
        <?php foreach (flash_messages() as $type => $messages): ?>
            <?php foreach ($messages as $message): ?>
                <div class="alert <?= e($type) ?>"><?= e($message) ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
</body>
</html>
