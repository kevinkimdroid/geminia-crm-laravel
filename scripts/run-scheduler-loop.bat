@echo off
REM Runs Laravel scheduler every 60 seconds (same as cron * * * * *).
REM Keep this window open for ERP SMS auto-send and other scheduled tasks.
cd /d "%~dp0.."
echo ERP SMS scheduler loop — %CD%
echo Log: storage\logs\erp-sms-scheduler.log
echo Press Ctrl+C to stop.
:loop
C:\xampp\php\php.exe artisan schedule:run
timeout /t 60 /nobreak >nul
goto loop
