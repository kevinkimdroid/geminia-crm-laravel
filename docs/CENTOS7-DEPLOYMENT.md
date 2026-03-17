# Geminia CRM – CentOS 7 Server Deployment

This guide walks through deploying the Laravel CRM on **CentOS 7** so the web app, queue worker, and ERP API (if used) keep running in the background.

---

## 1. Prerequisites (run as root or with sudo)

### Install PHP 8.1+ (Remi repository)

```bash
# Enable EPEL and Remi
yum install -y epel-release
yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm

# Install PHP 8.1 and required extensions
yum install -y php81 php81-php-fpm php81-php-cli php81-php-mbstring php81-php-xml \
  php81-php-pdo php81-php-mysqlnd php81-php-opcache php81-php-zip php81-php-gd \
  php81-php-json php81-php-tokenizer php81-php-bcmath php81-php-posix

# Enable PHP 8.1 for CLI
scl enable php81 bash
# Or add to path: export PATH="/opt/remi/php81/root/usr/bin:$PATH"
```

### Install Composer, Node.js, Nginx

```bash
# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Node.js 18 (NodeSource)
curl -fsSL https://rpm.nodesource.com/setup_18.x | bash -
yum install -y nodejs

# Nginx
yum install -y nginx

# (Optional) If using Oracle/ERP: Oracle Instant Client + oci8
# yum install -y oracle-instantclient19.19-basic oracle-instantclient19.19-devel
# pecl install oci8
```

### Install Python 3 (for ERP clients API, if using erp_http)

CentOS 7 defaults to Python 2.7. The ERP API **requires Python 3.7+** (oracledb and Flask have no Python 2 support).

```bash
# Option A: SCL Python 3.8 (recommended – oracledb needs 3.7+)
yum install -y centos-release-scl
yum install -y rh-python38
scl enable rh-python38 bash
pip install -r /var/www/geminia-crm-laravel/erp-clients-api/requirements.txt

# For systemd, use full path:
# ExecStart=/opt/rh/rh-python38/root/usr/bin/python3 app.py

# Option B: IUS repo (Python 3.8)
# yum install -y https://repo.ius.io/ius-release-el7.rpm
# yum install -y python38 python38-pip
# pip3.8 install -r erp-clients-api/requirements.txt
# ExecStart=/usr/bin/python3.8 app.py

# Find python3 path for systemd
which python3
```

---

## 2. Upload and Deploy the Application

### Option A: Git (recommended)

```bash
cd /var/www
git clone https://your-repo/geminia-crm-laravel.git
cd geminia-crm-laravel
```

### Option B: Upload files (scp/rsync/sftp)

```bash
# On your local machine:
# scp -r /path/to/geminia-crm-laravel user@server:/var/www/
# Then on server:
cd /var/www/geminia-crm-laravel
```

### Deploy commands

```bash
cd /var/www/geminia-crm-laravel

# Copy env and configure
cp .env.example .env
nano .env   # Set APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, etc.

# Generate key
php81 artisan key:generate

# Install dependencies and build
composer install --no-dev --optimize-autoloader
npm ci --omit=dev
npm run build

# Run migrations
php81 artisan migrate --force

# Cache and link
php81 artisan config:cache
php81 artisan route:cache
php81 artisan view:cache
php81 artisan storage:link

# Permissions (Nginx user is usually nginx)
chown -R nginx:nginx storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

Or use the deploy script:

```bash
chmod +x deploy.sh
./deploy.sh
```

---

## 3. Nginx Configuration

Create `/etc/nginx/conf.d/geminia-crm.conf`:

```nginx
server {
    listen 80;
    server_name your-domain.com;   # or IP
    root /var/www/geminia-crm-laravel/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;   # or 127.0.0.1:9000
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }
}
```

**For CentOS 7 with php-fpm 8.1**, the socket may be:

- `/var/run/php-fpm/www.sock` (stock php-fpm), or  
- `/opt/remi/php81/root/var/run/php-fpm/www.sock` (Remi)

Check: `ls /var/run/php-fpm/` or `ls /opt/remi/php81/root/var/run/php-fpm/`

Then:

```bash
nginx -t
systemctl enable nginx
systemctl start nginx
```

---

## 4. PHP-FPM (for PHP 8.1)

If using Remi PHP 8.1:

```bash
systemctl enable php81-php-fpm
systemctl start php81-php-fpm
```

Ensure Nginx `fastcgi_pass` points to the correct socket.

---

## 5. Keep Queue Worker Running (systemd)

Create `/etc/systemd/system/geminia-crm-queue.service`:

```ini
[Unit]
Description=Geminia CRM Queue Worker
After=network.target mysql.service

