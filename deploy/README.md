# Geminia CRM - Server Deploy Files

Files for running the app on **CentOS 7** so the web app, queue worker, and ERP API keep running.

## Contents

| File | Purpose |
|------|---------|
| `systemd/geminia-crm-queue.service` | Laravel queue worker (runs in background, restarts on boot) |
| `systemd/geminia-erp-api.service` | ERP Clients API - Python Flask (if using erp_http) |
| `nginx/geminia-crm.conf` | Nginx virtual host |
| `setup-centos7.sh` | One-time setup script |

## Quick Start

1. **Upload the project** to `/var/www/geminia-crm-laravel` (or your path)
2. **Run deploy** (see main [CENTOS7-DEPLOYMENT.md](../docs/CENTOS7-DEPLOYMENT.md)):
   ```bash
   cd /var/www/geminia-crm-laravel
   ./deploy.sh
   ```
3. **Run setup** (installs systemd + nginx config):
   ```bash
   sudo ./deploy/setup-centos7.sh
   ```
4. **Edit** `/etc/nginx/conf.d/geminia-crm.conf`:
   - Set `server_name` to your domain or IP
   - Set `fastcgi_pass` to your PHP-FPM socket
5. **Reload Nginx**: `sudo systemctl reload nginx`

## Custom paths

```bash
APP_DIR=/home/user/geminia-crm WEB_USER=apache sudo ./deploy/setup-centos7.sh
```
