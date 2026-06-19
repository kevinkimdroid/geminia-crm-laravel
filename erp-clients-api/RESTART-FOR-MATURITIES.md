# Maturities Support – Restart Required

The `/maturities` and `/clients/maturities` endpoints use **partial maturities**
(`LMS_POLICY_PRTL_MATURITIES.PPM_EXPECTED_DATE`), not `LMS_INDIVIDUAL_CRM_VIEW.MATURITY_DATE`.
The old CRM-view query fails with `ORA-00932` and returns incomplete data.

**You must restart the ERP Clients API** after updating `app.py` for maturities to work.

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
