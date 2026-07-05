# PowerMail Core cPanel Deployment

## 1. Prepare cPanel

Create a subdomain such as:

```text
mailcore.yourdomain.co.za
```

Set the document root to the Laravel public folder:

```text
/home/CPANEL_USER/powermail-core/public
```

Enable these PHP extensions:

```text
bcmath
ctype
curl
dom
fileinfo
filter
hash
imap
json
mbstring
mysqli
mysqlnd
openssl
pdo
pdo_mysql
session
tokenizer
xml
zip
```

Use PHP 8.3 or newer.

For Git deployment, see `GIT_DEPLOYMENT.md`.

## 2. Check The Web PHP Runtime

If you need to prove which PHP runtime the domain is using, temporarily upload
`scripts/deploy-check.php` into the domain's public web root as
`deploy-check.php`, then open:

```text
https://mailcore.yourdomain.co.za/deploy-check.php
```

All required checks should pass. This page does not boot Laravel, so it can
confirm whether the domain's web PHP has the required extensions before the app
loads.

Delete `deploy-check.php` from the public web root after testing.

If cPanel shows an extension as enabled but this page reports it as missing, the
domain is using a different PHP runtime than the one you edited in cPanel, or
the host's PHP build is incomplete. Ask the host to enable the missing extension
for the web PHP handler serving the domain.

Do not hard-code a PHP handler in `public/.htaccess` unless your host explicitly
gives you the correct handler name. Use cPanel `MultiPHP Manager` / `Select PHP
Version` instead.

## 3. Upload Files

Upload `powermail-core-cpanel.zip` into:

```text
/home/CPANEL_USER/powermail-core
```

Extract the ZIP there.

Do not put the whole Laravel app directly inside `public_html` unless your host cannot set the document root. The public web root should be the `public` directory only.

## 4. Create MySQL Database

In cPanel, create:

```text
Database: cpaneluser_powermail
User: cpaneluser_powermail
Password: strong password
```

Assign the database user to the database with all privileges.

## 5. Create `.env`

Copy `.env.cpanel.example` to `.env` on the server and update:

```env
APP_URL=https://powermail.beestack.co.za
MAIL_TRACKING_URL=https://powermail.beestack.co.za
DB_DATABASE=cpaneluser_powermail
DB_USERNAME=cpaneluser_powermail
DB_PASSWORD=your_mysql_password
ADMIN_EMAIL=your_admin_email
ADMIN_PASSWORD=your_admin_password
```

## 6. Run Commands

From cPanel Terminal, inside `/home/CPANEL_USER/powermail-core`, run:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If Composer is not available on cPanel, upload the ZIP that includes the `vendor` folder. Then skip `composer install`.

If cPanel Terminal is not available, use cPanel `Git Version Control` and click
`Pull or Deploy`, or upload a package that already includes `vendor/` and the
generated Composer autoload files from your local machine.

## 7. Permissions

If storage/cache errors appear, run:

```bash
chmod -R 775 storage bootstrap/cache
```

## 8. Login

Open:

```text
https://mailcore.yourdomain.co.za/login
```

Use the `ADMIN_EMAIL` and `ADMIN_PASSWORD` you placed in `.env` before running `php artisan db:seed --force`.

## 9. Inbox Setup

For each email account:

```text
IMAP host: mail.yourdomain.co.za
IMAP port: 993
IMAP encryption: SSL
Username: full email address
Password: mailbox password
```

Then go to `Inbox` and click `Sync All Accounts`.
