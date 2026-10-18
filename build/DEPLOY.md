# Deployment — movie-theatre in-seat ordering

Backend only. The customer/driver/restaurant apps are **not** in this zip: their
changes (the seat QR scanner, deep links, interval labels) ship as new store
builds, not as a server upload.

---

## What this contains

Every `admin-panel/` file that differs between the deployed `main` branch and
`feature/theatre-in-seat-ordering` — 177 files.

That is **more than the theatre feature alone**, on purpose. `routes/web.php`
and `routes/api.php` accumulated `use` statements for several modules that were
developed in parallel (ads, country-tax strategies, branch fleet & gigs, live
order board). Laravel resolves every route's controller when routes load, so
shipping the new routes files without those controllers would leave a server
that cannot boot at all. They travel together or not at all.

If any of those other modules are **not** meant to go live yet, stop and say so
before deploying this — it needs to be unpicked at the source, not at the zip.

## What is deliberately excluded

| Excluded | Why |
|---|---|
| `.env` | Never shipped. New variables are listed below. |
| `database/database.sqlite` | The **development** database. It is in the diff because it is tracked in git; uploading it would overwrite live data. |
| `vendor/`, `node_modules/` | No dependency changes in this work — `composer.json` and `composer.lock` are untouched. |

---

## Steps

**1. Back up first**

```bash
mysqldump -u USER -p DATABASE > backup-$(date +%F).sql   # or your usual method
tar -czf app-backup-$(date +%F).tar.gz /path/to/laravel-root
```

**2. Upload**

Extract the zip, then copy the **contents of the `admin-panel/` folder** into
your Laravel root (the directory holding `artisan`). The prefix is kept so the
zip cannot be unpacked over the wrong directory by accident.

**3. Remove one stale file**

A view moved, and a zip cannot express a deletion. The old copy must go, or the
vendor bank-details page can resolve to the wrong template:

```bash
rm -f resources/views/admin/vendor/bank-details.blade.php
```

(The replacement is `resources/views/admin/vendor-accounts/bank-details.blade.php`,
included in the zip.)

**4. Run the migrations**

```bash
php artisan migrate --force
```

This adds `theatre_shows` and the interval-delivery columns, and backfills
`orders.released_at` from `created_at` so existing orders are treated as already
released. Without the backfill every historical order would look "held" and
disappear from restaurant screens — so do not skip it or run it partially.

**5. Add the scheduler entry, if the cron is not already installed**

Interval orders are withheld from the kitchen until 20 minutes before the
interval, and a scheduled scan releases them. **Without a working cron, interval
orders are paid for and never cooked.**

```cron
* * * * * cd /path/to/laravel-root && php artisan schedule:run >> /dev/null 2>&1
```

Verify it is wired up:

```bash
php artisan schedule:list | grep theatre
# expect: theatre:release-held-orders  ... every minute
```

**6. Add the new `.env` variables**

```env
# Android App Links / iOS Universal Links for the seat QR stickers.
ANDROID_APP_PACKAGE=com.adgraph.yumma
ANDROID_APP_SHA256_FINGERPRINTS=
IOS_APP_BUNDLE_ID=com.adgraph.yumma
IOS_APP_TEAM_ID=
```

- `ANDROID_APP_SHA256_FINGERPRINTS` — comma-separated. With Play App Signing the
  fingerprint that matters is Google's **App signing key certificate**
  (Play Console → Test and release → Setup → App signing), *not* the upload key.
  List both so sideloaded and internal-test builds keep working. Leaving this
  blank falls back to the upload key baked into `config/applinks.php`.
- `IOS_APP_TEAM_ID` — required, no default. Until it is set,
  `/.well-known/apple-app-site-association` returns 404 on purpose: iOS caches
  that file, so serving a wrong one breaks universal links for everyone who
  fetched it, long after the mistake is fixed.

**7. Clear caches**

```bash
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache   # only if you cache in production
```

---

## Verify

```bash
# The two OS verification files must return JSON over HTTPS, no redirect, no auth.
curl -i https://yumma.in/.well-known/assetlinks.json
curl -i https://yumma.in/.well-known/apple-app-site-association
```

If your web server handles `/.well-known/` itself for certbot/ACME, make sure it
does not shadow these two Laravel routes.

Then, in the browser:

1. Sign in as a theatre owner → **Theatre panel → Screens → QR Stickers**.
   A printable sheet of one QR per seat should render.
2. Open any sticker's URL. The dark seat-ordering page should load and list the
   theatre's stalls.
3. Tamper with the last path segment (the signature). It must return **404** —
   that check is what stops someone ordering to a seat they are not in.
4. **Theatre panel → Show Times** → add a show with an interval an hour out,
   then place an interval order. It should appear under Orders as
   "Interval H:MM — Held from the kitchen" and be invisible to the stall until
   the release scan runs.

## Rolling back

Nothing here is destructive to existing data: the migration only adds a table
and columns. To roll back the code, restore the backup from step 1. To roll back
the schema:

```bash
php artisan migrate:rollback --step=1
```
