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

# 3. Clear all caches first (in case of stale cached config)
echo "[3/7] Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# 4. Run migrations (if any)
echo "[4/7] Running migrations..."
php artisan migrate --force --no-interaction || true

# 5. Create production caches (faster response)
echo "[5/7] Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Storage link (if not exists)
echo "[6/7] Ensuring storage link..."
php artisan storage:link 2>/dev/null || true

# 7. Permissions reminder
echo "[7/7] Done!"
echo ""
echo "REMINDER: Ensure these are writable by web server:"
echo "  - storage/"
echo "  - bootstrap/cache/"
echo ""
echo "Check .env has: APP_ENV=production, APP_DEBUG=false, APP_URL=<your-domain>"
