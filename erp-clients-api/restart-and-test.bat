@echo off
cd /d "%~dp0"
echo ============================================
echo   STOP old API first: Ctrl+C in its window
echo   Or run: taskkill /F /PID 23288
echo   Then: python app.py
echo ============================================
echo.
echo [1] Health:
curl -s -m 5 "http://localhost:5000/health" 2>nul || echo API not running - start with: python app.py
echo.
echo [2] Find policy (use this URL in browser):
curl -s -m 5 "http://localhost:5000/find-policy?policy=GEMPPP0334" 2>nul
echo.
echo [3] All routes (debug 404):
curl -s -m 5 "http://localhost:5000/routes" 2>nul
echo.
echo [4] Group clients:
curl -s -m 5 "http://localhost:5000/clients?limit=2&offset=0&system=group" 2>nul
echo.
pause
