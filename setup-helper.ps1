# Job Application Tracker - Windows Setup Helper
# Run this script in PowerShell as Administrator

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  Job Application Tracker - Setup Helper" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Function to check if running as admin
function Test-Administrator {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

# Check for admin rights (needed for PATH modification)
if (-not (Test-Administrator)) {
    Write-Host "[WARNING] Not running as Administrator." -ForegroundColor Yellow
    Write-Host "Some features (like adding PHP to PATH) may not work." -ForegroundColor Yellow
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    Write-Host ""
}

# Check if PHP is installed
Write-Host "Checking for PHP installation..." -ForegroundColor White
$phpPath = Get-Command php -ErrorAction SilentlyContinue

if ($phpPath) {
    Write-Host "[OK] PHP is installed at: $($phpPath.Source)" -ForegroundColor Green
    $phpVersion = php -v | Select-Object -First 1
    Write-Host "     Version: $phpVersion" -ForegroundColor Gray
    
    # Check extensions
    Write-Host ""
    Write-Host "Checking PHP extensions..." -ForegroundColor White
    $extensions = php -m
    
    $requiredExtensions = @("PDO", "pdo_mysql", "curl", "json", "mbstring")
    foreach ($ext in $requiredExtensions) {
        if ($extensions -match $ext) {
            Write-Host "[OK] $ext" -ForegroundColor Green
        } else {
            Write-Host "[MISSING] $ext - Enable in php.ini" -ForegroundColor Red
        }
    }
} else {
    Write-Host "[NOT FOUND] PHP is not installed or not in PATH" -ForegroundColor Red
    Write-Host ""
    Write-Host "Would you like to download PHP? (y/n)" -ForegroundColor Yellow
    $response = Read-Host
    
    if ($response -eq 'y' -or $response -eq 'Y') {
        Write-Host ""
        Write-Host "Opening PHP download page..." -ForegroundColor Cyan
        Start-Process "https://windows.php.net/download/"
        Write-Host ""
        Write-Host "Download the 'VS16 x64 Thread Safe' ZIP file" -ForegroundColor Yellow
        Write-Host "Then extract it to C:\php" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "After extracting, run this script again to continue setup." -ForegroundColor Yellow
        Write-Host ""
        Read-Host "Press Enter to exit"
        exit
    }
}

# Check if MySQL is running
Write-Host ""
Write-Host "Checking MySQL status..." -ForegroundColor White
$mysqlService = Get-Service -Name "MySQL*" -ErrorAction SilentlyContinue

if ($mysqlService) {
    foreach ($svc in $mysqlService) {
        if ($svc.Status -eq "Running") {
            Write-Host "[OK] MySQL service '$($svc.Name)' is running" -ForegroundColor Green
        } else {
            Write-Host "[STOPPED] MySQL service '$($svc.Name)' is not running" -ForegroundColor Yellow
            Write-Host "     Start it with: Start-Service '$($svc.Name)'" -ForegroundColor Gray
        }
    }
} else {
    # Try checking if MySQL is listening on port 3306
    $mysqlPort = Get-NetTCPConnection -LocalPort 3306 -ErrorAction SilentlyContinue
    if ($mysqlPort) {
        Write-Host "[OK] Something is listening on MySQL port 3306" -ForegroundColor Green
    } else {
        Write-Host "[WARNING] MySQL service not found and port 3306 not in use" -ForegroundColor Yellow
        Write-Host "     Make sure MySQL is installed and running" -ForegroundColor Gray
    }
}

# Setup project directory
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  Project Setup" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

$defaultPath = "C:\job-tracker"
Write-Host "Where would you like to install the Job Tracker?"
Write-Host "Default: $defaultPath"
$installPath = Read-Host "Path (press Enter for default)"

if ([string]::IsNullOrWhiteSpace($installPath)) {
    $installPath = $defaultPath
}

# Create directory if it doesn't exist
if (-not (Test-Path $installPath)) {
    Write-Host "Creating directory: $installPath" -ForegroundColor Cyan
    New-Item -ItemType Directory -Path $installPath -Force | Out-Null
}

# Check if files are already there
$existingFiles = Get-ChildItem -Path $installPath -Filter "*.html" -ErrorAction SilentlyContinue
if ($existingFiles) {
    Write-Host "[OK] Project files found in $installPath" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "Project directory is ready at: $installPath" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Please copy these files to that directory:" -ForegroundColor Yellow
    Write-Host "  - index.html" -ForegroundColor Gray
    Write-Host "  - api.php" -ForegroundColor Gray
    Write-Host "  - interview_tips_generator.php" -ForegroundColor Gray
    Write-Host "  - start-server.bat" -ForegroundColor Gray
}

# Create a desktop shortcut
Write-Host ""
Write-Host "Would you like to create a desktop shortcut to start the server? (y/n)" -ForegroundColor Yellow
$createShortcut = Read-Host

if ($createShortcut -eq 'y' -or $createShortcut -eq 'Y') {
    $desktopPath = [Environment]::GetFolderPath("Desktop")
    $shortcutPath = Join-Path $desktopPath "Job Tracker Server.lnk"
    $batchPath = Join-Path $installPath "start-server.bat"
    
    $shell = New-Object -ComObject WScript.Shell
    $shortcut = $shell.CreateShortcut($shortcutPath)
    $shortcut.TargetPath = $batchPath
    $shortcut.WorkingDirectory = $installPath
    $shortcut.Description = "Start Job Application Tracker Server"
    $shortcut.Save()
    
    Write-Host "[OK] Desktop shortcut created!" -ForegroundColor Green
}

# Summary
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  Setup Summary" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

if ($phpPath) {
    Write-Host "[OK] PHP is installed and ready" -ForegroundColor Green
} else {
    Write-Host "[ACTION NEEDED] Install PHP from windows.php.net" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Next Steps:" -ForegroundColor White
Write-Host "1. Make sure all project files are in: $installPath" -ForegroundColor Gray
Write-Host "2. Double-click 'start-server.bat' or the desktop shortcut" -ForegroundColor Gray
Write-Host "3. Open http://localhost:8000 in your browser" -ForegroundColor Gray
Write-Host "4. Click the gear icon to configure your MySQL connection" -ForegroundColor Gray
Write-Host ""

# Open the install folder
Write-Host "Would you like to open the project folder? (y/n)" -ForegroundColor Yellow
$openFolder = Read-Host
if ($openFolder -eq 'y' -or $openFolder -eq 'Y') {
    Start-Process explorer.exe $installPath
}

Write-Host ""
Write-Host "Setup helper complete!" -ForegroundColor Green
Write-Host ""
Read-Host "Press Enter to exit"
