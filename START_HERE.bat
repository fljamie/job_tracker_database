@echo off
setlocal EnableDelayedExpansion
title Job Application Tracker - Setup & Launch
color 0A

echo.
echo  ================================================
echo    Job Application Tracker v2.04
echo    Starting setup check...
echo  ================================================
echo.

set SCRIPT_DIR=%~dp0
if "%SCRIPT_DIR:~-1%"=="\" set SCRIPT_DIR=%SCRIPT_DIR:~0,-1%
set PORT=8013
set PHP_OK=0
set CURL_OK=0
set WINGET_OK=0

rem ── Check if already running ─────────────────────────────────────────────
netstat -an 2>nul | findstr ":%PORT% " | findstr "LISTENING" >nul
if %errorlevel% equ 0 (
    echo  [OK] Server already running - opening browser...
    start http://127.0.0.1:%PORT%
    exit /b 0
)

rem ── Check PHP ─────────────────────────────────────────────────────────────
echo  Checking requirements...
echo.
where php >nul 2>nul
if %errorlevel% equ 0 (
    set PHP_OK=1
    for /f "tokens=2 delims= " %%v in ('php -v 2^>nul ^| findstr /R "^PHP"') do (
        echo  [OK] PHP %%v found
    )
) else (
    echo  [MISSING] PHP is not installed or not in PATH
    set PHP_OK=0
)

rem ── Check cURL extension ──────────────────────────────────────────────────
if %PHP_OK%==1 (
    php -r "echo extension_loaded('curl') ? 'OK' : 'MISSING';" 2>nul | findstr "OK" >nul
    if %errorlevel% equ 0 (
        set CURL_OK=1
        echo  [OK] PHP cURL extension enabled
    ) else (
        echo  [WARNING] PHP cURL extension not enabled
        set CURL_OK=0
    )
)

echo.

rem ── All good - just launch ────────────────────────────────────────────────
if %PHP_OK%==1 (
    if %CURL_OK%==1 (
        goto :LAUNCH
    )
)

rem ── Something missing - offer to fix ─────────────────────────────────────
echo  ================================================
echo    Some requirements need attention
echo  ================================================
echo.

if %PHP_OK%==0 (
    echo  PHP is required to run this application.
    echo.
    echo  Install options:
    echo    [1] Auto-install PHP using winget  (Windows 11, recommended^)
    echo    [2] Download PHP manually          (all Windows versions^)
    echo    [3] Exit
    echo.
    set /p CHOICE="  Choose an option (1/2/3): "

    if "!CHOICE!"=="1" goto :INSTALL_WINGET
    if "!CHOICE!"=="2" goto :INSTALL_MANUAL
    if "!CHOICE!"=="3" exit /b 0
    goto :INSTALL_MANUAL
)

if %CURL_OK%==0 (
    echo  PHP is installed but the cURL extension is not enabled.
    echo  This is needed for the AI features.
    echo.
    echo    [1] Show me how to enable it
    echo    [2] Continue without AI features (cURL not required for basic use^)
    echo    [3] Exit
    echo.
    set /p CHOICE="  Choose an option (1/2/3): "
    if "!CHOICE!"=="1" goto :FIX_CURL
    if "!CHOICE!"=="2" goto :LAUNCH
    if "!CHOICE!"=="3" exit /b 0
    goto :LAUNCH
)

rem ── Auto-install via winget ───────────────────────────────────────────────
:INSTALL_WINGET
echo.
echo  Checking for winget (Windows Package Manager)...
where winget >nul 2>nul
if %errorlevel% neq 0 (
    echo  [NOT FOUND] winget is not available on this system.
    echo  Please use option 2 to download PHP manually.
    echo.
    pause
    goto :INSTALL_MANUAL
)

echo  [OK] winget found - installing PHP...
echo.
echo  Running: winget install PHP.PHP
echo  This may take a minute, please wait...
echo.
winget install PHP.PHP --accept-source-agreements --accept-package-agreements
if %errorlevel% equ 0 (
    echo.
    echo  [OK] PHP installed successfully!
    echo.
    echo  IMPORTANT: Please close this window and run START_HERE.bat again.
    echo  (Windows needs to refresh the PATH before PHP is accessible^)
    echo.
    pause
    exit /b 0
) else (
    echo.
    echo  [ERROR] winget install failed. Falling back to manual download.
    goto :INSTALL_MANUAL
)

rem ── Manual download instructions ──────────────────────────────────────────
:INSTALL_MANUAL
echo.
echo  ================================================
echo    Manual PHP Installation
echo  ================================================
echo.
echo  1. A browser window will open to the PHP download page
echo  2. Download "VS16 x64 Thread Safe" ZIP
echo  3. Extract it to C:\php
echo  4. Copy php.ini-development to php.ini in that folder
echo  5. Open php.ini and uncomment these lines (remove the semicolon^):
echo.
echo       extension_dir = "ext"
echo       extension=curl
echo       extension=mbstring
echo       extension=openssl
echo.
echo  6. Add C:\php to your system PATH:
echo     - Search "environment variables" in Start menu
echo     - Click "Environment Variables"
echo     - Edit "Path" under System Variables
echo     - Add: C:\php
echo.
echo  7. Close this window and run START_HERE.bat again
echo.
start https://windows.php.net/download/
echo  Opening https://windows.php.net/download/ in your browser...
echo.
pause
exit /b 0

rem ── Fix cURL instructions ─────────────────────────────────────────────────
:FIX_CURL
echo.
echo  To enable cURL in PHP:
echo.
echo  1. Find your php.ini file by running:  php --ini
echo  2. Open that file in Notepad
echo  3. Find the line:  ;extension=curl
echo  4. Remove the semicolon so it reads:  extension=curl
echo  5. Save the file
echo  6. Close this window and run START_HERE.bat again
echo.
for /f "tokens=*" %%i in ('php --ini 2^>nul ^| findstr "Loaded"') do echo  Your php.ini: %%i
echo.
pause
goto :LAUNCH

rem ── Launch the server ─────────────────────────────────────────────────────
:LAUNCH
echo.
echo  ================================================
echo    Launching Job Application Tracker
echo  ================================================
echo.
echo  Server: http://127.0.0.1:%PORT%
echo  Browser opens automatically in 2 seconds.
echo  Close this window or use the in-app Shutdown button to stop.
echo  ================================================
echo.

if exist "%SCRIPT_DIR%\.shutdown_flag" del "%SCRIPT_DIR%\.shutdown_flag" >nul 2>&1

start "" /b cmd /c "timeout /t 2 /nobreak >nul && start http://127.0.0.1:%PORT%"
php -S 127.0.0.1:%PORT% -t "%SCRIPT_DIR%"

if exist "%SCRIPT_DIR%\.shutdown_flag" (
    del "%SCRIPT_DIR%\.shutdown_flag" >nul 2>&1
    exit /b 0
)

echo.
echo  Server stopped. Press any key to close...
pause >nul
