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

if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction
elif [ ! -f "vendor/autoload.php" ]; then
    echo "Composer is not available and vendor/autoload.php is missing."
    echo "Install Composer on cPanel or deploy a package that includes vendor/."
    exit 1
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan optimize:clear
php artisan migrate --force

if grep -q '^DEPLOY_RUN_SEED=true' .env; then
    php artisan db:seed --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Deployment complete."
