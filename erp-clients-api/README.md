# ERP Clients API

Standalone API that fetches clients from Oracle `LMS_INDIVIDUAL_CRM_VIEW`.  
**Deploy this on a machine with reliable Oracle connectivity** (e.g. where Toad works, same network as Oracle).

The Geminia Laravel CRM fetches clients from this API when `CLIENTS_VIEW_SOURCE=erp_http`.

## Setup

1. **Install dependencies:**
   ```bash
   pip install -r requirements.txt
   ```

2. **Configure environment** (copy .env.example to .env):
   ```bash
   copy .env.example .env
   # Edit .env with Oracle credentials
   ```

3. **Run the API:**
   ```bash
   python app.py
   ```
   Or use the start script: `start.bat` (Windows) / `start.sh` (Linux/Mac).

   **With Laravel:** `composer run dev` starts Laravel + Vite + Queue + **ERP API** automatically.
   Use `composer run dev:no-erp` if you don't need Oracle clients.
   Or with env vars:
   ```bash
   set ORACLE_PASSWORD=TQ#LMS2019c
   python app.py
   ```

4. **Expose to Laravel** – The Laravel server must reach this API:
   - If API runs on same machine as Laravel: `http://localhost:5000/clients` or `http://127.0.0.1:5000/clients`
   - If API runs on Oracle server (10.1.4.101): `http://10.1.4.101:5000/clients`
   - For production: run behind nginx, set up HTTPS, firewall rules

## Client Count (ERP_CLIENTS_ESTIMATED_TOTAL)

The API skips slow `COUNT(*)` on Oracle. Set `ERP_CLIENTS_ESTIMATED_TOTAL` in `erp-clients-api/.env` to match the actual row count of `LMS_INDIVIDUAL_CRM_VIEW` (e.g. 10536). Run `SELECT COUNT(*) FROM TQ_LMS.LMS_INDIVIDUAL_CRM_VIEW` in Toad/SQL Developer to get the current count.

## Laravel Configuration

In Laravel `.env`:
```
CLIENTS_VIEW_SOURCE=erp_http
ERP_CLIENTS_HTTP_URL=http://your-erp-api-server:5000/clients
```

Then: `php artisan config:clear`

## API Endpoints

- **GET /clients** – List clients (supports limit, offset, search)
  - Query params: `limit` (default 50, max 100), `offset` (default 0), `search` (optional)
  - Response: `{ "data": [...], "total": N }`

- **GET /health** – Health check (tests Oracle connection)

## Production Deployment

- Use gunicorn: `gunicorn -w 2 -b 0.0.0.0:5000 app:app`
- Or run as Windows service / systemd
- Ensure firewall allows Laravel server to reach this API port
