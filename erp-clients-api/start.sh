#!/bin/bash
cd "$(dirname "$0")"
command -v python3 >/dev/null 2>&1 || command -v python >/dev/null 2>&1 || { echo "Python not found."; exit 1; }
pip install -q -r requirements.txt 2>/dev/null || true
python3 app.py 2>/dev/null || python app.py
