#!/bin/bash
# Geminia CRM - CentOS 7 one-time server setup
# Run as root: ./setup-centos7.sh

set -e

APP_DIR="${APP_DIR:-/var/www/geminia-crm-laravel}"
WEB_USER="${WEB_USER:-nginx}"

echo "=== Geminia CRM - CentOS 7 Setup ==="
echo "App dir: $APP_DIR"
echo "Web user: $WEB_USER"
echo ""

# 1. Install systemd services
echo "[1/5] Installing systemd services..."
cp -v "$(dirname "$0")/systemd/geminia-crm-queue.service" /etc/systemd/system/
cp -v "$(dirname "$0")/systemd/geminia-erp-api.service" /etc/systemd/system/

# Fix WorkingDirectory and User if APP_DIR differs
sed -i "s|/var/www/geminia-crm-laravel|$APP_DIR|g" /etc/systemd/system/geminia-crm-queue.service
sed -i "s|/var/www/geminia-crm-laravel|$APP_DIR|g" /etc/systemd/system/geminia-erp-api.service
sed -i "s|User=nginx|User=$WEB_USER|g" /etc/systemd/system/geminia-crm-queue.service
sed -i "s|Group=nginx|Group=$WEB_USER|g" /etc/systemd/system/geminia-crm-queue.service
sed -i "s|User=nginx|User=$WEB_USER|g" /etc/systemd/system/geminia-erp-api.service
sed -i "s|Group=nginx|Group=$WEB_USER|g" /etc/systemd/system/geminia-erp-api.service

# 2. Nginx config
echo "[2/5] Installing Nginx config..."
cp -v "$(dirname "$0")/nginx/geminia-crm.conf" /etc/nginx/conf.d/
sed -i "s|/var/www/geminia-crm-laravel|$APP_DIR|g" /etc/nginx/conf.d/geminia-crm.conf
echo "  Edit /etc/nginx/conf.d/geminia-crm.conf to set server_name and php-fpm socket"

# 3. Reload systemd
echo "[3/5] Reloading systemd..."
systemctl daemon-reload

# 4. Enable and start queue worker
echo "[4/5] Enabling queue worker..."
systemctl enable geminia-crm-queue
systemctl start geminia-crm-queue
echo "  Queue: systemctl status geminia-crm-queue"

# 5. Optional: enable ERP API
echo "[5/5] ERP API (optional)..."
read -p "Enable ERP Clients API service? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    systemctl enable geminia-erp-api
    systemctl start geminia-erp-api
    echo "  ERP API: systemctl status geminia-erp-api"
fi

echo ""
echo "=== Done ==="
echo "Next steps:"
echo "  1. Configure /etc/nginx/conf.d/geminia-crm.conf (server_name, fastcgi_pass)"
echo "  2. Add scheduler cron: crontab -u $WEB_USER -e"
echo "     * * * * * cd $APP_DIR && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"
echo "  3. systemctl reload nginx"
echo "  4. Open firewall: firewall-cmd --permanent --add-service=http && firewall-cmd --reload"
