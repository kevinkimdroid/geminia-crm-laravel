# Performance Optimization Guide

This document describes performance optimizations applied and recommended settings for faster response times.

## Applied Optimizations

### 1. Session & Cache Drivers
- **Default changed from `database` to `file`** — File storage is significantly faster than database for session and cache on single-server setups.
- Update your `.env`: `SESSION_DRIVER=file` and `CACHE_STORE=file`
- For multi-server or high-traffic setups, use Redis: `SESSION_DRIVER=redis`, `CACHE_STORE=redis`

### 2. Contact Show Page
- **Deferred loading** — Deals, activities, comments, and followups are only loaded when viewing the Summary tab, not on other tabs (tickets, policies, emails, etc.).

### 3. Caching
- **Contacts list** — Cached for 45 seconds per page and owner filter
- **Tickets list** — Cached for 90–120 seconds, including per-user filtered views
- **Dashboard stats** — Already cached (120s)
- **Layout data** — User role and modules cached for 10 minutes

### 4. Database
- **Persistent connections** — Set `DB_PERSISTENT=true` in `.env` to reuse MySQL connections (reduces connection overhead)

## Recommended .env Settings

```env
# Faster than database for single-server
SESSION_DRIVER=file
CACHE_STORE=file

# Optional: reuse DB connections
DB_PERSISTENT=true
```

## For Best Performance Under Load

1. **Use Redis** for session and cache when you have multiple app servers or high concurrency
2. **Enable OPcache** in PHP (`opcache.enable=1`) — Laravel benefits greatly from bytecode caching
3. **Queue heavy tasks** — Use `QUEUE_CONNECTION=database` or Redis and run `php artisan queue:work` for background jobs
4. **CDN** — Serve static assets (CSS, JS, images) from a CDN
5. **Database indexes** — Ensure vtiger tables have indexes on frequently queried columns (e.g. `vtiger_crmentity.deleted`, `vtiger_crmentity.smownerid`, `vtiger_troubletickets.status`)
