<?php

$appRoot = dirname(__DIR__);

$requiredExtensions = [
    'ctype',
    'curl',
    'dom',
    'fileinfo',
    'filter',
    'mbstring',
    'openssl',
    'pdo',
    'pdo_mysql',
    'session',
    'tokenizer',
    'xml',
];

$optionalExtensions = [
    'imap',
    'zip',
];

$checks = [];

foreach ($requiredExtensions as $extension) {
    $checks[] = [
        'label' => 'PHP extension: '.$extension,
        'ok' => extension_loaded($extension),
        'required' => true,
        'detail' => extension_loaded($extension) ? 'loaded' : 'missing',
    ];
}

foreach ($optionalExtensions as $extension) {
    $checks[] = [
        'label' => 'PHP extension: '.$extension,
        'ok' => extension_loaded($extension),
        'required' => false,
        'detail' => extension_loaded($extension) ? 'loaded' : 'optional',
    ];
}

$checks[] = [
    'label' => 'PHP function: mb_split',
    'ok' => function_exists('mb_split'),
    'required' => true,
    'detail' => function_exists('mb_split') ? 'available' : 'missing',
];

$paths = [
    '.env' => $appRoot.'/.env',
    'vendor/autoload.php' => $appRoot.'/vendor/autoload.php',
    'storage' => $appRoot.'/storage',
    'storage/logs' => $appRoot.'/storage/logs',
    'storage/framework/views' => $appRoot.'/storage/framework/views',
    'bootstrap/cache' => $appRoot.'/bootstrap/cache',
];

foreach ($paths as $label => $path) {
    $isDirectory = is_dir($path);
    $exists = $isDirectory || is_file($path);
    $shouldBeWritable = in_array($label, ['storage', 'storage/logs', 'storage/framework/views', 'bootstrap/cache'], true);

    $checks[] = [
        'label' => 'Path: '.$label,
        'ok' => $exists && (! $shouldBeWritable || is_writable($path)),
        'required' => true,
        'detail' => $exists
            ? ($shouldBeWritable ? (is_writable($path) ? 'writable' : 'not writable') : 'exists')
            : 'missing',
    ];
}

$failedRequired = array_filter($checks, fn ($check) => $check['required'] && ! $check['ok']);

http_response_code($failedRequired ? 500 : 200);
header('Content-Type: text/plain; charset=UTF-8');

echo "PowerMail Core deployment check\n";
echo "PHP version: ".PHP_VERSION."\n";
echo "PHP SAPI: ".PHP_SAPI."\n";
echo "Loaded ini: ".(php_ini_loaded_file() ?: 'none')."\n\n";

foreach ($checks as $check) {
    $status = $check['ok'] ? 'OK' : ($check['required'] ? 'FAIL' : 'WARN');
    echo str_pad($status, 5).' '.$check['label'].' - '.$check['detail']."\n";
}

echo "\n";
echo $failedRequired ? "Required checks failed.\n" : "Required checks passed.\n";
