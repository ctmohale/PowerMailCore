#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

echo "Deploying PowerMail Core from: $APP_DIR"

if [ ! -f ".env" ]; then
    if [ -f ".env.cpanel.example" ]; then
        cp .env.cpanel.example .env
        echo "Created .env from .env.cpanel.example. Edit .env before using the app."
    else
        echo "Missing .env file and .env.cpanel.example template."
        exit 1
    fi
fi

mkdir -p storage/app/public storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache || true

find bootstrap/cache -type f ! -name '.gitignore' -delete

php_candidates=()

if [ -n "${CPANEL_PHP_BIN:-}" ]; then
    php_candidates+=("$CPANEL_PHP_BIN")
fi

if command -v php >/dev/null 2>&1; then
    php_candidates+=("$(command -v php)")
fi

php_candidates+=(
    /opt/alt/php84/usr/bin/php
    /opt/alt/php83/usr/bin/php
    /opt/alt/php82/usr/bin/php
    /opt/cpanel/ea-php84/root/usr/bin/php
    /opt/cpanel/ea-php83/root/usr/bin/php
    /opt/cpanel/ea-php82/root/usr/bin/php
)

PHP_BIN=""

for candidate in "${php_candidates[@]}"; do
    if [ -x "$candidate" ] && "$candidate" -r 'exit(extension_loaded("pdo") && extension_loaded("pdo_mysql") && extension_loaded("dom") && extension_loaded("mbstring") && function_exists("mb_split") ? 0 : 1);' >/dev/null 2>&1; then
        PHP_BIN="$candidate"
        break
    fi
done

if [ -z "$PHP_BIN" ]; then
    echo "No usable CLI PHP binary found. Laravel deployment requires CLI PHP with PDO, pdo_mysql, DOM, mbstring, and mb_split enabled."
    echo "Ask the host to enable those extensions for the cPanel CLI PHP runtime, or set CPANEL_PHP_BIN to a working PHP binary."
    exit 1
fi

echo "Using PHP binary: $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

required_extensions=(ctype curl dom fileinfo filter mbstring openssl pdo pdo_mysql session tokenizer xml)
missing_extensions=()

for extension in "${required_extensions[@]}"; do
    if ! "$PHP_BIN" -r "exit(extension_loaded('$extension') ? 0 : 1);" >/dev/null 2>&1; then
        missing_extensions+=("$extension")
    fi
done

if [ "${#missing_extensions[@]}" -gt 0 ]; then
    echo "Missing required CLI PHP extension(s): ${missing_extensions[*]}"
    echo "Enable them for the PHP binary above before running deployment commands."
    exit 1
fi

if ! "$PHP_BIN" -r 'exit(function_exists("mb_split") ? 0 : 1);' >/dev/null 2>&1; then
    echo "Missing required CLI PHP function: mb_split"
    echo "Enable native mbstring for the PHP binary above before running deployment commands."
    exit 1
fi

if command -v composer >/dev/null 2>&1; then
    COMPOSER_BIN="$(command -v composer)"

    if "$PHP_BIN" "$COMPOSER_BIN" --version >/dev/null 2>&1; then
        "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
    else
        composer install --no-dev --optimize-autoloader --no-interaction
    fi
elif [ ! -f "vendor/autoload.php" ]; then
    echo "Composer is not available and vendor/autoload.php is missing."
    echo "Install Composer on cPanel or deploy a package that includes vendor/."
    exit 1
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    "$PHP_BIN" artisan key:generate --force
fi

"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force

if grep -q '^DEPLOY_RUN_SEED=true' .env; then
    "$PHP_BIN" artisan db:seed --force
fi

"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo "Deployment complete."
