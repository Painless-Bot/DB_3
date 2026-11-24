@echo off
chcp 65001 >nul
cd /d "%~dp0"
title Hongik grad-check server

set PY=python
%PY% --version >nul 2>&1 || set PY=py
%PY% --version >nul 2>&1 || (
  echo [ERROR] Python not found. Install Python from https://www.python.org/downloads/
  echo         and check "Add python.exe to PATH" during install.
  pause & exit /b 1
)

echo [1/3] Installing packages...
%PY% -m pip install -q -r requirements.txt || (pause & exit /b 1)

echo [2/3] Preparing database...
%PY% init_db.py --if-needed || (pause & exit /b 1)

echo [3/3] Starting server. Browser will open http://127.0.0.1:5000
echo       Keep this window open. Close it to stop the server.
start "" cmd /c "timeout /t 3 >nul & start http://127.0.0.1:5000"
%PY% run.py
pause
