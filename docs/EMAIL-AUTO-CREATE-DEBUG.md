# Debug: Emails Not Auto-Creating Tickets on Server

When emails are fetched but tickets are not being created, use this guide to diagnose.

## 1. Run the diagnostic command

On the server:

```bash
cd /var/www/geminia-crm-laravel   # or your app path
php artisan mail:debug --fetch
```

This checks:

- `MAIL_AUTO_FETCH_ENABLED` – must be `true` for scheduler to run `mail:fetch`
- `TICKET_AUTO_FROM_EMAIL_ENABLED` – must be `true` to create tickets from emails
- Which email backend is used (Microsoft Graph, HTTP API, or IMAP)
- Recent stored emails and fetch result
- Recent log lines related to mail/ticket

## 2. Common causes

### Scheduler not running

The Laravel scheduler must run **every minute**. Cron:

```bash
* * * * * cd /var/www/geminia-crm-laravel && php artisan schedule:run >> /dev/null 2>&1
```

- **CentOS + Apache**: `crontab -u apache -e` (or the user that runs the web app)
- **CentOS + Nginx**: `crontab -u nginx -e`

Verify: `php artisan schedule:list` – you should see `mail:fetch` every 5 minutes.

### Auto-ticket disabled

In `.env`:

```
TICKET_AUTO_FROM_EMAIL_ENABLED=true
```

Then: `php artisan config:clear`

### Email fetch failing (no emails stored)

- **Microsoft Graph**: Check `MSGRAPH_ENABLED`, `MSGRAPH_CLIENT_ID`, `MSGRAPH_CLIENT_SECRET`, `MSGRAPH_MAILBOX`
- **HTTP API**: `EMAIL_SERVICE_URL` (e.g. `http://10.10.1.111:8080`), `MAIL_USERNAME`, `MAIL_PASSWORD`
- **IMAP**: `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`

If using HTTP API on CentOS, SELinux may block outbound connections:

```bash
sudo setsebool -P httpd_can_network_connect 1
sudo systemctl restart httpd
```

### Emails stored but no tickets

- Sender domain may be **excluded** – see `EMAIL_EXCLUDED_SENDER_DOMAINS` in `config/email-service.php` (e.g. `geminialife.co.ke`, `gab.co.ke`, `centralbank.go.ke`)
- Sender may be treated as **internal** – check `AutoTicketFromEmailService::isInternalSender()`
- Contact lookup failed – check logs for `Could not find or create contact`

## 3. Inspect logs

```bash
tail -f storage/logs/laravel.log
```

Look for:

- `MailService::fetchAndStoreEmails` – connection/fetch errors
- `MailService auto-ticket:` – ticket creation failures
- `AutoTicketFromEmailService` – specific reasons (disabled, internal sender, contact failed)

## 4. Manual test fetch

```bash
php artisan mail:fetch
```

Should show: `Fetched: X, Stored: Y new.` If `Fetched: 0` and no errors, the connection or config is wrong.
