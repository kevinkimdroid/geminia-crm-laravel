@echo off
echo Stopping ERP API on port 5000...
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :5000 ^| findstr LISTENING') do taskkill /F /PID %%a 2>nul
timeout /t 2 /nobreak >nul

echo Starting ERP API...
cd /d "%~dp0"
start "ERP API" cmd /k "python app.py"

timeout /t 5 /nobreak >nul
echo.
echo Testing policy 090807694...
curl -s "http://localhost:5000/clients?policy=090807694&limit=1"
echo.
echo.
echo If you see prp_dob, maturity_date, id_no, phone_no in the JSON above, it worked.
pause
