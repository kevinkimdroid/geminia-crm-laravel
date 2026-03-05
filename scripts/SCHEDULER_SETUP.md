# Auto Email Fetch & Scheduler Setup

Once configured, **no manual action is needed** – emails are fetched every 5 minutes, tickets are auto-created, and auto-replies are sent.

## One-Time Setup: Windows Task Scheduler

1. Open **Task Scheduler** (search in Start menu).

2. **Create Task** (not Basic Task):
   - **General**: Name = `Geminia Laravel Scheduler`, Run whether user is logged on or not
   - **Triggers**: New → Begin: At startup (or At log on); Repeat task every **1 minute**; for a duration of **Indefinitely**
   - **Actions**: Start a program
     - Program: `C:\xampp\php\php.exe`
     - Arguments: `artisan schedule:run`
     - Start in: `C:\xampp\htdocs\sites\geminia-crm-laravel`

3. **Conditions**: Uncheck "Start only if on AC power" if on a laptop.

4. Save. The task will run every minute and trigger `mail:fetch` every 5 minutes.

**Alternative** – Run the batch file every minute:
- Program: `C:\xampp\htdocs\sites\geminia-crm-laravel\scripts\run-scheduler.bat`
- Start in: `C:\xampp\htdocs\sites\geminia-crm-laravel`

## .env for Fully Automated Email → Ticket Flow

```
MAIL_AUTO_FETCH_ENABLED=true
TICKET_AUTO_FROM_EMAIL_ENABLED=true
TICKET_AUTO_REPLY_ENABLED=true
```

If using Microsoft Graph: `MSGRAPH_ENABLED=true` (with Mail.Read + Mail.Send permissions).
