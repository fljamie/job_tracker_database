# Windows 11 Setup Guide for Job Application Tracker

This guide will help you set up the Job Application Tracker on Windows 11.

## Prerequisites

- Windows 11
- MySQL already installed and running (which you have!)
- Internet connection for downloading PHP

---

## Option 1: Install PHP Standalone (Recommended - Simplest)

This is the easiest option since you already have MySQL.

### Step 1: Download PHP

1. Go to: https://windows.php.net/download/
2. Download the **VS16 x64 Thread Safe** ZIP file (e.g., `php-8.3.x-Win32-vs16-x64.zip`)
3. Choose the latest stable version (PHP 8.2 or 8.3)

### Step 2: Install PHP

1. Create a folder: `C:\php`
2. Extract the downloaded ZIP contents into `C:\php`
3. You should now have files like `C:\php\php.exe`

### Step 3: Configure PHP

1. In `C:\php`, find `php.ini-development` 
2. Copy it and rename the copy to `php.ini`
3. Open `php.ini` in Notepad (Run as Administrator)
4. Find and uncomment (remove the `;` at the start) these lines:
   ```
   extension_dir = "ext"
   extension=curl
   extension=fileinfo
   extension=mbstring
   extension=openssl
   extension=pdo_mysql
   ```
5. Save the file

### Step 4: Add PHP to System PATH

1. Press `Win + X` and select "System"
2. Click "Advanced system settings"
3. Click "Environment Variables"
4. Under "System variables", find and select "Path"
5. Click "Edit"
6. Click "New"
7. Add `C:\php`
8. Click "OK" on all dialogs

### Step 5: Verify Installation

1. Open a **new** Command Prompt or PowerShell
2. Type: `php -v`
3. You should see the PHP version displayed

---

## Option 2: Install Laragon (All-in-One, Modern)

Laragon is a modern, lightweight development environment for Windows.

### Step 1: Download Laragon

1. Go to: https://laragon.org/download/
2. Download "Laragon Full" (includes PHP, MySQL, Apache, etc.)

### Step 2: Install

1. Run the installer
2. Choose installation directory (default is fine)
3. Complete the installation

### Step 3: Configure

1. Open Laragon
2. Since you already have MySQL, you may want to:
   - Go to Menu → Preferences → Services & Ports
   - Uncheck MySQL (to use your existing one)
   - Or change the port if you want both

### Step 4: Add Your Project

1. Copy the job-tracker files to: `C:\laragon\www\job-tracker\`
2. Start Laragon services
3. Access at: http://localhost/job-tracker/

---

## Option 3: Install XAMPP (Traditional All-in-One)

XAMPP includes Apache, MySQL, and PHP together.

### Step 1: Download XAMPP

1. Go to: https://www.apachefriends.org/
2. Download XAMPP for Windows

### Step 2: Install

1. Run the installer
2. Select components: Apache, PHP (uncheck MySQL if you want to use your existing one)
3. Install to default location

### Step 3: Configure for Existing MySQL

Since you have MySQL already:

1. Open XAMPP Control Panel
2. Don't start the MySQL service (use your existing one)
3. Start only Apache

### Step 4: Add Your Project

1. Copy job-tracker files to: `C:\xampp\htdocs\job-tracker\`
2. Access at: http://localhost/job-tracker/

---

## Setting Up the Job Tracker

Once PHP is installed (using any option above):

### Using PHP Built-in Server (Options 1)

1. Create a folder for the project, e.g., `C:\job-tracker\`
2. Copy all the application files there:
   - `index.html`
   - `api.php`
   - `interview_tips_generator.php`
   - `start-server.bat`

3. Double-click `start-server.bat` to start the server
4. Open your browser and go to: http://localhost:8000

### Using Laragon or XAMPP (Options 2 & 3)

1. Copy files to the appropriate www/htdocs folder
2. Start the web server
3. Navigate to the project URL in your browser

---

## Configuring the Database Connection

1. Open the application in your browser
2. Click the ⚙️ (gear/settings) icon in the top right
3. Enter your MySQL details:
   - **Host**: `localhost` (or `127.0.0.1`)
   - **Port**: `3306` (default MySQL port)
   - **Database Name**: `job_tracker` (will be created)
   - **Username**: Your MySQL username (often `root`)
   - **Password**: Your MySQL password

4. Click "Test Connection" to verify
5. Click "Save & Initialize Database" to create tables

---

## Troubleshooting

### "PHP is not recognized"
- Make sure PHP is in your PATH (see Step 4 in Option 1)
- Open a NEW command prompt after adding to PATH
- Restart your computer if needed

### "Access denied" for MySQL
- Verify your MySQL username and password
- Try connecting with MySQL Workbench first to confirm credentials

### "Extension not found" errors
- Make sure you uncommented the extensions in php.ini
- Restart the PHP server after changes

### Port 8000 already in use
- Edit `start-server.bat` and change 8000 to another port like 8080
- Or close whatever is using port 8000

### Can't connect to MySQL on localhost
- Try using `127.0.0.1` instead of `localhost`
- Make sure MySQL service is running (check Windows Services)

---

## Quick Start Summary

**Fastest path to get running:**

1. Download PHP from https://windows.php.net/download/ (Thread Safe x64)
2. Extract to `C:\php`
3. Copy `php.ini-development` to `php.ini`
4. Edit `php.ini` and uncomment: `extension=pdo_mysql` and `extension=curl`
5. Add `C:\php` to your system PATH
6. Put job-tracker files in a folder
7. Double-click `start-server.bat`
8. Open http://localhost:8000
9. Configure your database connection in the app

---

## Need Help?

If you run into issues:

1. Check the troubleshooting section above
2. Make sure MySQL is running (check Services)
3. Verify PHP is working: `php -v` in command prompt
4. Check PHP error log (create one in php.ini if needed)

Good luck with your job search! 🎯
