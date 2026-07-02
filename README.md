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

## Integration API

Create an API key in **API Keys** and enable the abilities your integration needs:

- `send` sends templated emails and lists sending accounts.
- `templates` lists active templates for the key's client.
- `inbox` reads received emails for the key's client.

Keep API keys on your server. Do not expose them in public browser JavaScript.

Use bearer authentication:

```bash
Authorization: Bearer pmc_your_api_key
```

### Send Email

`POST /api/send`

```bash
curl -X POST https://your-app.example.com/api/send \
  -H "Authorization: Bearer pmc_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "from_email": "info@beestack.co.za",
    "to": "client@gmail.com",
    "subject": "Welcome to BeeStack",
    "template_key": "welcome",
    "data": { "name": "John" }
  }'
```

```json
{
  "message": "Email sent.",
  "log_id": 1,
  "status": "sent"
}
```

### Templates

```bash
curl https://your-app.example.com/api/templates \
  -H "Authorization: Bearer pmc_your_api_key"

curl https://your-app.example.com/api/templates/welcome \
  -H "Authorization: Bearer pmc_your_api_key"
```

### Sending Accounts

```bash
curl https://your-app.example.com/api/sending-accounts \
  -H "Authorization: Bearer pmc_your_api_key"
```

Use one of the returned `email` values as `from_email` when calling `/api/send`.

### Received Emails

```bash
curl "https://your-app.example.com/api/inbox?status=unopened&mailbox=inbox" \
  -H "Authorization: Bearer pmc_your_api_key"

curl https://your-app.example.com/api/inbox/123 \
  -H "Authorization: Bearer pmc_your_api_key"

curl -X PATCH https://your-app.example.com/api/inbox/123/opened \
  -H "Authorization: Bearer pmc_your_api_key"
```

Supported inbox filters:

- `status`: `all`, `opened`, `unopened`
- `mailbox`: `inbox`, `spam`, `sent`, `drafts`, `trash`, `archive`

API keys are stored as SHA-256 hashes. SMTP passwords are stored using Laravel's encrypted cast.
