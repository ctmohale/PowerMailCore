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

## 2. Upload Files

Upload `powermail-core-cpanel.zip` into:

```text
/home/CPANEL_USER/powermail-core
```

Extract the ZIP there.

Do not put the whole Laravel app directly inside `public_html` unless your host cannot set the document root. The public web root should be the `public` directory only.

## 3. Create MySQL Database

In cPanel, create:

```text
Database: cpaneluser_powermail
User: cpaneluser_powermail
Password: strong password
```

Assign the database user to the database with all privileges.

## 4. Create `.env`

Copy `.env.cpanel.example` to `.env` on the server and update:

```env
APP_URL=https://mailcore.yourdomain.co.za
DB_DATABASE=cpaneluser_powermail
DB_USERNAME=cpaneluser_powermail
DB_PASSWORD=your_mysql_password
ADMIN_EMAIL=your_admin_email
ADMIN_PASSWORD=your_admin_password
```

## 5. Run Commands

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

## 6. Permissions

If storage/cache errors appear, run:

```bash
chmod -R 775 storage bootstrap/cache
```

## 7. Login

Open:

```text
https://mailcore.yourdomain.co.za/login
```

Use the `ADMIN_EMAIL` and `ADMIN_PASSWORD` you placed in `.env` before running `php artisan db:seed --force`.

## 8. Inbox Setup

For each email account:

```text
IMAP host: mail.yourdomain.co.za
IMAP port: 993
IMAP encryption: SSL
Username: full email address
Password: mailbox password
```

Then go to `Inbox` and click `Sync All Accounts`.
