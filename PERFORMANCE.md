# Performance Optimization Guide

## New: Real-time & Background Processing

### Laravel Reverb (WebSockets)
Dashboard updates in real time when tickets, leads, deals, or contacts change.

**Run Reverb** (with dev server):
```bash
php artisan reverb:start
```
Or use `composer run dev` which starts server + reverb + queue + vite.

**Config:** `.env` has `BROADCAST_CONNECTION=reverb`, `REVERB_HOST`, `REVERB_PORT`, etc.

### Database Queues
Heavy jobs (e.g. Excel exports) run in the background. Set `QUEUE_CONNECTION=database` and run:
```bash
php artisan queue:listen --tries=1
```

**Example:** `ExportReportsJob::dispatch()` queues a full reports export to `storage/app/exports/`.

### Laravel Octane (installed, optional)
Octane + RoadRunner keeps PHP in memory for 3–5× faster requests. **Requires PHP `sockets` extension.**

**Windows:** Enable in `php.ini`: `extension=sockets` then restart Apache/PHP.
```bash
composer require spiral/roadrunner-http spiral/roadrunner-cli
php artisan octane:install
# Choose RoadRunner when prompted
php artisan octane:start --server=roadrunner
```

---

## Slow page transitions

If navigating between pages (Dashboard → Contacts → Clients → Client detail) feels slow:

1. **Loading bar** – A thin blue bar at the top now shows immediately when you click a link or submit a search. This gives instant feedback so the app feels more responsive.

2. **Reduce debug overhead** – Set `APP_DEBUG=false` in production. Debug mode adds query logging and stack traces.

3. **Laravel Octane** – Biggest win for persistent slowness. Keeps PHP in memory for 3–5× faster requests. See [Laravel Octane](#laravel-octane-biggest-win-for-persistent-slowness) below.

4. **ERP API** – If using `erp_http`, the Clients page calls the ERP API on each load (unless cached). Ensure the ERP API is running on the same machine or a fast network. Cache TTL: 3 min for first page, 1.5 min for others.

5. **PHP OPcache** – Enable in `php.ini` to cache compiled PHP.

---

## Quick wins (apply these first)

### 1. Enable persistent database connections (remote DB)
If your database is on a different server (e.g. `DB_HOST=10.1.1.64`), add to `.env`:
```
DB_PERSISTENT=true
```
This reuses connections instead of opening a new one per request. **Important for remote DB**.

### 2. Reduce logging in production
```
LOG_LEVEL=info
```
Use `debug` only when troubleshooting. `info` or `warning` reduces disk I/O.

### 3. Disable debug mode in production
```
APP_DEBUG=false
```
Debug mode adds significant overhead (query logging, stack traces, Whoops).
**Note:** Set back to `true` when debugging errors.

### 4. Cache config and routes (run after deploy)
```bash
php artisan optimize
```
Or individually: `config:cache`, `route:cache`, `view:cache`.
Undo for local dev: `php artisan optimize:clear`

---

## What's already optimized

- **Dashboard** – Stats cached for 2 minutes
- **Reports page** – All metrics cached for 2 minutes
- **Deals pipeline value** – Cached for 90 seconds (invalidated on deal changes)
- **Ticket counts** – Cached for 5 minutes
- **Tickets list** – Cached for 3 min (All) / 2 min (filtered tabs)
- **Ticket count totals** – Cached for 5 minutes
- **Module settings** – Cached for 5 minutes
- **Leads today count** – Cached for 2 minutes

---

## Optional: Redis cache (faster than file)

If Redis is available:
```
CACHE_STORE=redis
```
Then run: `php artisan cache:clear` (clears old file cache).

---

## Laravel Octane (biggest win for persistent slowness)

If the app is still slow, **Laravel Octane** keeps PHP in memory and reuses connections. Huge improvement for remote DB:

```bash
composer require laravel/octane
php artisan octane:install
php artisan octane:start --host=0.0.0.0 --port=8000
```

Use Octane instead of `php artisan serve`. Requires Swoole or RoadRunner.

---

## Tickets page slow (3k+ tickets)

1. **Run index script** – `database/sql/tickets_performance_indexes.sql` adds indexes for faster ticket queries.
2. **Warm cache** – Visit `/tickets` once; subsequent loads serve from cache.
3. **Production** – Set `APP_DEBUG=false` to avoid query logging overhead.

## If still slow

1. **Database indexes** – Run `database/sql/tickets_performance_indexes.sql`. Ensure `vtiger_crmentity` has indexes on `crmid`, `deleted`, `setype`.
2. **PHP OPcache** – In `php.ini` add:
   ```ini
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.max_accelerated_files=10000
   opcache.validate_timestamps=0
   ```
   (Use `validate_timestamps=1` in development so file changes apply.)
3. **Laravel Octane** – For high traffic: `composer require laravel/octane`
