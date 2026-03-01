@echo off
title Job Application Tracker - Setup & Launch
color 0A
cd /d "%~dp0"

set PORT=8013
set PHP_EXE=

echo.
echo  =====================================================
echo    Job Application Tracker v2.06
echo  =====================================================
echo.

REM ── Check if already running ──────────────────────────────────────────────
netstat -an 2>nul | findstr ":%PORT% " | findstr "LISTENING" >nul 2>&1
if not errorlevel 1 (
    echo  [INFO] Server is already running on port %PORT%.
    echo         Opening browser...
    echo.
    start http://127.0.0.1:%PORT%
    echo  Press any key to close this window.
    pause >nul
    exit /b 0
)

REM ── Find PHP ──────────────────────────────────────────────────────────────
echo  [1/3] Searching for PHP...

where php >nul 2>&1
if not errorlevel 1 (
    for /f "usebackq tokens=*" %%P in (`where php`) do (
        set PHP_EXE=%%P
        goto :found_php
    )
)

if exist "C:\php\php.exe"                       ( set PHP_EXE=C:\php\php.exe                       & goto :found_php )
if exist "C:\php8\php.exe"                      ( set PHP_EXE=C:\php8\php.exe                      & goto :found_php )
if exist "%LOCALAPPDATA%\Programs\PHP\php.exe"  ( set PHP_EXE=%LOCALAPPDATA%\Programs\PHP\php.exe  & goto :found_php )
if exist "%PROGRAMFILES%\PHP\php.exe"           ( set PHP_EXE=%PROGRAMFILES%\PHP\php.exe           & goto :found_php )

goto :install_php

:found_php
echo  [OK] PHP found: %PHP_EXE%
echo.
goto :enable_curl

REM ── Auto-install PHP ──────────────────────────────────────────────────────
:install_php
echo  [!!] PHP not found - attempting auto-install...
echo.

where winget >nul 2>&1
if not errorlevel 1 (
    echo  Trying winget (Windows Package Manager)...
    winget install PHP.PHP --silent --accept-source-agreements --accept-package-agreements >nul 2>&1
    timeout /t 5 /nobreak >nul
    where php >nul 2>&1
    if not errorlevel 1 (
        for /f "usebackq tokens=*" %%P in (`where php`) do set PHP_EXE=%%P
        echo  [OK] PHP installed via winget.
        echo.
        goto :enable_curl
    )
)

echo  Trying direct download from windows.php.net (~30MB)...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$ErrorActionPreference='Stop';" ^
    "try {" ^
    "  [Net.ServicePointManager]::SecurityProtocol='Tls12';" ^
    "  $page=Invoke-WebRequest -Uri 'https://windows.php.net/download/' -UseBasicParsing;" ^
    "  $href=($page.Links | Where-Object { $_.href -match 'php-8.*-nts-Win32-vs16-x64\.zip' } | Select-Object -First 1).href;" ^
    "  if(!$href){throw 'No download link found'}" ^
    "  if($href -notmatch '^http'){$href='https://windows.php.net'+$href}" ^
    "  Write-Host ('  Downloading: '+$href);" ^
    "  Invoke-WebRequest -Uri $href -OutFile '$env:TEMP\php_dl.zip';" ^
    "  if(!(Test-Path 'C:\php')){New-Item -Type Directory 'C:\php'|Out-Null}" ^
    "  Expand-Archive -Path '$env:TEMP\php_dl.zip' -DestinationPath 'C:\php' -Force;" ^
    "  Remove-Item '$env:TEMP\php_dl.zip' -ErrorAction SilentlyContinue;" ^
    "  Write-Host 'DONE'" ^
    "} catch { Write-Host ('FAIL: '+$_.Exception.Message) }" 2>&1

if exist "C:\php\php.exe" (
    set PHP_EXE=C:\php\php.exe
    echo  [OK] PHP extracted to C:\php
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
        "$p=[Environment]::GetEnvironmentVariable('PATH','User');" ^
        "if($p -notlike '*C:\php*'){[Environment]::SetEnvironmentVariable('PATH',$p+';C:\php','User')}" >nul 2>&1
    echo.
    goto :enable_curl
)

echo.
echo  =====================================================
echo    PHP AUTO-INSTALL FAILED
echo  =====================================================
echo.
echo  Please install PHP manually:
echo    1. Go to: https://windows.php.net/download/
echo    2. Download: VS16 x64 Non Thread Safe ZIP
echo    3. Extract to: C:\php
echo    4. Copy php.ini-development ^=^> php.ini
echo    5. In php.ini, remove the semicolons before:
echo         extension_dir = "ext"
echo         extension=curl
echo         extension=mbstring
echo         extension=openssl
echo    6. Add C:\php to your system PATH
echo    7. Run this file again
echo.
start https://windows.php.net/download/
echo  Press any key to close...
pause >nul
exit /b 1

REM ── Enable cURL in php.ini ────────────────────────────────────────────────
:enable_curl
echo  [2/3] Checking PHP extensions (cURL, mbstring, openssl)...

"%PHP_EXE%" -r "echo extension_loaded('curl') ? 'CURL_OK' : 'MISS';" 2>nul | findstr "CURL_OK" >nul 2>&1
if not errorlevel 1 (
    echo  [OK] cURL is active
    echo.
    goto :launch
)

echo  [..] cURL not active - enabling in php.ini...
for /f "usebackq tokens=*" %%F in (`"%PHP_EXE%" -r "echo php_ini_loaded_file();" 2^>nul`) do set PHP_INI=%%F

if "%PHP_INI%"=="" (
    REM Try to create php.ini from php.ini-development
    for /f "usebackq tokens=*" %%D in (`"%PHP_EXE%" -r "echo dirname(php_ini_loaded_file());" 2^>nul`) do set PHP_DIR=%%D
    if exist "%PHP_DIR%\php.ini-development" (
        copy "%PHP_DIR%\php.ini-development" "%PHP_DIR%\php.ini" >nul 2>&1
        for /f "usebackq tokens=*" %%F in (`"%PHP_EXE%" -r "echo php_ini_loaded_file();" 2^>nul`) do set PHP_INI=%%F
    )
)

if not "%PHP_INI%"=="" if exist "%PHP_INI%" (
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
        "$f='%PHP_INI%';" ^
        "$c=Get-Content $f;" ^
        "$c=$c -replace '^;(extension_dir = .ext.)','$1';" ^
        "$c=$c -replace '^;(extension=curl\b)','$1';" ^
        "$c=$c -replace '^;(extension=mbstring)','$1';" ^
        "$c=$c -replace '^;(extension=openssl)','$1';" ^
        "$c|Set-Content $f" >nul 2>&1
    echo  [OK] php.ini updated. Extensions enabled.
) else (
    echo  [WARN] Could not locate php.ini. AI features may be limited.
)
echo.

REM ── Launch ────────────────────────────────────────────────────────────────
:launch
echo  [3/3] Starting server...
echo.
echo  =====================================================
echo    Running at: http://127.0.0.1:%PORT%
echo.
echo    Keep this window open while using the app.
echo    Close this window to stop the server.
echo  =====================================================
echo.

REM Open browser after a short delay
start "" /b cmd /c "timeout /t 2 /nobreak >nul && start http://127.0.0.1:%PORT%"

REM Run PHP built-in server (blocking - keeps window alive)
"%PHP_EXE%" -S 127.0.0.1:%PORT% -t "%~dp0"

echo.
echo Server stopped. Closing...
timeout /t 2 /nobreak >nul
exit
