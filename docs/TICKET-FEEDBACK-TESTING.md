# Ticket Feedback / Rating Feature — Testing Guide

When a ticket is closed, the client (contact) receives an email asking them to rate their experience: **"Were you happy with our service?"** — Yes (Happy) or No (Not satisfied).

## Setup

### 1. Run the migration

```bash
php artisan migrate --force
```

This creates the `ticket_feedback` table in your default database.

> **Note:** If you get "Array to string conversion" on migrate, check your `config/database.php` and `.env` — ensure `DB_DATABASE` and related values are plain strings, not arrays.

### 2. Enable feedback requests (default: enabled)

In `.env`:

```
TICKET_FEEDBACK_REQUEST_ENABLED=true
```

To disable:

```
TICKET_FEEDBACK_REQUEST_ENABLED=false
```

### 3. Email feedback to life@geminialife (optional)

When a contact submits feedback, it is emailed to `life@geminialife.co.ke` by default. Override with:

```
TICKET_FEEDBACK_NOTIFY_EMAIL=life@geminialife.co.ke
```

### 4. Ensure email sending works

Feedback emails use the same system as ticket creation notifications (Microsoft Graph or Laravel Mail). Make sure your email configuration is correct.

---

## How to Test

### A. End-to-end (recommended)

1. **Find or create a ticket** with a contact that has a valid email.

2. **Close the ticket** either:
   - Via **Quick Close**: Ticket → "Close Ticket" button → enter solution → Close, or
   - Via **Edit**: change status to Closed and add a solution.

3. **Check the contact's email** — they should receive:
   - Subject: `How was your experience? — Ticket TT12345 closed`
   - Body includes a signed link to the feedback form.

4. **Open the link** (valid for 7 days). You should see:
   - "How was your experience?"
   - Two options: "Yes, I was happy with the service" and "No, I was not satisfied"
   - Optional comment box.

5. **Submit feedback** — you should see the "Thank You" page.

6. **View the ticket** in the CRM — the ticket show page should show a "Client Feedback" section with the rating and comment.

7. **Check life@geminialife.co.ke** — the team inbox should receive an email with the feedback (rating, comment, ticket link).

### B. Test without email

1. Close a ticket with a contact that has an email.

2. Generate the feedback URL manually:
   ```bash
   php artisan tinker
   >>> $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('feedback.form', now()->addDays(7), ['ticket' => YOUR_TICKET_ID]);
   >>> echo $url;
   ```

3. Replace `YOUR_TICKET_ID` with a closed ticket ID (e.g. `2025806067`).

4. Open the URL in a browser and submit feedback.

### C. Verify feedback storage

After submitting:

```bash
php artisan tinker
>>> \App\Models\TicketFeedback::latest()->first();
```

---

## Troubleshooting

| Issue | Check |
|-------|--------|
| No email sent | Contact has email? `TICKET_FEEDBACK_REQUEST_ENABLED=true`? Mail/Graph configured? Check `storage/logs/laravel.log` for "feedback request sent" or "feedback request skipped" |
| "Link expired" | Signed URLs expire after 7 days. Generate a new link. |
| "Ticket not found" | Ticket ID correct? Ticket in vtiger DB? |
| Feedback not showing on ticket | Migration run? `ticket_feedback` table exists? |

---

## Summary of Changes

- **POLICY column**: Still shows policy number only, never KRA PIN (unchanged from previous work).
- **Feedback on close**: When a ticket is closed, the contact gets an email with a link to rate the service.
- **Feedback form**: Simple Yes/No + optional comment. No login required; uses signed URL.
- **Ticket show page**: Displays client feedback (rating + comment) when available.
- **Notify on submit**: When feedback is submitted, an email is sent to `life@geminialife.co.ke` (or `TICKET_FEEDBACK_NOTIFY_EMAIL`) with the rating, comment, and ticket link.
