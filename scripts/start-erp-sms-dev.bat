@echo off
REM Local dev: erp-clients-api (port 5000) + Laravel scheduler for ERP SMS auto-send.
REM Requires FINANCE_ERP_HTTP_BASE=http://127.0.0.1:5000 in CRM .env (default in many dev setups).
cd /d "%~dp0.."
echo.
echo === Geminia CRM — ERP SMS dev stack ===
echo CRM folder: %CD%
echo.
echo 1) Keep ONE window open for erp-clients-api (Oracle .env in erp-clients-api\.env)
start "erp-clients-api" cmd /k "cd /d %CD%\erp-clients-api && start.bat"
echo.
echo 2) Starting scheduler loop (erp:send-sms-messages every 5 min when auto-send is on)...
timeout /t 5 /nobreak >nul
start "erp-sms-scheduler" cmd /k "cd /d %CD%\scripts && run-scheduler-loop.bat"
echo.
echo Done. Wait ~10s for API on :5000, then run:
echo   php artisan erp:diagnose-sms
echo   php artisan erp:send-sms-messages --limit=5
echo.
echo Or use one command for everything: composer run dev
pause
