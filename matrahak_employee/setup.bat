@echo off
REM Matrahak Field (employee app) - first time setup (Windows CMD)
REM Generates the platform folders (android, ios, web, windows) for YOUR Flutter version,
REM keeps the files already in this folder (lib, pubspec, AndroidManifest, icons), then downloads packages.
cd /d "%~dp0"

echo [1/3] flutter create (platform folders)...
call flutter create --org sy.gov.matrahak --project-name matrahak_employee --platforms android,ios,web,windows .
if errorlevel 1 goto :error

echo [2/3] flutter pub get...
call flutter pub get
if errorlevel 1 goto :error

echo [3/3] flutter analyze...
call flutter analyze

echo.
echo Done. Start the Laravel server, then run:  flutter run
exit /b 0

:error
echo.
echo FAILED - see the message above.
exit /b 1
