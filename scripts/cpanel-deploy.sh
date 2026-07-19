#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

echo "Deploying PowerMail Core React/Node from: $APP_DIR"

if ! command -v node >/dev/null 2>&1; then
    echo "Node.js is required. Configure a Node.js 20+ application in cPanel first."
    exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
    echo "npm is required to install and build PowerMail Core."
    exit 1
fi

if [ ! -f .env ]; then
    cp .env.cpanel.example .env
    echo "Created .env from .env.cpanel.example. Set production secrets before starting the app."
fi

npm ci --include=dev
npm run build

node --input-type=module -e "import { getDb } from './apps/api/src/database.js'; const db = getDb(); console.log('Database ready:', db.name); db.close();"

mkdir -p tmp
touch tmp/restart.txt

echo "Deployment complete. Configure the cPanel startup file as apps/api/src/server.js."
