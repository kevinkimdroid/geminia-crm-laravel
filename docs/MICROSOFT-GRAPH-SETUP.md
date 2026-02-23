# Microsoft Graph Setup for Office 365 Email

The **easiest and most reliable** way to fetch Office 365 emails in Geminia CRM is Microsoft Graph API. It avoids IMAP quirks (NOOP, etc.) and works with MFA.

## Quick Setup (5 steps)

### 1. Register an app in Azure AD

1. Go to [Azure Portal](https://portal.azure.com) → **Microsoft Entra ID** (or Azure Active Directory)
2. **App registrations** → **New registration**
3. Name: `Geminia CRM Mail`
4. Supported account types: **Single tenant** (or Multitenant if needed)
5. Redirect URI: leave blank (not needed for client credentials)
6. Click **Register**

### 2. Create a client secret

1. In your app → **Certificates & secrets**
2. **New client secret** → Add description → Expires: 24 months
3. **Copy the Value** (you won't see it again)

### 3. Grant Mail.Read permission

1. In your app → **API permissions**
2. **Add a permission**
3. **Microsoft Graph** → **Application permissions**
4. Search for **Mail.Read** → check it → **Add permissions**
5. **Grant admin consent for [Your org]** (required for application permissions)

### 4. Get your Tenant ID

1. In Azure Portal → **Microsoft Entra ID** → **Overview**
2. Copy **Tenant ID** (or Directory ID)

### 5. Configure .env

```env
MSGRAPH_ENABLED=true
MSGRAPH_TENANT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
MSGRAPH_CLIENT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
MSGRAPH_CLIENT_SECRET=your-secret-value
MSGRAPH_MAILBOX=life@geminialife.co.ke
```

Replace with your actual values. `MSGRAPH_MAILBOX` is the mailbox to read (user principal name).

## Priority order

When you click **Fetch Emails** in Mail Manager:

1. **Microsoft Graph** (if `MSGRAPH_ENABLED=true` and configured) ← **Use this for Office 365**
2. HTTP email service (if `EMAIL_SERVICE_URL` is set)
3. IMAP (fallback; has issues with Office 365)

## Troubleshooting

| Error | Fix |
|-------|-----|
| "Failed to get access token" | Check `MSGRAPH_CLIENT_ID`, `MSGRAPH_CLIENT_SECRET`, `MSGRAPH_TENANT_ID` |
| "403 Forbidden" | Ensure **Mail.Read** application permission is granted and **admin consent** was given |
| "404 User not found" | Verify `MSGRAPH_MAILBOX` matches the user's UPN (e.g. `user@domain.com`) |

## Security

- Store `MSGRAPH_CLIENT_SECRET` securely; never commit to git
- Use application permissions (not delegated) for server-side fetch
- Rotate the client secret before it expires
