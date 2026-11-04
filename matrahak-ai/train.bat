@echo off
setlocal
cd /d "%~dp0"
if not exist ".venv\Scripts\python.exe" (
    echo Run run.bat once first to create the virtual environment.
    pause
    exit /b 1
)
".venv\Scripts\python.exe" -m pip install --disable-pip-version-check -q -r requirements-dev.txt
echo Training and evaluating the model (about 1 minute) ...
".venv\Scripts\python.exe" -m app.ml.train
echo Report: reports\evaluation.md
pause
