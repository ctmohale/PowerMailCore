# PowerMail Core

PowerMail Core is now a plain PHP + MySQL app for simple cPanel hosting. It has one admin dashboard and one REST API endpoint for sending template emails through connected SMTP accounts.

## Structure

- `index.php` - front controller
- `.htaccess` - routes requests and blocks private files
- `app/` - core PHP classes, controllers, services, routes
- `views/` - admin screens
- `assets/` - CSS and public assets
- `storage/` - logs/cache only
- `config.example.php` - copy to `config.php` on the server
- `install.sql` - MySQL tables and starter data

## cPanel Install

1. Create a MySQL database and database user in cPanel.
2. Open phpMyAdmin and import `install.sql`.
3. Copy `config.example.php` to `config.php`.
4. Update `config.php` with your app URL and MySQL details.
5. Point your domain or subdomain document root to this project folder.
6. Enable these PHP extensions in cPanel: `pdo_mysql`, `openssl`, `mbstring`, and `imap` if you want inbox sync.
7. Visit `/login`.

Default login after importing `install.sql`:

```text
Email: admin@powermail.local
Password: password
```

Change that password before using the app for real.

## Local SQLite Setup

For quick local testing without installing MySQL:

```bash
cp config.sqlite.example.php config.php
php setup-sqlite.php
php -S 127.0.0.1:8000 dev-router.php
```

Then open `http://127.0.0.1:8000/login`.

## Git Deployment

For the easiest continuous deployment on cPanel, clone this repository directly into the folder used as the domain or subdomain document root. Then every cPanel Git pull updates the live PHP files without Composer, NPM, or Laravel build steps.

Recommended cPanel shape:

```text
/home/beestac1/repositories/PowerMailCore
```

Then set a subdomain document root to:

```text
/home/beestac1/repositories/PowerMailCore
```

Use a subdomain like `mailcore.yourdomain.co.za` instead of a subfolder, because the app uses root paths such as `/assets/css/app.css`.

## REST API

Create an API key inside the dashboard, then call:

```http
POST /api/send
Content-Type: application/json
```

```json
{
  "api_key": "pmc_your_key",
  "from_email": "info@beestack.co.za",
  "to": "client@gmail.com",
  "subject": "Welcome to BeeStack",
  "template_key": "welcome",
  "data": {
    "name": "John"
  }
}
```

The API key, sending account, and template must belong to the same client.
