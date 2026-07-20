# Simple cPanel Installation

PowerMail runs as one Node.js application. The API and React website use the
same domain.

## What You Need

- cPanel **Setup Node.js App** or **Application Manager**
- Node.js 20 or 22
- An HTTPS domain or subdomain
- `PowerMailCore-cPanel.zip`

## 1. Upload The ZIP

1. Open **cPanel > File Manager**.
2. Create `/home/CPANEL_USERNAME/powermail` outside `public_html`.
3. Upload `PowerMailCore-cPanel.zip` into that folder.
4. Extract the ZIP.
5. Confirm that `app.js` and `package.json` are directly inside `powermail`.
6. Create `/home/CPANEL_USERNAME/powermail-data` for the database.

Replace `CPANEL_USERNAME` with the username shown in your cPanel account.

## 2. Create The Node.js App

Open **Setup Node.js App** or **Application Manager** and enter:

| Setting | Value |
| --- | --- |
| Node.js version | 20 or 22 |
| Application mode | Production |
| Application root | `powermail` |
| Application URL | Your HTTPS domain or subdomain |
| Startup file | `app.js` |

Do not create a `PORT` variable. cPanel supplies the correct port.

## 3. Add Environment Variables

Add each variable in the Node.js application screen:

```env
NODE_ENV=production
NODE_PUBLIC_BASE_URL=https://YOUR-DOMAIN
REACT_WEB_ORIGIN=https://YOUR-DOMAIN
NODE_AUTH_SECRET=YOUR_RANDOM_SECRET
NODE_ENCRYPTION_KEY=base64:YOUR_32_BYTE_BASE64_KEY
DB_CONNECTION=sqlite
DB_DATABASE=/home/CPANEL_USERNAME/powermail-data/database.sqlite
ADMIN_NAME=PowerMail Admin
ADMIN_EMAIL=YOUR_ADMIN_EMAIL
ADMIN_PASSWORD=YOUR_NEW_ADMIN_PASSWORD
```

Use the exact old `NODE_ENCRYPTION_KEY` when moving an existing database.
Changing it makes saved SMTP and IMAP passwords unreadable.

For a new installation, generate the secrets in cPanel Terminal:

```bash
node -e "const c=require('crypto'); console.log('NODE_AUTH_SECRET='+c.randomBytes(32).toString('hex')); console.log('NODE_ENCRYPTION_KEY=base64:'+c.randomBytes(32).toString('base64'))"
```

Keep these values private.

## 4. Install And Start

1. Click **Run NPM Install**.
2. Wait until installation finishes successfully.
3. Click **Restart App**.
4. Open `https://YOUR-DOMAIN/api/health`.

A working installation returns JSON containing:

```json
{"ok":true}
```

Then open `https://YOUR-DOMAIN` and sign in.

## Updating Later

1. Download a backup of
   `/home/CPANEL_USERNAME/powermail-data/database.sqlite`.
2. Upload and extract the new cPanel ZIP into the existing `powermail` folder.
3. Click **Run NPM Install**.
4. Click **Restart App**.

The cPanel ZIP excludes databases, `.env` files, Git history, and
`node_modules`, so an application update does not replace the database.
