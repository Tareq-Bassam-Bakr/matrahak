@echo off
setlocal
cd /d "%~dp0"

if not exist ".venv\Scripts\python.exe" (
    echo [1/3] Creating virtual environment .venv ...
    py -3 -m venv .venv 2>nul || python -m venv .venv
    if errorlevel 1 (
        echo Python was not found. Install Python 3.10+ from python.org and tick "Add python.exe to PATH".
        pause
        exit /b 1
    )
)

echo [2/3] Installing requirements ...
".venv\Scripts\python.exe" -m pip install --disable-pip-version-check -q -r requirements.txt
if errorlevel 1 (
    echo Failed to install requirements. Check your internet connection and try again.
    pause
    exit /b 1
)

if not exist ".env" copy ".env.example" ".env" >nul

echo [3/3] Starting Matrahak AI service on http://127.0.0.1:8001  (API docs: http://127.0.0.1:8001/docs)
".venv\Scripts\python.exe" -m uvicorn app.main:app --host 127.0.0.1 --port 8001
pause
