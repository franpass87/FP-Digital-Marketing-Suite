@echo off
REM FP Digital Marketing Suite - Portable Build Script for Windows
REM This script creates a portable .exe application

echo ========================================
echo FP DMS Portable Builder
echo ========================================
echo.

REM Check if build directory exists
if exist "build\portable" (
    echo Cleaning previous build...
    rmdir /s /q build\portable
)

REM Create build directory
echo Creating build directory...
mkdir build\portable
cd build\portable

REM Download PHP Desktop (you need to do this manually first time)
echo.
echo ========================================
echo STEP 1: PHP Desktop Setup
echo ========================================
echo.
echo Please download PHP Desktop from:
echo https://github.com/cztomczak/phpdesktop/releases
echo.
echo Download: phpdesktop-chrome-57.0-msvc-php-7.4.zip
echo Extract it in build\portable\
echo.
pause

REM Check if PHP Desktop exists
if not exist "phpdesktop-chrome.exe" (
    echo ERROR: PHP Desktop not found!
    echo Please extract PHP Desktop files in build\portable\
    pause
    exit /b 1
)

echo.
echo ========================================
echo STEP 2: Renaming executable
echo ========================================
move phpdesktop-chrome.exe FP-DMS.exe
echo FP-DMS.exe created!

echo.
echo ========================================
echo STEP 3: Copying application files
echo ========================================

REM Create www directory structure
mkdir www
mkdir www\public
mkdir www\src
mkdir www\storage
mkdir www\storage\logs
mkdir www\storage\uploads
mkdir www\storage\cache

REM Copy application files
echo Copying source files...
xcopy /E /I /Y ..\..\src www\src
xcopy /E /I /Y ..\..\public www\public
copy ..\..\composer.json www\
copy ..\..\.env.example www\.env

echo.
echo ========================================
echo STEP 4: Installing Composer dependencies
echo ========================================
cd www

REM Check if composer is installed
where composer >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Composer not found!
    echo Please install Composer first: https://getcomposer.org
    pause
    exit /b 1
)

echo Installing dependencies (production only)...
composer install --no-dev --optimize-autoloader --no-interaction

cd ..

echo.
echo ========================================
echo STEP 5: Configuring for SQLite
echo ========================================

REM Update .env for SQLite
echo Updating configuration...
(
echo APP_NAME="FP Digital Marketing Suite - Portable"
echo APP_ENV=production
echo APP_DEBUG=false
echo.
echo # SQLite Database (Portable)
echo DB_CONNECTION=sqlite
echo DB_DATABASE=storage/database.sqlite
echo.
echo # Application Settings
echo APP_PORTABLE=true
echo APP_KEY=
echo ENCRYPTION_KEY=
echo.
echo # Timezone
echo APP_TIMEZONE=UTC
) > www\.env

echo.
echo ========================================
echo STEP 6: Creating SQLite database
echo ========================================

REM Create empty SQLite database
type nul > www\storage\database.sqlite

REM Run migrations
echo Running database migrations...
php\php.exe www\cli.php db:migrate

echo.
echo ========================================
echo STEP 7: Creating configuration files
echo ========================================

REM Create settings.json for PHP Desktop
echo Creating settings.json...
(
echo {
echo     "title": "FP Digital Marketing Suite",
echo     "main_window": {
echo         "default_size": [1200, 800],
echo         "minimum_size": [800, 600],
echo         "center_on_screen": true,
echo         "start_maximized": false
echo     },
echo     "web_server": {
echo         "listen_on": ["127.0.0.1", 8080],
echo         "www_directory": "www/public",
echo         "index_files": ["index.php"],
echo         "cgi_interpreter": "php/php-cgi.exe",
echo         "cgi_extensions": ["php"]
echo     },
echo     "chrome": {
echo         "cache_path": "webcache",
echo         "context_menu": {
echo             "enable_menu": false
echo         }
echo     },
echo     "application": {
echo         "hide_php_console": true
echo     }
echo }
) > settings.json

echo.
echo ========================================
echo STEP 8: Creating launcher scripts
echo ========================================

