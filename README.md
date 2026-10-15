# Restaurant Analytics Demo

An end-to-end restaurant demo with a reusable browser tracking SDK, a Laravel analytics API, MySQL migrations, server-side order events, and a React dashboard.

## Project layout

- `backen-api/` is the Laravel 13 API. All analytics and order migrations live in `backen-api/database/migrations/`.
- `frontend-app/` is the React 19 + Vite restaurant demo and dashboard.

## Local setup

Requirements: PHP 8.3+, Composer, Node.js 20.19+, npm, MySQL 8+ (or compatible MariaDB), and PHP's `pdo_mysql` extension.

Create a database, configure Laravel, and start the API:

```sh
mysql -u root -p -e "CREATE DATABASE restaurant_analytics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cd backen-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```

In another terminal, install and start the React app:

```sh
cd frontend-app
npm install
npm run dev -- --host 0.0.0.0
```

Open the Vite URL (normally `http://localhost:5173`). The dev server proxies `/api` to Laravel at `http://127.0.0.1:8000`; set `API_PROXY_TARGET` to change that. For a deployed frontend, set `VITE_API_BASE_URL` to the API base URL and configure the API's CORS policy for that origin.

The generated local environment uses `ANALYTICS_SERVER_KEY=local-demo-key`; replace it with a long random secret outside local development. The key is only sent by the Laravel server and is never included in frontend assets. Set `ANALYTICS_SERVER_EVENT_URL` when the server-event endpoint is not `http://127.0.0.1:8000/api/server-events`.

Run backend feature tests with `cd backen-api && php artisan test`; they use the in-memory SQLite test connection and require `pdo_sqlite`. Run the frontend production build with `cd frontend-app && npm run build`.

## Event flow and data model

The browser SDK assigns a random visitor ID in local storage and session ID in session storage, and sends `page_view` plus custom interaction events to `POST /api/events`. It uses `crypto.randomUUID`, keepalive requests, and in-memory identity fallback when storage is unavailable. The order endpoint accepts menu IDs and quantities, recalculates prices from the server menu, commits the order, then the Laravel `ServerAnalyticsClient` sends an `order_created` event to the authenticated server-event endpoint. Analytics delivery is best-effort and cannot roll back an order.

`analytics_events` stores a unique `event_id`, event name/source, UTC occurrence and receipt times, optional visitor/session IDs and page path, and JSON properties. Duplicate event IDs are ignored. `restaurant_orders` stores server-priced lines and the total in integer cents. Events are indexed by time, visitor/time, and event/page. The dashboard calls `GET /api/analytics?from=YYYY-MM-DD&to=YYYY-MM-DD` with inclusive UTC bounds, capped at 366 days. It reports page views, distinct browser visitors, orders, daily event totals, and popular pages.

## Assumptions and limitations

- No customer details, IP addresses, or payment information are collected. Visitor IDs are pseudonymous and not a cross-device identity.
- Browser event ingestion is public by design and validated; server ingestion requires a shared secret. Aggregate dashboard reads are public in this demo and should be put behind application authentication before deployment.
- There is no durable analytics retry queue, retention cleanup, bot filtering, authentication, payment handling, or inventory management. Delivery failures are logged; MySQL is the intended production/local store, while SQLite is used only in isolated tests.
- This workspace currently lacks `pdo_mysql` and a MySQL server, so the Laravel feature suite and React build can be verified here, but live MySQL migrations/API smoke tests require those local prerequisites.