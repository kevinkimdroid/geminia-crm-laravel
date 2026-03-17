# After Upload – Run This Once

After uploading the project to your server, run:

```bash
# Clear dashboard and clients caches so numbers refresh correctly
php artisan dashboard:clear-cache
```

**If using ERP Clients API** (CLIENTS_VIEW_SOURCE=erp_http), restart it:

```bash
systemctl restart geminia-erp-api
```

If you see **ORA-00904: "BASE"."IPOL_POLICY_NO": invalid identifier**, add to `erp-clients-api/.env`:

```
ERP_GROUP_GROUP_BY_COLUMN=POL_POLICY_NO
```

Then restart the API again.

That's it. Dashboard and Clients page will now show correct numbers.

---

**Optional:** Add to your `.env` if you want to change behavior:

```
DASHBOARD_SHOW_ALL_STATS=true
```

- `true` (default): Everyone sees organization-wide totals
- `false`: Non-admins see only their own records
