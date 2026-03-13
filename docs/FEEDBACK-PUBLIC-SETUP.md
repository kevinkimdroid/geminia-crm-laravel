# Feedback Public URL Setup

Feedback links in "How was your experience?" emails need to point to a **public URL** (e.g. `https://geminialife.co.ke/feedback`) so clients can access them. The main CRM may run on an internal IP (e.g. `10.1.1.65`) that clients cannot reach.

## Option A: Deploy Standalone Feedback App

A minimal PHP app in `feedback-public/` can be deployed at `geminialife.co.ke/feedback`.

### 1. Deploy the app

Copy the `feedback-public/` folder to your web server (e.g. as a subdomain or path):

```
https://geminialife.co.ke/feedback/   → feedback-public/index.php
```

Or as subdomain: `https://feedback.geminialife.co.ke/` pointing to the feedback-public folder.

### 2. Configure the standalone app

Copy `feedback-public/config.example.php` to `feedback-public/config.php`:

```php
return [
    'crm_api_url' => 'https://geminialife.co.ke',  // CRM base URL (same domain when both are on geminialife.co.ke)
    'app_name' => 'Geminia Life Insurance',
];
```

When the feedback app and CRM are both on geminialife.co.ke, use `https://geminialife.co.ke` so the form validates and submits via the public domain.

### 3. Configure the CRM (.env)

In the main Laravel app's `.env`:

```env
# Public URL where clients access the feedback form
FEEDBACK_PUBLIC_URL=https://geminialife.co.ke/feedback

# CRM base URL for API calls (use geminialife.co.ke when both apps are on the same server)
FEEDBACK_CRM_API_URL=https://geminialife.co.ke
```

### 4. Clear config cache

```bash
php artisan config:clear
```

---

## Option B: Expose CRM Directly

If the full Laravel app is reachable at `https://geminialife.co.ke` or `https://crm.geminialife.co.ke`, set:

```env
APP_URL=https://geminialife.co.ke
# Or: APP_URL=https://crm.geminialife.co.ke
```

Leave `FEEDBACK_PUBLIC_URL` empty. Feedback links will use `APP_URL` (e.g. `https://geminialife.co.ke/feedback?ticket=...`).

---

## Apache vhost example (standalone app)

```apache
<VirtualHost *:443>
    ServerName geminialife.co.ke
    DocumentRoot /var/www/geminialife
    # ...
</VirtualHost>

# Or for path /feedback:
Alias /feedback /var/www/geminialife/feedback-public
<Directory /var/www/geminialife/feedback-public>
    AllowOverride None
    Require all granted
    DirectoryIndex index.php
</Directory>
```

## Nginx example

```nginx
location /feedback {
    alias /var/www/geminialife/feedback-public;
    index index.php;
    try_files $uri $uri/ /feedback/index.php?$query_string;
}
location ~ ^/feedback/index\.php {
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /var/www/geminialife/feedback-public/index.php;
}
```
