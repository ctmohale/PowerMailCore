# PowerMail Core

PowerMail Core is a React dashboard and Node.js API for multi-company email delivery, inbox syncing, templates, marketing contacts, audiences, campaigns, lead generation, prospect calls, bookings, API keys, and delivery logs.

## Requirements

- Node.js 20 or newer
- npm
- SQLite

## Local Setup

```bash
npm install
cp .env.example .env
npm run dev
```

The development services run at:

- React: `http://127.0.0.1:5174`
- Node API: `http://127.0.0.1:4000`

The existing application data is stored in `database/database.sqlite`. When that file does not exist, the Node API creates a fresh database from `database/schema.sql`.

## Production

```bash
npm ci
npm run build
npm start
```

The Node API serves both `/api` and the production React build from `apps/web/dist`.

Set these production values in `.env`:

```env
NODE_ENV=production
NODE_API_PORT=4000
NODE_PUBLIC_BASE_URL=https://mailcore.example.com
REACT_WEB_ORIGIN=https://mailcore.example.com
VITE_API_BASE_URL=https://mailcore.example.com/api
NODE_AUTH_SECRET=replace-with-a-long-random-secret
NODE_ENCRYPTION_KEY=base64:replace-with-a-base64-encoded-32-byte-key
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

Existing installations should set `NODE_ENCRYPTION_KEY` to their previous application encryption key so saved SMTP and IMAP credentials remain readable.

## Railway Deployment

Deploy the repository root as one Railway service. The root build creates the
React bundle and the Node API serves both the frontend and `/api` from the port
Railway provides.

Use these service settings:

- Build command: `npm ci && npm run build`
- Start command: `npm start`
- Healthcheck path: `/api/health`
- Do not set `PORT`; Railway supplies it automatically.

Set these Railway variables with persistent random values:

```env
NODE_ENV=production
NODE_AUTH_SECRET=replace-with-a-long-random-secret
NODE_ENCRYPTION_KEY=base64:replace-with-a-base64-encoded-32-byte-key
DB_CONNECTION=sqlite
```

Generate suitable secrets locally with `openssl rand -hex 32` and
`openssl rand -base64 32`. Do not change `NODE_ENCRYPTION_KEY` after storing
email credentials.

Attach a Railway Volume to this service at `/data`. The API automatically uses
`RAILWAY_VOLUME_MOUNT_PATH/database.sqlite`, so `DB_DATABASE` is not required.
Without a volume, SQLite data is lost when Railway replaces the deployment.

The recommended single-service setup does not require `VITE_API_BASE_URL`; the
browser uses same-origin `/api`. If the frontend and API are intentionally
deployed as separate services, set `VITE_API_BASE_URL` on the frontend service
to `https://YOUR-BACKEND-DOMAIN/api` before building, and set
`REACT_WEB_ORIGIN` on the backend to the frontend's exact HTTPS origin.

## Integration API

Create an API key in the dashboard and grant the required abilities:

- `send`: send email and list sending accounts
- `templates`: list and read active templates
- `inbox`: list, read, and update inbox messages

Use the key as a bearer token:

```bash
curl https://mailcore.example.com/api/templates \
  -H "Authorization: Bearer pmc_your_api_key"
```

Send a templated email:

```bash
curl -X POST https://mailcore.example.com/api/send \
  -H "Authorization: Bearer pmc_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "from_email": "info@example.com",
    "to": "client@example.com",
    "subject": "Welcome",
    "template_key": "welcome",
    "data": { "name": "Client" }
  }'
```

Keep API keys on trusted servers and never expose them in public browser code.
