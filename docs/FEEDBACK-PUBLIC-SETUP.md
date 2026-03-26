# CRM client feedback (public URL)

Feedback links in “How was your experience?” emails must point at a **public URL** clients can open. The standalone PHP app lives in **`crm-client-feedback/`** and is served via **`public/crm-client-feedback/index.php`** when the site document root is Laravel’s `public/`.

## Option A: Bundled with Laravel (recommended)

1. Ensure `public/crm-client-feedback/index.php` exists (includes `crm-client-feedback/index.php`).

2. In `.env`:

```env
FEEDBACK_PUBLIC_URL=https://your-host.example.com
FEEDBACK_PUBLIC_PATH=crm-client-feedback
FEEDBACK_CRM_API_URL=https://your-host.example.com
```

Use the same scheme/host/port clients use in the browser. `FEEDBACK_CRM_API_URL` is the CRM base URL the PHP app calls for `/api/feedback/validate` and `/api/feedback/submit`.

3. `php artisan config:clear`

Clients open: `https://your-host.example.com/crm-client-feedback?ticket=…&expires=…&signature=…`

### Optional: local `config.php`

Copy `crm-client-feedback/config.example.php` to `crm-client-feedback/config.php` to override `crm_api_url` / `app_name` without env vars.

## Option B: Deploy only `crm-client-feedback/`

Copy the `crm-client-feedback/` folder to the web server and point the vhost or `/crm-client-feedback` location at it. Set the same `FEEDBACK_PUBLIC_URL`, `FEEDBACK_PUBLIC_PATH`, and `FEEDBACK_CRM_API_URL` on the CRM.

## Option C: Laravel `/feedback` route only

If the full Laravel app is public and you do **not** set `FEEDBACK_PUBLIC_URL`, links use `URL::temporarySignedRoute('feedback.form', …)` (path `/feedback`). Leave `FEEDBACK_PUBLIC_URL` empty.

---

## Apache (separate directory deploy)

```apache
Alias /crm-client-feedback /var/www/geminia-crm-laravel/crm-client-feedback
<Directory /var/www/geminia-crm-laravel/crm-client-feedback>
    AllowOverride None
    Require all granted
    DirectoryIndex index.php
</Directory>
```

## Nginx (separate directory)

```nginx
location /crm-client-feedback {
    alias /var/www/geminia-crm-laravel/crm-client-feedback;
    index index.php;
    try_files $uri $uri/ /crm-client-feedback/index.php?$query_string;
}
location ~ ^/crm-client-feedback/index\.php {
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /var/www/geminia-crm-laravel/crm-client-feedback/index.php;
}
```

**Legacy path:** If you already sent links signed for `/feedback`, set `FEEDBACK_PUBLIC_PATH=feedback` and deploy that path (or keep the Laravel `FeedbackController` route at `/feedback` with empty `FEEDBACK_PUBLIC_URL`).
