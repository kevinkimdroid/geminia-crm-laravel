# ERP Integration – Quick Reference

## Current Setup

- **Clients source:** `CLIENTS_VIEW_SOURCE=erp_http` (Laravel fetches from ERP API)
- **ERP API:** Python Flask app in `erp-clients-api/`, connects to Oracle `LMS_INDIVIDUAL_CRM_VIEW`
- **Client count:** 10,536 (set in `erp-clients-api/.env` as `ERP_CLIENTS_ESTIMATED_TOTAL`)

## Restart ERP API

```bash
restart-erp-and-test.bat
```

Or manually:
1. Stop any process on port 5000
2. `cd erp-clients-api && python app.py`
3. `php artisan cache:clear`

## Key URLs

| Page | URL |
|------|-----|
| Clients list | `/support/customers` |
| Client details | `/support/clients/show?policy=090807694` |
| Serve Client | `/support/serve-client` |
| Tickets | `/tickets` |

## If Client Count Changes

1. Run `SELECT COUNT(*) FROM TQ_LMS.LMS_INDIVIDUAL_CRM_VIEW` in Oracle
2. Update `erp-clients-api/.env`: `ERP_CLIENTS_ESTIMATED_TOTAL=<new_count>`
3. Restart the ERP API

## Troubleshooting

### "Oracle connection failed. Showing CRM contacts below (if any)."

This means Laravel cannot get client data from the ERP API. Check in order:

| Step | Action |
|------|--------|
| **1. ERP API running?** | On server: `systemctl status geminia-erp-api` (or `ps aux \| grep app.py`). Start: `sudo systemctl start geminia-erp-api` |
| **2. Health check** | `curl http://localhost:5000/health` — if "connected" = Oracle OK; if error = Oracle/credential issue |
| **3. Laravel can reach API?** | Ensure `ERP_CLIENTS_HTTP_URL` in Laravel `.env` is correct. Same server: `http://127.0.0.1:5000/clients` or `http://localhost:5000/clients` |
| **4. Oracle credentials** | In `erp-clients-api/.env`: `ORACLE_DSN`, `ORACLE_USER`, `ORACLE_PASSWORD` must be set. DSN format: `host:port/service_name` (e.g. `10.1.4.101:18032/PDBTQUEST`) |
| **5. Network/firewall** | Can the server reach Oracle? `telnet 10.1.4.101 18032` or `nc -zv 10.1.4.101 18032` |
| **6. SELinux (CentOS)** | May block outbound: `setenforce 0` temporarily to test; or allow with `setsebool` |

**Quick test (on server):**
```bash
# From Laravel app directory - full diagnostic
php artisan erp:test-connection

# Or manually:
curl -s http://127.0.0.1:5000/health
# If {"status":"ok"} = Oracle OK. If 503/connection refused = API or Oracle issue.

# SELinux (CentOS): Apache may be blocked from connecting to port 5000
sudo setsebool -P httpd_can_network_connect 1
```

### Other issues

| Issue | Fix |
|-------|-----|
| Wrong client count | Update `ERP_CLIENTS_ESTIMATED_TOTAL` in erp-clients-api/.env |
| Search returns no results | Restart API to load updated `ERP_CLIENTS_LIST_SEARCH_COLUMNS` |
| Null fields on client details | Check Oracle view has those columns; see `http://localhost:5000/columns` |
