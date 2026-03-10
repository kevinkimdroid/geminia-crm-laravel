# Auto-Start Processes with HTTPD (Apache)

When using **CentOS httpd** (Apache) or XAMPP as the web server, the app is served by Apache + PHP. Configs below target CentOS with `httpd.service`. The following processes should auto-start with the server for full functionality.

## What needs to run

| Process | Purpose |
|---------|---------|
| **Queue worker** | Background jobs (Excel export, email, etc.) |
| **ERP API** | Python Flask API for Serve Client (if using `erp_http`) |
| **Laravel scheduler** | Cron: mail fetch, maturities, SLA reminders |
| **Vite (dev only)** | Hot reload for assets; in production use `npm run build` |

---

## Option A: Windows (XAMPP)

### 1. Create a startup script

The repo includes `scripts/start-with-httpd.bat`. To customize, edit that file.

It starts:
- Queue worker (`php artisan queue:work`)
- ERP API (`python erp-clients-api/app.py`)

### 2. Run when Apache starts (Task Scheduler)

1. Open **Task Scheduler** (taskschd.msc)
2. Create Basic Task → Name: `Geminia CRM - Start Queue & API`
3. Trigger: **At startup** (or: **When the computer starts**)
4. Action: **Start a program**
5. Program: `C:\xampp\htdocs\sites\geminia-crm-laravel\scripts\start-with-httpd.bat`
6. Or use two separate tasks for queue and ERP API for easier management

### 3. Alternative: Run as Windows Services (NSSM)

Install [NSSM](https://nssm.cc/download) then:

```batch
REM Queue worker
nssm install GeminiaQueue "C:\xampp\php\php.exe" "artisan queue:work database --tries=3 --sleep=3"
nssm set GeminiaQueue AppDirectory "C:\xampp\htdocs\sites\geminia-crm-laravel"
nssm start GeminiaQueue

REM ERP API
nssm install GeminiaErpApi "C:\xampp\python\python.exe" "app.py"
nssm set GeminiaErpApi AppDirectory "C:\xampp\htdocs\sites\geminia-crm-laravel\erp-clients-api"
nssm start GeminiaErpApi
```

### 4. Scheduler (cron equivalent on Windows)

Add a **scheduled task** to run every minute:

- Program: `C:\xampp\php\php.exe`
- Arguments: `artisan schedule:run`
- Start in: `C:\xampp\htdocs\sites\geminia-crm-laravel`
- Trigger: Repeat every 1 minute

---

## Option B: CentOS (systemd with httpd)

### 1. Install and enable systemd services

```bash
cd /var/www/geminia-crm-laravel

# Copy service files (configured for httpd)
sudo cp deploy/systemd/geminia-crm-queue.service /etc/systemd/system/
sudo cp deploy/systemd/geminia-erp-api.service /etc/systemd/system/

# Edit WorkingDirectory if app is not at /var/www/geminia-crm-laravel
# sudo nano /etc/systemd/system/geminia-crm-queue.service

sudo systemctl daemon-reload
sudo systemctl enable geminia-crm-queue geminia-erp-api
sudo systemctl start geminia-crm-queue geminia-erp-api
```

### 2. Start after httpd (CentOS Apache)

The service files use `httpd.service` so queue and ERP API start after Apache. Enable and start:

```bash
sudo systemctl enable geminia-crm-queue geminia-erp-api
sudo systemctl start geminia-crm-queue geminia-erp-api
```

### 3. Scheduler (crontab)

```bash
* * * * * cd /var/www/geminia-crm-laravel && php artisan schedule:run >> /dev/null 2>&1
```

---

## Option C: Start when XAMPP Control Panel starts Apache

XAMPP does not support custom post-start hooks. Options:

1. **Run the batch script manually** after starting Apache, or
2. **Use Task Scheduler** (Option A.2) set to "At log on" so it runs when you log in (often before or with XAMPP)
3. **Add to XAMPP start**: Edit `C:\xampp\xampp_start.exe` or create a wrapper—not recommended as it modifies XAMPP

---

## Quick reference

| Scenario | What to run |
|----------|-------------|
| **Production (Apache)** | Queue worker + ERP API + Cron |
| **Development (artisan serve)** | `composer run dev` (starts serve, queue, vite, erp) |
| **Development (Apache/XAMPP)** | Queue worker + ERP API + `npm run dev` (separate terminal) |

---

## Verify

- **Queue**: Create a ticket or trigger an export; job should process.
- **ERP API**: Open Serve Client, search by policy; results should load.
- **Scheduler**: Check `storage/logs/laravel.log` for scheduled task output.
