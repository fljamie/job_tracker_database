@echo off
title Job Application Tracker
color 0A

echo ============================================
echo    Job Application Tracker v2.03
echo ============================================
echo.

set PORT=8013
set SCRIPT_DIR=%~dp0

:: Remove old shutdown flag
if exist "%SCRIPT_DIR%.shutdown_flag" del "%SCRIPT_DIR%.shutdown_flag" >nul 2>&1

:: Check PHP
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] PHP not found in PATH.
    echo Please see WINDOWS_SETUP.md for install instructions.
    echo.
    pause
    exit /b 1
)

echo [OK] PHP found:
php -v | findstr /R "^PHP"
echo.

:: Check if already running
netstat -an 2>nul | findstr ":%PORT% " | findstr "LISTENING" >nul
if %errorlevel% equ 0 (
    echo [INFO] Port %PORT% already in use - opening browser...
    start http://127.0.0.1:%PORT%
    exit /b 0
)

echo Starting server on http://127.0.0.1:%PORT%
echo Browser will open automatically in 2 seconds.
echo Use the Shutdown button in the app to stop the server.
echo ============================================
echo.

:: Open browser after 2 second delay (non-blocking)
start "" /b cmd /c "timeout /t 2 /nobreak >nul && start http://127.0.0.1:%PORT%"

:: Start PHP server (this keeps the window open - that's fine)
php -S 127.0.0.1:%PORT% -t "%SCRIPT_DIR%"

:: Cleanup after shutdown
if exist "%SCRIPT_DIR%.shutdown_flag" (
    del "%SCRIPT_DIR%.shutdown_flag" >nul 2>&1
    exit
)

echo.
echo Server stopped. Press any key to close...
pause >nul
