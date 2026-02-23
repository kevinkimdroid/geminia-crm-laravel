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

| Issue | Fix |
|-------|-----|
| "Oracle connection failed" | Ensure ERP API is running (`python app.py` in erp-clients-api) |
| Wrong client count | Update `ERP_CLIENTS_ESTIMATED_TOTAL` in erp-clients-api/.env |
| Search returns no results | Restart API to load updated `ERP_CLIENTS_LIST_SEARCH_COLUMNS` |
| Null fields on client details | Check Oracle view has those columns; see `http://localhost:5000/columns` |
