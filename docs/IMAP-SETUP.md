# IMAP Setup for Mail Manager

## 0. DNS / Host resolution

If you see "No such host is known" for smtp.office365.com:

- **Internal mail server**: Add to `C:\Windows\System32\drivers\etc\hosts` (run Notepad as Administrator):
  ```
  10.x.x.x    smtp.office365.com
  ```
  Replace `10.x.x.x` with your mail server's actual IP.

- **Or use IP in .env**: `IMAP_HOST=10.x.x.x` (if your provider gives you an IP)
- **Check DNS**: From your machine, run `ping smtp.office365.com` — if it fails, DNS/hosts is the issue.

## 1. Enable PHP IMAP extension (required)

**XAMPP (Windows):**
1. Open `C:\xampp\php\php.ini`
2. Find the line `;extension=imap` (with semicolon = disabled)
3. Remove the semicolon: `extension=imap`
4. Restart Apache from XAMPP Control Panel

**Linux (apt):**
```bash
sudo apt install php-imap
sudo systemctl restart apache2
```

**Verify:**
```bash
php -m | grep imap
```
You should see `imap` in the output.

## 2. .env configuration

These are already in your `.env`:

```env
IMAP_HOST=smtp.office365.com
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_VALIDATE_CERT=false
IMAP_USERNAME=life@geminialife.co.ke
IMAP_PASSWORD="LMSadmin2029@"
```

## 3. Test the connection

```bash
php artisan mail:test-imap
```

## 4. Alternative: Microsoft Graph (Office 365)

If your mailbox is on Microsoft 365, use Microsoft Graph instead of IMAP. See [MICROSOFT-GRAPH-SETUP.md](MICROSOFT-GRAPH-SETUP.md).
