<?php

declare(strict_types=1);

$configPath = __DIR__.'/config.php';

if (! is_file($configPath)) {
    copy(__DIR__.'/config.sqlite.example.php', $configPath);
}

$config = require $configPath;

if (($config['database']['driver'] ?? 'mysql') !== 'sqlite') {
    fwrite(STDERR, "config.php is not using the sqlite driver. Copy config.sqlite.example.php to config.php first.\n");
    exit(1);
}

$path = $config['database']['path'] ?? __DIR__.'/storage/powermail.sqlite';
$directory = dirname($path);

if (! is_dir($directory)) {
    mkdir($directory, 0775, true);
}

$pdo = new PDO('sqlite:'.$path);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec((string) file_get_contents(__DIR__.'/install.sqlite.sql'));

echo "SQLite database ready: {$path}\n";
