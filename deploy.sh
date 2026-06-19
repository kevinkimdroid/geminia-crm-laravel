#!/bin/bash
# Production deployment script for Geminia CRM
# Run this on the server after uploading code and before switching traffic

set -e

echo "=== Geminia CRM - Production Deploy ==="

# 1. Install production dependencies (no dev packages)
echo "[1/7] Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Build frontend assets (Vite)
echo "[2/7] Building frontend assets..."
npm ci --omit=dev
npm run build

# 3. Ensure storage directories exist and are writable by the web server
echo "[3/9] Ensuring storage directories and permissions..."
php artisan storage:ensure || true
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if id www-data &>/dev/null; then
  WEB_USER=www-data
elif id apache &>/dev/null; then
  WEB_USER=apache
elif id nginx &>/dev/null; then
  WEB_USER=nginx
else
  WEB_USER=$(whoami)
fi
if [ "$(id -u)" -eq 0 ]; then
  chown -R "${WEB_USER}:${WEB_USER}" storage bootstrap/cache
else
  sudo chown -R "${WEB_USER}:${WEB_USER}" storage bootstrap/cache 2>/dev/null || true
fi
chmod -R ug+rwx storage bootstrap/cache
find storage/framework/cache/data -type d -exec chmod ug+rwx {} + 2>/dev/null || true

# 4. Clear all caches first (in case of stale cached config)
echo "[4/9] Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# 5. Run migrations (creates cache table when CACHE_STORE=database)
echo "[5/9] Running migrations..."
php artisan migrate:sync-existing 2>/dev/null || php scripts/sync-existing-migrations.php 2>/dev/null || true
php artisan migrate --force --no-interaction || true

# 6. Create production caches (faster response)
echo "[6/9] Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Storage link (if not exists)
echo "[7/9] Ensuring storage link..."
php artisan storage:link 2>/dev/null || true

# 8. Permissions reminder
echo "[8/9] Verifying cache configuration..."
if grep -q '^CACHE_STORE=file' .env 2>/dev/null; then
  echo "WARNING: CACHE_STORE=file in .env — set CACHE_STORE=database to avoid storage/framework/cache errors."
else
  echo "CACHE_STORE is database or redis (recommended for production)."
fi

echo "[9/9] Done!"
echo ""
echo "REMINDER: storage/ and bootstrap/cache/ must be owned by the web server user (${WEB_USER})."
echo "Recommended .env: CACHE_STORE=database (then run: php artisan config:cache)"
echo ""
echo "Check .env has: APP_ENV=production, APP_DEBUG=false, APP_URL=<your-domain>"
