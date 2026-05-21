@echo off
cd /d "%~dp0"
echo Geminia erp-clients-api — after git pull, restart this window so /finance/* routes load (Finance module in CRM).
python --version >nul 2>&1 || (echo Python not found. Install Python and run: pip install -r requirements.txt & exit /b 1)
pip show flask >nul 2>&1 || pip install -r requirements.txt
python app.py
