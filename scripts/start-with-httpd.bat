@echo off
REM Start Geminia CRM background processes (queue + ERP API) when using Apache/XAMPP.
REM Use with Task Scheduler (trigger: At startup) to auto-run when server starts.
cd /d "%~dp0.."

set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php

REM Queue worker - processes jobs (Excel export, notifications, etc.)
start "Geminia Queue" cmd /k "%PHP% artisan queue:work database --tries=3 --sleep=3"

REM ERP API - required for Serve Client when using erp_http
start "Geminia ERP API" cmd /k "cd erp-clients-api && python app.py"

echo.
echo Started: Queue worker + ERP API
echo Close the opened windows to stop the processes.
echo.
pause
