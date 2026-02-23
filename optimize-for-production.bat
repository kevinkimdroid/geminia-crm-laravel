@echo off
cd /d "%~dp0"
echo Optimizing Geminia CRM for production...
php artisan config:cache 2>nul
php artisan route:cache 2>nul
php artisan view:cache 2>nul
echo Done. Run "php artisan optimize:clear" to reverse.
pause
