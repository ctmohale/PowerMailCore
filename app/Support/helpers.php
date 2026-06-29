<?php

declare(strict_types=1);

use App\Core\Database;

function config_value(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'] ?? [];

    foreach (explode('.', $key) as $segment) {
        if (! is_array($value) || ! array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function db(): PDO
{
    return Database::pdo();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path): string
{
    $base = rtrim((string) config_value('app.url', ''), '/');
    $path = '/'.ltrim($path, '/');

    return $base.$path;
}

function view(string $view, array $data = [], string $layout = 'layouts/app'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__, 2).'/views/'.str_replace('.', '/', $view).'.php';
    $content = ob_get_clean();
    require dirname(__DIR__, 2).'/views/'.str_replace('.', '/', $layout).'.php';
}

function redirect(string $path): never
{
    header('Location: '.$path);
    exit;
}

function back(): never
{
    redirect($_SERVER['HTTP_REFERER'] ?? '/dashboard');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

function flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

function keep_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function csrf_token(): string
{
    if (empty($_SESSION['_token'])) {
        $_SESSION['_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">';
}

function csrf_valid(string $token): bool
{
    return hash_equals($_SESSION['_token'] ?? '', $token);
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return Database::fetch('select * from users where id = ?', [$_SESSION['user_id']]);
}

function require_fields(array $data, array $fields): array
{
    $errors = [];

    foreach ($fields as $field => $label) {
        if (trim((string) ($data[$field] ?? '')) === '') {
            $errors[] = $label.' is required.';
        }
    }

    return $errors;
}

function fail_form(array $errors, array $old = []): never
{
    foreach ($errors as $error) {
        flash('error', $error);
    }

    keep_old($old);
    back();
}

function str_slug(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '', '-'));

    return $slug !== '' ? $slug : 'item';
}

function encrypt_secret(?string $value): ?string
{
    if ($value === null || $value === '') {
        return $value;
    }

    $key = hash('sha256', (string) config_value('app.key'), true);
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    if ($ciphertext === false) {
        return $value;
    }

    return 'enc:'.base64_encode($iv.$ciphertext);
}

function decrypt_secret(?string $value): ?string
{
    if ($value === null || $value === '' || ! str_starts_with($value, 'enc:')) {
        return $value;
    }

    $decoded = base64_decode(substr($value, 4), true);

    if ($decoded === false || strlen($decoded) <= 16) {
        return null;
    }

    $key = hash('sha256', (string) config_value('app.key'), true);
    $iv = substr($decoded, 0, 16);
    $ciphertext = substr($decoded, 16);
    $plain = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    return $plain === false ? null : $plain;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
