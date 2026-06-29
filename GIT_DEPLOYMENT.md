# Continuous Deployment With Git

PowerMail Core supports two Git deployment styles.

## Option A: cPanel Git Version Control

This is the simplest cPanel-native option.

1. Create a private GitHub repository.
2. Push this project to the repository.
3. In cPanel, open `Git Version Control`.
4. Clone the repository into:

```text
/home/CPANEL_USER/powermail-core
```

5. Set your subdomain document root to:

```text
/home/CPANEL_USER/powermail-core/public
```

6. Create `.env` on the server from `.env.cpanel.example`.
7. Fill in database, app URL, and admin values.
8. In cPanel `Git Version Control`, click `Pull or Deploy`.

cPanel will read `.cpanel.yml` and run:

```bash
bash scripts/cpanel-deploy.sh
```

That script installs dependencies when Composer is available, runs migrations, and refreshes Laravel caches.

## Option B: GitHub Actions Auto Deploy

Use this if you want deployment to happen automatically every time you push to `main`.

1. Rename:

```text
.github/workflows/deploy-cpanel.yml.example
```

to:

```text
.github/workflows/deploy-cpanel.yml
```

2. In GitHub, open `Settings > Secrets and variables > Actions`.
3. Add these repository secrets:

```text
CPANEL_HOST=server.example.com
CPANEL_PORT=22
CPANEL_USER=your_cpanel_username
CPANEL_SSH_KEY=your_private_ssh_key
CPANEL_APP_PATH=/home/your_cpanel_username/powermail-core
```

4. Push to `main`.

The workflow will:

- run tests
- upload changed files with `rsync`
- run `scripts/cpanel-deploy.sh` on the server

## First Server Setup

Before the first deploy, create the MySQL database/user in cPanel and create `.env` on the server.

Use:

```bash
cp .env.cpanel.example .env
```

Then edit `.env` with real values.

For the first admin seed, add this temporarily:

```env
DEPLOY_RUN_SEED=true
```

After the first deploy succeeds, remove it or set:

```env
DEPLOY_RUN_SEED=false
```

## Important

Never commit `.env`, cPanel passwords, database passwords, or private SSH keys.