REM Create main launcher
echo Creating FP-DMS-Launcher.bat...
(
echo @echo off
echo title FP Digital Marketing Suite - Portable
echo echo.
echo echo ========================================
echo echo FP Digital Marketing Suite
echo echo Portable Edition
echo echo ========================================
echo echo.
echo echo Starting application...
echo echo.
echo.
echo REM Start background scheduler
echo start /B php\php.exe www\scheduler.php ^>NUL 2^>^&1
echo.
echo REM Wait a moment
echo timeout /t 2 /nobreak ^>nul
echo.
echo REM Start main application
echo start "" FP-DMS.exe
echo.
echo echo Application started!
echo echo You can close this window.
echo echo.
echo timeout /t 3
echo exit
) > FP-DMS-Launcher.bat

REM Create scheduler script
echo Creating scheduler.php...
(
echo ^<?php
echo // Background Scheduler for Portable Version
echo.
echo require __DIR__ . '/vendor/autoload.php';
echo.
echo $dotenv = Dotenv\Dotenv::createImmutable(__DIR__^);
echo $dotenv-^>safeLoad(^);
echo.
echo use FP\DMS\App\ScheduleProvider;
echo use FP\DMS\Infra\Scheduler;
echo.
echo // Run continuously
echo while (true^) {
echo     try {
echo         $scheduler = new Scheduler(^);
echo         ScheduleProvider::register($scheduler^);
echo         $scheduler-^>run(^);
echo         
echo         file_put_contents(
echo             'storage/logs/scheduler.log',
echo             date('Y-m-d H:i:s'^) . " - Scheduler tick completed\n",
echo             FILE_APPEND
echo         ^);
echo     } catch (Exception $e^) {
echo         file_put_contents(
echo             'storage/logs/scheduler-error.log',
echo             date('Y-m-d H:i:s'^) . " - Error: " . $e-^>getMessage(^) . "\n",
echo             FILE_APPEND
echo         ^);
echo     }
echo     
echo     sleep(60^); // 1 minute
echo }
) > www\scheduler.php

echo.
echo ========================================
echo STEP 9: Creating documentation
echo ========================================

REM Create README
echo Creating README.txt...
(
echo ========================================
echo FP DIGITAL MARKETING SUITE
echo Portable Edition
echo ========================================
echo.
echo VERSION: 1.0.0
echo BUILD DATE: %date% %time%
echo.
echo ========================================
echo QUICK START
echo ========================================
echo.
echo 1. Double-click FP-DMS.exe to start the application
echo.
echo 2. On first run, follow the setup wizard to:
echo    - Initialize the database
echo    - Create admin user
echo    - Configure basic settings
echo.
echo 3. Login with your credentials
echo.
echo 4. Start using FP DMS!
echo.
echo ========================================
echo FEATURES
echo ========================================
echo.
echo - Completely portable (no installation needed^)
echo - Works from USB stick or any folder
echo - SQLite database included
echo - Background scheduler for automated tasks
echo - No internet connection required
echo - No administrator rights needed
echo.
echo ========================================
echo REQUIREMENTS
echo ========================================
echo.
echo - Windows 7 or higher
echo - 100MB free disk space
echo - No additional software required!
echo.
echo ========================================
echo TROUBLESHOOTING
echo ========================================
echo.
echo If the application doesn't start:
echo.
echo 1. Make sure no other application is using port 8080
echo 2. Check storage\logs\error.log for details
echo 3. Try running as administrator (shouldn't be needed^)
echo 4. Contact support: info@francescopasseri.com
echo.
echo ========================================
echo SUPPORT
echo ========================================
echo.
echo Email: info@francescopasseri.com
echo Web: https://francescopasseri.com
echo.
echo ========================================
echo LICENSE
echo ========================================
echo.
echo GPLv2 or later
echo See LICENSE.txt for details
echo.
) > README.txt

echo.
echo ========================================
echo STEP 10: Creating package
echo ========================================

cd ..

REM Create ZIP package
echo Creating distribution package...
powershell Compress-Archive -Path portable\* -DestinationPath FP-DMS-Portable-v1.0.0.zip -Force

echo.
echo ========================================
echo BUILD COMPLETE!
echo ========================================
echo.
echo Portable application created in:
echo build\portable\
echo.
echo Distribution package:
echo build\FP-DMS-Portable-v1.0.0.zip
echo.
echo Size: 
dir FP-DMS-Portable-v1.0.0.zip | find "FP-DMS"
echo.
echo You can now distribute FP-DMS-Portable-v1.0.0.zip
echo Users just need to extract and run FP-DMS.exe!
echo.
echo ========================================
pause
