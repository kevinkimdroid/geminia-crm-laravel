# ERP Clients Sync & API Options

**Missing columns (Date of Birth, Effective Date, Maturity Date):**  
LMS_INDIVIDUAL_CRM_VIEW may not include PRP_DOB, EFFECTIVE_DATE, MATURITY. Ask your DBA to add these to the view, then set:
`ERP_CLIENTS_LIST_COLUMNS=POLICY_NUMBER,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN,PRP_DOB,EFFECTIVE_DATE,MATURITY`
and re-run the Python sync.

**Sources for Clients (CLIENTS_VIEW_SOURCE in .env):**
- `erp_sync` – Local cache (populated by Python script or import API). Fast, no Oracle on page load.
- `erp_http` – Direct fetch from ERP REST API (`ERP_CLIENTS_HTTP_URL`). Fastest if your ERP exposes an API.
- `erp` – Live Oracle (drops connection on large views).
- `crm` – vtiger contacts.

## Setup

1. **Run migration** (on the Laravel server):
   ```bash
   php artisan migrate
   ```

2. **Set ERP_SYNC_TOKEN** in `.env`:
   ```
   ERP_SYNC_TOKEN=your-secure-random-token
   ```
   Generate one: `php -r "echo bin2hex(random_bytes(32));"`

3. **Set CLIENTS_VIEW_SOURCE** in `.env`:
   ```
   CLIENTS_VIEW_SOURCE=erp_sync
   ```

## Option A: Python script (recommended – run where Oracle works)

On a machine with Oracle client (e.g. where Toad connects successfully):

```bash
cd scripts
pip install -r requirements-sync.txt

# Set env vars (or use a .env file)
set ORACLE_DSN=10.1.4.101:18032/PDBTQUEST
set ORACLE_USER=TQ_LMS
set ORACLE_PASSWORD=TQ#LMS2019c
set LARAVEL_URL=https://geminialife.co.ke
set ERP_SYNC_TOKEN=your-token-from-env

python sync_erp_clients.py
```

**Schedule it** (Windows Task Scheduler or cron) to run hourly or daily.

## Option B: Laravel command (if Oracle works from Laravel server)

```bash
php artisan erp:sync-clients --replace
```

Runs directly on the Laravel host. Use if Oracle connects reliably from there; otherwise use the Python script.

## API (for custom sync tools)

```
POST /api/admin/erp-clients-import
Headers: X-API-Key: <ERP_SYNC_TOKEN>
Body: {
  "replace": true,
  "clients": [
    {
      "policy_number": "PN123",
      "product": "Life",
      "pol_prepared_by": "John",
      "intermediary": "Broker Co",
      "status": "Active",
      "kra_pin": "A001234567B",
      "prp_dob": "1990-01-15",
      "maturity": "2040-01-15"
    }
  ]
}
```

- `replace: true` – truncate cache, then insert all provided clients (full sync).
- `replace: false` – upsert by policy_number.
