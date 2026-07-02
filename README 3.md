# PowerMail Core

PowerMail Core is a Laravel email core for multiple client apps and websites. It has one admin dashboard and one REST API for sending templated emails through configured SMTP accounts.

## MVP Modules

- Clients
- Domains
- SMTP email accounts
- Inbox access for received emails
- Email templates
- API keys
- Email logs

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Default seeded login:

```text
Email: admin@powermailcore.test
Password: password
```

Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` before seeding on a hosted environment.

## cPanel Notes

Use MySQL in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_cpanel_database
DB_USERNAME=your_cpanel_user
DB_PASSWORD=your_cpanel_password
```

Point the domain or subdomain document root to Laravel's `public` directory. After uploading, run:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

For inbox syncing, enable the PHP `imap` extension in cPanel's PHP extension selector. Typical cPanel mailbox settings are:

```text
IMAP host: mail.yourdomain.co.za
IMAP port: 993
IMAP encryption: SSL
Username: full email address
Password: mailbox password
```

## Send Email API

`POST /api/send`

```json
{
  "api_key": "APP_API_KEY",
  "from_email": "info@beestack.co.za",
  "to": "client@gmail.com",
  "subject": "Welcome to BeeStack",
  "template_key": "welcome",
  "data": {
    "name": "John"
  }
}
```

Successful response:

```json
{
  "message": "Email sent.",
  "log_id": 1,
  "status": "sent"
}
```

API keys are stored as SHA-256 hashes. SMTP passwords are stored using Laravel's encrypted cast.
