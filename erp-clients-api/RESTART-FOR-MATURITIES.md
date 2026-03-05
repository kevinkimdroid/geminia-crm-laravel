# Maturities Support – Restart Required

The `/maturities` and `/clients/maturities` endpoints were added for the maturities page.  
**You must restart the ERP Clients API** for them to be available.

## Steps

1. **Stop the current API** (in the terminal where it's running: Ctrl+C)

2. **Start it again:**
   ```bash
   cd erp-clients-api
   python app.py
   ```

3. **Run the maturities sync:**
   ```bash
   php artisan maturities:sync
   ```

4. Open **Support → Maturities** in the CRM.

## Verify

Test the maturities endpoint:
```bash
curl "http://localhost:5000/maturities?from=2026-03-01&to=2026-06-30"
```
You should get JSON with a `data` array of policies.
