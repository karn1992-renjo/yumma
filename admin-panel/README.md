# FoodFlow Admin Panel

This folder contains the Laravel backend and admin dashboard for the FoodFlow delivery platform.

## Setup

1. Install backend dependencies and frontend assets:

```bash
cd admin-panel
composer install
npm install
```

2. If you plan to use admin panel Firebase notifications or Firebase helpers, install the Firebase PHP SDK:

```bash
composer require kreait/firebase-php
```

3. Copy the environment template:

```bash
cp .env.example .env
```

4. Update `.env` values for:
- `APP_URL`
- database credentials
- `GOOGLE_MAPS_API_KEY`
- Pusher credentials
- payment gateway keys
- `CODECYNON_CLIENT_ID`
- `CODECYNON_CLIENT_SECRET`
- `CODECYNON_API_BASE_URL`
- `CODECYNON_PURCHASE_CODE`

5. Configure Firebase settings using the admin settings page and upload service account credentials. The repo does not store real Firebase credentials.

6. Generate the application key:

```bash
php artisan key:generate
```

7. Run database migrations and seeders:

```bash
php artisan migrate --seed
```

8. Start the application:

```bash
php artisan serve
npm run dev
```

The app typically runs at `http://127.0.0.1:8000`.

## Admin panel package list

The admin panel currently relies on these backend packages:
- `laravel/framework`
- `laravel/jetstream`
- `laravel/sanctum`
- `laravel/tinker`
- `livewire/livewire`
- `spatie/laravel-permission`
- `maatwebsite/excel`
- `barryvdh/laravel-dompdf`
- `spatie/laravel-activitylog`
- `intervention/image`
- `laravel/cashier`
- `laravel/ui`
- `pusher/pusher-php-server`

The frontend build uses these npm packages:
- `vue`, `alpinejs`, `chart.js`, `datatables.net-dt`, `laravel-echo`, `pusher-js`
- `tailwindcss`, `@tailwindcss/forms`, `@tailwindcss/typography`, `@tailwindcss/vite`, `vite`, `laravel-vite-plugin`, `postcss`, `autoprefixer`

## Notes

- Jetstream + Livewire is the default UI stack for this admin panel. Breeze is optional and requires manual installation.
- Do not commit production credentials or Firebase service account files.
- Use `.env.example` as the configuration reference.

## Codecynon License Verification

Codecynon purchase code verification is now integrated into the admin panel.

- First-time installation verifies the purchase code automatically.
- The verification status is refreshed daily via scheduler.
- Run a manual check with:

```bash
php artisan codecynon:check
```

## Scheduler

Scheduled tasks are defined in `routes/console.php`.

Use cron to run the Laravel scheduler every minute:

```bash
* * * * * cd /path/to/admin-panel && php artisan schedule:run >> /dev/null 2>&1
```

## Useful commands

```bash
php artisan migrate --seed
php artisan codecynon:check
php artisan serve
npm run dev
```
