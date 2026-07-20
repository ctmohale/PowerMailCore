# cPanel React/Node Deployment

PowerMail runs as one Node.js application. Express serves the API and the compiled React app from the same domain.

## Requirements

- A cPanel account with Terminal or SSH access.
- **Setup Node.js App** or **Application Manager** enabled by the hosting provider.
- Node.js 20 or newer.
- A domain or subdomain with SSL, for example `mailcore.example.com`.

Do not use `public_html` as the application root. Keep the source, `.env`, and SQLite database in a private home-directory path such as `/home/CPANEL_USER/powermail-core`.

## 1. Upload The Repository

Use **Git Version Control** to clone the repository into:

```text
/home/CPANEL_USER/powermail-core
```

Alternatively, upload a release archive and extract it there. Make sure the current changes are committed and pushed before deploying with Git.

## 2. Configure Production

From cPanel Terminal:

```bash
cd /home/CPANEL_USER/powermail-core
cp .env.cpanel.example .env
```

Edit `.env` and replace every placeholder:

```dotenv
APP_NAME="PowerMail Core"
NODE_ENV=production
NODE_PUBLIC_BASE_URL=https://mailcore.example.com
REACT_WEB_ORIGIN=https://mailcore.example.com
VITE_API_BASE_URL=https://mailcore.example.com/api
NODE_AUTH_SECRET=REPLACE_WITH_RANDOM_SECRET
NODE_ENCRYPTION_KEY=base64:REPLACE_WITH_32_BYTE_BASE64_KEY
DB_CONNECTION=sqlite
DB_DATABASE=/home/CPANEL_USER/powermail-core/database/database.sqlite
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4.1-mini
OPENAI_BASE_URL=https://api.openai.com/v1
```

Generate new secrets for a new installation:

```bash
openssl rand -hex 32
openssl rand -base64 32
```

Prefix the second value with `base64:` when setting `NODE_ENCRYPTION_KEY`.

For an existing PowerMail database, use the same encryption key as the existing installation. Changing it makes stored SMTP and IMAP passwords unreadable.

## 3. Preserve Or Create The Database

For the existing installation, upload `database/database.sqlite` separately because it is not normally committed to Git. Back it up before every deployment.

For a new installation, the deploy script creates an empty SQLite database from `database/schema.sql`.

The application user needs write access:

```bash
chmod 750 database
chmod 640 database/database.sqlite
```

## 4. Install And Build

Run:

```bash
cd /home/CPANEL_USER/powermail-core
bash scripts/cpanel-deploy.sh
```

This command installs npm packages, builds React, initializes SQLite when necessary, and creates `tmp/restart.txt` for Passenger.

If `better-sqlite3` cannot install, ask the hosting provider to enable the Node.js build tools required for native npm packages.

## 5. Register The Node Application

Open **Setup Node.js App** or **Application Manager** and configure:

| Setting | Value |
| --- | --- |
| Node.js version | 20 or newer |
| Application mode | Production |
| Application root | `powermail-core` |
| Application URL | `https://mailcore.example.com` |
| Startup file | `apps/api/src/server.js` |

Do not configure a fixed production port. cPanel Passenger supplies the port to the app.

After saving, click **Restart Application**. If that control is unavailable, run:

```bash
mkdir -p /home/CPANEL_USER/powermail-core/tmp
touch /home/CPANEL_USER/powermail-core/tmp/restart.txt
```

## 6. Verify

Open these URLs:

```text
https://mailcore.example.com/api/health
https://mailcore.example.com/
https://mailcore.example.com/book/CLIENT-SLUG
```

The health endpoint should return JSON containing `"ok":true` and `"runtime":"node"`.

## Future Updates

After pulling a new version, run:

```bash
cd /home/CPANEL_USER/powermail-core
git pull --ff-only
bash scripts/cpanel-deploy.sh
```

The checked-in `.cpanel.yml` can also run the deployment script through cPanel **Git Version Control > Pull or Deploy > Deploy HEAD Commit**. cPanel requires `.cpanel.yml` at the repository root and a clean working tree for this deployment method.
