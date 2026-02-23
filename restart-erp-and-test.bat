@echo off
cd /d "%~dp0"

echo ========================================
echo   ERP API Restart and Verification
echo ========================================
echo.

echo [1/5] Stopping ERP API on port 5000...
for /f "tokens=5" %%a in ('netstat -ano 2^>nul ^| findstr :5000 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /F /PID %%a 2>nul
)
timeout /t 3 /nobreak >nul

echo [2/5] Starting ERP API...
start "ERP API" cmd /k "cd /d %~dp0erp-clients-api && python app.py"
timeout /t 8 /nobreak >nul

echo [3/5] Clearing Laravel cache...
php artisan cache:clear 2>nul
php artisan config:clear 2>nul

echo [4/5] Testing API...
curl -s "http://localhost:5000/clients?limit=2&offset=0" | findstr /C:"total" /C:"data" >nul 2>&1
if %errorlevel% equ 0 (
    echo   API responded OK
) else (
    echo   WARNING: API may not be ready. Wait a few seconds and try again.
)

echo [5/5] Testing policy 090807694...
php artisan erp:debug-policy 090807694 2>nul

echo.
echo ========================================
echo   Done. Next steps:
echo   - Clients list: /support/customers (should show 10,536 total)
echo   - Client details: /support/clients/show?policy=090807694
echo   - Serve Client: /support/serve-client
echo ========================================
pause
