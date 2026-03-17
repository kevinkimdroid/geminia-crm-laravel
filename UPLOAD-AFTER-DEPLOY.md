# After Upload – Run This Once

After uploading the project to your server, run:

```bash
# Clear dashboard and clients caches so numbers refresh correctly
php artisan dashboard:clear-cache
```

**If using ERP Clients API** (CLIENTS_VIEW_SOURCE=erp_http), restart it so the accurate-count fix takes effect:

```bash
systemctl restart geminia-erp-api
```

That's it. Dashboard and Clients page will now show correct numbers.

---

**Optional:** Add to your `.env` if you want to change behavior:

```
DASHBOARD_SHOW_ALL_STATS=true
```

- `true` (default): Everyone sees organization-wide totals
- `false`: Non-admins see only their own records
