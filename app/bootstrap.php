<?php

declare(strict_types=1);

use App\Core\Database;

session_start();

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

require __DIR__.'/Support/helpers.php';

$configPath = dirname(__DIR__).'/config.php';

if (! is_file($configPath)) {
    http_response_code(500);
    echo 'Missing config.php. Copy config.example.php to config.php and update database settings.';
    exit;
}

$GLOBALS['config'] = require $configPath;

Database::configure($GLOBALS['config']['database']);

if (($GLOBALS['config']['app']['debug'] ?? false) === true) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
