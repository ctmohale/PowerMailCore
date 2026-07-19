# Git Deployment

Use `.github/workflows/deploy-cpanel.yml.example` as the starting point for automated cPanel deployment.

Configure these repository secrets:

- `CPANEL_HOST`
- `CPANEL_PORT`
- `CPANEL_USER`
- `CPANEL_SSH_KEY`
- `CPANEL_APP_PATH`

The workflow verifies the React build and Node entry point before uploading. It preserves the production `.env` and SQLite database, runs `scripts/cpanel-deploy.sh`, and restarts the Node application.