[Service]
Type=simple
User=nginx
Group=nginx
WorkingDirectory=/var/www/geminia-crm-laravel
ExecStart=/opt/remi/php81/root/usr/bin/php artisan queue:work --tries=3 --sleep=3
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

**Adjust paths:** If PHP 8.1 is in `PATH`, you can use `ExecStart=/usr/bin/php artisan queue:work ...` instead.

Enable and start:

```bash
systemctl daemon-reload
systemctl enable geminia-crm-queue
systemctl start geminia-crm-queue
systemctl status geminia-crm-queue
```

---

## 6. Keep ERP Clients API Running (optional, if using erp_http)

Create `/etc/systemd/system/geminia-erp-api.service`:

```ini
[Unit]
Description=Geminia ERP Clients API (Python)
After=network.target

[Service]
Type=simple
User=nginx
Group=nginx
WorkingDirectory=/var/www/geminia-crm-laravel/erp-clients-api
Environment="PATH=/usr/local/bin:/usr/bin:/usr/local/sbin:/usr/sbin"
# Use full path to Python 3.7+ (e.g. /opt/rh/rh-python38/root/usr/bin/python3)
ExecStart=/opt/rh/rh-python38/root/usr/bin/python3 app.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

If the app runs on port 5000, ensure:

- Firewall allows port 5000 (or use a reverse proxy)
- `.env` has: `ERP_CLIENTS_HTTP_URL=http://localhost:5000/clients` (or the correct host)

Enable and start:

```bash
systemctl daemon-reload
systemctl enable geminia-erp-api
systemctl start geminia-erp-api
```

---

## 7. Laravel Scheduler (cron)

```bash
crontab -u nginx -e
```

Add:

```cron
* * * * * cd /var/www/geminia-crm-laravel && /opt/remi/php81/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs:

- **Daily 06:00** – `maturities:sync` – refresh maturities cache
- **Daily 08:00** – `tickets:create-maturity-reminders` – auto-create tickets for policies maturing soon
- **Hourly** – `tickets:sla-violation-reminders` – email assigned users for SLA violations (rate-limited per ticket)
- **Every 5 min** – `mail:fetch` – fetch inbound emails and auto-create tickets (if enabled)

Ensure `MAIL_MAILER` (or Microsoft Graph) is configured so ticket-creation emails and SLA reminders can be sent.

---

## 8. Firewall (CentOS 7)

```bash
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https
firewall-cmd --reload
```

---

## 9. Contacts/Clients Page – Server Troubleshooting

When the **Contacts** or **Clients** page (Group Life, Individual Life, policy search) is empty, wrong, or shows errors on the server:

### 1. SELinux blocking network (most common)

PHP/Apache/Nginx cannot reach the ERP API. Fix:

```bash
sudo setsebool -P httpd_can_network_connect 1
sudo systemctl restart httpd   # or: nginx, php-fpm
```

### 2. ERP_CLIENTS_HTTP_URL

- **Same server:** `ERP_CLIENTS_HTTP_URL=http://127.0.0.1:5000/clients` (prefer 127.0.0.1 over localhost)
- **Different server:** `ERP_CLIENTS_HTTP_URL=http://erp-server-ip:5000/clients`
- Test: `curl http://127.0.0.1:5000/clients?limit=5` from the server

### 3. ERP API not running

```bash
systemctl status geminia-erp-api
# If stopped:
systemctl start geminia-erp-api
```

### 4. Using erp_sync (local cache)

If `CLIENTS_VIEW_SOURCE=erp_sync`, the `erp_clients_cache` table must be populated. After deployment:

- Run the import API: `POST /api/admin/erp-clients-import` with your clients data, or
- Run sync: `php artisan erp:sync-clients --replace` (requires Oracle connection)

### 5. Wrong count after upload

The total is cached. After importing clients, run:

```bash
php artisan cache:clear
```

Or the import API will invalidate caches automatically when used.

### 6. APP_URL

Set `APP_URL` in `.env` to your real server URL (e.g. `https://crm.yourcompany.com`). Wrong APP_URL breaks redirects and asset links.

---

## 10. Quick Reference

| Service           | Command / Path                                            |
|------------------|------------------------------------------------------------|
| Nginx            | `systemctl start nginx`                                   |
| PHP-FPM          | `systemctl start php81-php-fpm`                           |
| Queue worker     | `systemctl start geminia-crm-queue`                       |
| ERP API (optional)| `systemctl start geminia-erp-api`                        |
| Logs             | `tail -f storage/logs/laravel.log`                        |

---

## 10. Pre-built files in the repo

- `deploy/systemd/geminia-crm-queue.service` – queue worker
- `deploy/systemd/geminia-erp-api.service` – ERP API
- `deploy/nginx/geminia-crm.conf` – Nginx config
- `deploy/setup-centos7.sh` – one-time setup script
