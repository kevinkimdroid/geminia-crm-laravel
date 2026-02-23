# Geminia CRM – Production Deployment

## Before First Deploy

1. **Update `.env` on the server:**
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-production-domain.com
   ```

2. **PHP 7.4** or **8.0** is required.

3. **Writable directories** (web server user, e.g. `www-data`):
   ```
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

## Deploy Commands

### Option A: Use deploy script (Linux/macOS)
```bash
chmod +x deploy.sh
./deploy.sh
```

### Option B: Manual steps
```bash
composer install --no-dev --optimize-autoloader
npm ci --omit=dev
npm run build

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan storage:link
```

### Option C: Composer (cache-only, after composer install + npm run build)
```bash
composer run deploy
```
Or just optimize caches: `composer run optimize`

## After Deploy

- If config changes: `php artisan config:cache`
- If routes change: `php artisan route:cache`
- If views change: `php artisan view:cache`

## Quick Cache Clear (if issues)
```bash
php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan cache:clear
```
