# After Upload – Run This Once

After uploading the project to your server, run:

```bash
# Clear dashboard cache so numbers refresh correctly
php artisan dashboard:clear-cache
```

That's it. The dashboard will now show correct Pipeline, Leads, Active Deals, Contacts, and Clients counts.

---

**Optional:** Add to your `.env` if you want to change behavior:

```
DASHBOARD_SHOW_ALL_STATS=true
```

- `true` (default): Everyone sees organization-wide totals
- `false`: Non-admins see only their own records
