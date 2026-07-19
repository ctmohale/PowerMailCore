# cPanel React/Node Deployment

## Application Setup

1. Open **Setup Node.js App** in cPanel.
2. Choose Node.js 20 or newer.
3. Set the application root to the PowerMail Core repository.
4. Set the startup file to `apps/api/src/server.js`.
5. Set the application URL to the PowerMail domain or subdomain.

## Environment

Create `.env` from `.env.cpanel.example` and configure the public URL, database path, authentication secret, encryption key, and optional OpenAI credentials.

For an existing database, keep its previous encryption key as `NODE_ENCRYPTION_KEY`; otherwise stored SMTP and IMAP passwords cannot be decrypted.

## Deploy

From the cPanel terminal:

```bash
cd /home/CPANEL_USER/powermail-core
bash scripts/cpanel-deploy.sh
```

The script installs npm dependencies, builds React, initializes SQLite when needed, and touches `tmp/restart.txt` for Passenger-based Node hosting.

The SQLite file must be writable by the Node application user. Back up `database/database.sqlite` before production upgrades.

## Health Check

After the application starts, open:

```text
https://mailcore.yourdomain.co.za/api/health
```

The dashboard is served from the same Node process at the application root.
