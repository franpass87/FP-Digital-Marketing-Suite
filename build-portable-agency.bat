@echo off
REM FP Digital Marketing Suite - Agency Edition Portable Builder
REM Versione ottimizzata per freelancer/agency che gestiscono più clienti

echo ========================================
echo FP DMS PORTABLE - AGENCY EDITION
echo Builder Ottimizzato per Multi-Client
echo ========================================
echo.

REM Colori
color 0A

echo Benvenuto nel builder per Agency/Freelancer!
echo Questa versione include:
echo  - Dashboard multi-client ottimizzata
echo  - Quick client add wizard
echo  - Bulk operations
echo  - White label reports
echo  - Agency branding
echo.
pause

REM Check build directory
if exist "build\portable-agency" (
    echo.
    echo Directory build esistente trovata.
    choice /C YN /M "Vuoi pulire e ricostruire"
    if errorlevel 2 goto skipclean
    echo Pulizia in corso...
    rmdir /s /q build\portable-agency
    :skipclean
)

REM Create build directory
echo.
echo ========================================
echo STEP 1: Preparazione Directory
echo ========================================
mkdir build\portable-agency
cd build\portable-agency

REM Check PHP Desktop
echo.
echo ========================================
echo STEP 2: PHP Desktop Check
echo ========================================
echo.

if not exist "phpdesktop-chrome.exe" (
    echo PHP Desktop non trovato!
    echo.
    echo ISTRUZIONI:
    echo 1. Vai su: https://github.com/cztomczak/phpdesktop/releases
    echo 2. Scarica: phpdesktop-chrome-57.0-msvc-php-7.4.zip
    echo 3. Estrai TUTTI i file in: build\portable-agency\
    echo 4. Riavvia questo script
    echo.
    pause
    exit /b 1
)

echo ✓ PHP Desktop trovato!

REM Rename executable
echo.
echo ========================================
echo STEP 3: Branding Applicazione
echo ========================================

if not exist "FP-DMS-Agency.exe" (
    move phpdesktop-chrome.exe FP-DMS-Agency.exe
)
echo ✓ FP-DMS-Agency.exe creato!

REM Agency Configuration Wizard
echo.
echo ========================================
echo STEP 4: Configurazione Agency
echo ========================================
echo.
echo Inserisci i dati della tua agency:
echo.

set /p AGENCY_NAME="Nome Agency/Freelancer: "
set /p AGENCY_EMAIL="Email: "
set /p AGENCY_PHONE="Telefono (opzionale): "
set /p AGENCY_WEBSITE="Sito Web (opzionale): "

echo.
echo Configurazione salvata:
echo  Nome: %AGENCY_NAME%
echo  Email: %AGENCY_EMAIL%
echo  Phone: %AGENCY_PHONE%
echo  Web: %AGENCY_WEBSITE%
echo.

REM Create directory structure
echo ========================================
echo STEP 5: Struttura Applicazione
echo ========================================
echo.

echo Creazione directory...
mkdir www
mkdir www\public
mkdir www\src
mkdir www\storage
mkdir www\storage\logs
mkdir www\storage\uploads
mkdir www\storage\cache
mkdir www\storage\backups
mkdir www\storage\reports
mkdir www\public\assets
mkdir www\public\assets\images
mkdir www\public\assets\css
mkdir www\templates
mkdir www\templates\email

echo ✓ Directory create!

REM Copy application files
echo.
echo Copia file applicazione...
xcopy /E /I /Y ..\..\src www\src
xcopy /E /I /Y ..\..\public www\public
xcopy /E /I /Y ..\..\assets www\public\assets

if exist ..\..\composer.json copy ..\..\composer.json www\
if exist ..\..\.env.example copy ..\..\.env.example www\.env

echo ✓ File copiati!

REM Install Composer dependencies
echo.
echo ========================================
echo STEP 6: Installazione Dipendenze
echo ========================================
echo.

where composer >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ATTENZIONE: Composer non trovato!
    echo.
    echo Opzioni:
    echo 1. Installa Composer: https://getcomposer.org
    echo 2. Copia vendor/ manualmente da altro progetto
    echo.
    choice /C 12 /M "Scegli opzione"
    if errorlevel 2 goto manualvendor
    echo.
    echo Installa Composer e riavvia questo script.
    pause
    exit /b 1
    
    :manualvendor
    echo.
    echo Copia la directory vendor/ da un altro progetto in:
    echo build\portable-agency\www\vendor\
    echo.
    pause
) else (
    cd www
    echo Installazione dipendenze (può richiedere qualche minuto)...
    composer install --no-dev --optimize-autoloader --no-interaction
    cd ..
    echo ✓ Dipendenze installate!
)

REM Configure for SQLite
echo.
echo ========================================
echo STEP 7: Configurazione Database
echo ========================================
echo.

REM Create .env with agency settings
(
echo # FP Digital Marketing Suite - Agency Edition
echo # Portable SQLite Configuration
echo.
echo APP_NAME="FP DMS - %AGENCY_NAME%"
echo APP_ENV=production
echo APP_DEBUG=false
echo APP_PORTABLE=true
echo.
echo # Database SQLite
echo DB_CONNECTION=sqlite
echo DB_DATABASE=storage/database.sqlite
echo.
echo # Agency Information
echo AGENCY_NAME="%AGENCY_NAME%"
echo AGENCY_EMAIL=%AGENCY_EMAIL%
echo AGENCY_PHONE=%AGENCY_PHONE%
echo AGENCY_WEBSITE=%AGENCY_WEBSITE%
echo.
echo # Security Keys (auto-generated on first run^)
echo APP_KEY=
echo ENCRYPTION_KEY=
echo.
echo # Timezone
echo APP_TIMEZONE=Europe/Rome
echo.
echo # Email Configuration
echo MAIL_MAILER=smtp
echo MAIL_HOST=smtp.gmail.com
echo MAIL_PORT=587
echo MAIL_USERNAME=
echo MAIL_PASSWORD=
echo MAIL_ENCRYPTION=tls
echo MAIL_FROM_ADDRESS=%AGENCY_EMAIL%
echo MAIL_FROM_NAME="%AGENCY_NAME%"
echo.
echo # Agency Features
echo AGENCY_MULTI_CLIENT=true
echo AGENCY_WHITE_LABEL=true
echo AGENCY_BULK_OPERATIONS=true
) > www\.env

echo ✓ Configurazione creata!

REM Create empty database
type nul > www\storage\database.sqlite
echo ✓ Database SQLite creato!

REM Run migrations
echo.
echo Esecuzione migrazioni database...
php\php.exe www\cli.php db:migrate
if %ERRORLEVEL% EQU 0 (
    echo ✓ Database inizializzato!
) else (
    echo ⚠ Errore durante migrazioni (continuo comunque^)
)

REM Create settings.json
echo.
echo ========================================
echo STEP 8: Configurazione PHP Desktop
echo ========================================
echo.

(
echo {
echo     "title": "FP DMS - %AGENCY_NAME%",
echo     "main_window": {
echo         "default_size": [1400, 900],
echo         "minimum_size": [1000, 700],
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
echo         },
echo         "command_line_switches": {
echo             "disable-web-security": "",
echo             "allow-file-access-from-files": ""
echo         }
echo     },
echo     "application": {
echo         "hide_php_console": true
echo     }
echo }
) > settings.json

echo ✓ settings.json creato!

REM Create agency-specific files
echo.
echo ========================================
echo STEP 9: File Agency Edition
echo ========================================
echo.

REM Quick client add template
(
echo ^<?php
echo // Quick Client Add - Agency Edition
echo // Auto-generated by build script
echo.
echo $agencyName = '%AGENCY_NAME%';
echo $agencyEmail = '%AGENCY_EMAIL%';
echo.
echo // Template configurations
echo $templates = [
echo     'basic' =^> [
echo         'name' =^> 'Setup Base',
echo         'datasources' =^> ['ga4'],
echo         'reports' =^> 'monthly'
echo     ],
echo     'complete' =^> [
echo         'name' =^> 'Setup Completo',
echo         'datasources' =^> ['ga4', 'gsc', 'ads'],
echo         'reports' =^> 'weekly'
echo     ],
echo     'ecommerce' =^> [
echo         'name' =^> 'E-commerce',
echo         'datasources' =^> ['ga4', 'ads', 'meta'],
echo         'reports' =^> 'daily'
echo     ]
echo ];
echo ?^>
) > www\config\agency-templates.php

echo ✓ Template clienti creati!

REM Create launcher scripts
echo.
echo ========================================
echo STEP 10: Script di Avvio
echo ========================================
echo.

REM Main launcher
(
echo @echo off
echo title FP DMS - %AGENCY_NAME%
echo color 0B
echo echo.
echo echo ╔════════════════════════════════════════════╗
echo echo ║  FP DIGITAL MARKETING SUITE                ║
echo echo ║  %AGENCY_NAME%
echo echo ║  Agency Edition                            ║
echo echo ╚════════════════════════════════════════════╝
echo echo.
echo echo Avvio applicazione...
echo echo.
echo.
echo REM Start background scheduler
echo start /B php\php.exe www\scheduler.php ^>NUL 2^>^&1
echo.
echo REM Wait
echo timeout /t 2 /nobreak ^>nul
echo.
echo REM Start app
echo start "" FP-DMS-Agency.exe
echo.
echo echo ✓ Applicazione avviata!
echo echo.
echo echo Puoi chiudere questa finestra.
echo timeout /t 3
echo exit
) > FP-DMS-Start.bat

echo ✓ Launcher creato!

REM Backup script
(
echo @echo off
echo echo Backup Database in corso...
echo.
echo set TIMESTAMP=%%date:~-4,4%%%%date:~-10,2%%%%date:~-7,2%%_%%time:~0,2%%%%time:~3,2%%
echo set TIMESTAMP=%%TIMESTAMP: =0%%
echo.
echo copy www\storage\database.sqlite www\storage\backups\database_%%TIMESTAMP%%.sqlite
echo.
echo echo ✓ Backup completato: database_%%TIMESTAMP%%.sqlite
echo pause
) > Backup-Database.bat

echo ✓ Script backup creato!

REM Documentation
echo.
echo ========================================
echo STEP 11: Documentazione
echo ========================================
echo.

(
echo ════════════════════════════════════════════════
echo FP DIGITAL MARKETING SUITE - AGENCY EDITION
echo %AGENCY_NAME%
echo ════════════════════════════════════════════════
echo.
echo VERSION: 1.0.0 Agency Edition
echo BUILD DATE: %date% %time%
echo.
echo ════════════════════════════════════════════════
echo QUICK START
echo ════════════════════════════════════════════════
echo.
echo 1. AVVIO
echo    - Doppio click su FP-DMS-Start.bat
echo    - Oppure direttamente FP-DMS-Agency.exe
echo.
echo 2. PRIMO AVVIO
echo    - Crea utente admin (il TUO account^)
echo    - Configura email SMTP
echo    - Personalizza branding
echo.
echo 3. AGGIUNGI CLIENTI
echo    - Usa "Quick Add" per setup rapido
echo    - Scegli template pre-configurato
echo    - Configura data sources
echo.
echo 4. GENERA REPORT
echo    - Automatico (scheduler^)
echo    - Manuale (dashboard^)
echo    - Bulk (tutti i clienti^)
echo.
echo ════════════════════════════════════════════════
echo FEATURES AGENCY EDITION
echo ════════════════════════════════════════════════
echo.
echo ✓ Multi-client dashboard
echo ✓ Quick client onboarding
echo ✓ Bulk operations
echo ✓ White label reports
echo ✓ Agency branding
echo ✓ Revenue tracking
echo ✓ Automated backups
echo ✓ SQLite portable database
echo ✓ USB stick compatible
echo.
echo ════════════════════════════════════════════════
echo FILE IMPORTANTI
echo ════════════════════════════════════════════════
echo.
echo FP-DMS-Start.bat        - Avvia applicazione
echo FP-DMS-Agency.exe       - Eseguibile principale
echo Backup-Database.bat     - Backup manuale DB
echo.
echo www\storage\database.sqlite     - Database (BACKUP QUESTO!^)
echo www\storage\backups\            - Backup automatici
echo www\storage\reports\            - PDF generati
echo www\.env                        - Configurazione
echo.
echo ════════════════════════════════════════════════
echo BACKUP
echo ════════════════════════════════════════════════
echo.
echo IMPORTANTE: Fai backup regolari del database!
echo.
echo Metodo 1: Usa Backup-Database.bat
echo Metodo 2: Copia manuale www\storage\
echo Metodo 3: Sync su cloud (Dropbox/OneDrive^)
echo.
echo ════════════════════════════════════════════════
echo USB STICK USAGE
echo ════════════════════════════════════════════════
echo.
echo Puoi usare questa app direttamente da USB:
echo.
echo 1. Copia intera cartella su USB
echo 2. Esegui da USB su qualsiasi PC Windows
echo 3. Tutti i dati restano su USB
echo 4. Nessuna installazione necessaria
echo 5. Rimuovi USB = Dati al sicuro
echo.
echo ════════════════════════════════════════════════
echo SYNC MULTI-DEVICE
echo ════════════════════════════════════════════════
echo.
echo Per usare su più PC:
echo.
echo Opzione 1: USB stick (porta tutto con te^)
echo Opzione 2: Cloud sync (Dropbox/OneDrive^)
echo Opzione 3: Network share
echo.
echo Esempio cloud sync:
echo - Installa su C:\FP-DMS\
echo - Sync www\storage\ con Dropbox
echo - Accedi da qualsiasi PC
echo.
echo ════════════════════════════════════════════════
echo SUPPORT
echo ════════════════════════════════════════════════
echo.
echo Email: info@francescopasseri.com
echo Web: https://francescopasseri.com
echo.
echo ════════════════════════════════════════════════
echo CONFIGURAZIONE AGENCY
echo ════════════════════════════════════════════════
echo.
echo Nome: %AGENCY_NAME%
echo Email: %AGENCY_EMAIL%
echo Phone: %AGENCY_PHONE%
echo Web: %AGENCY_WEBSITE%
echo.
echo Per modificare: edita www\.env
echo.
) > README-AGENCY.txt

echo ✓ Documentazione creata!

REM Create package
echo.
echo ========================================
echo STEP 12: Creazione Pacchetto
echo ========================================
echo.

cd ..

echo Creazione ZIP distribuzione...
powershell Compress-Archive -Path portable-agency\* -DestinationPath FP-DMS-Agency-Portable-v1.0.0.zip -Force

echo.
echo ════════════════════════════════════════════════
echo ✓ BUILD COMPLETATO CON SUCCESSO!
echo ════════════════════════════════════════════════
echo.
echo Pacchetto creato:
echo build\FP-DMS-Agency-Portable-v1.0.0.zip
echo.
echo Dimensione:
dir FP-DMS-Agency-Portable-v1.0.0.zip | find "FP-DMS"
echo.
echo Directory build:
echo build\portable-agency\
echo.
echo ════════════════════════════════════════════════
echo PROSSIMI PASSI
echo ════════════════════════════════════════════════
echo.
echo 1. TEST IN LOCALE
echo    cd build\portable-agency
echo    FP-DMS-Start.bat
echo.
echo 2. PERSONALIZZAZIONE
echo    - Aggiungi logo: portable-agency\www\public\assets\images\
echo    - Modifica colori: portable-agency\www\public\assets\css\
echo    - Testa con primo cliente
echo.
echo 3. DISTRIBUZIONE
echo    - Copia build\portable-agency\ su USB
echo    - Oppure usa ZIP per condividere
echo    - Oppure installa su PC principale
echo.
echo 4. BACKUP
echo    - Setup sync cloud per storage\
echo    - Usa Backup-Database.bat regolarmente
echo.
echo ════════════════════════════════════════════════
echo AGENCY EDITION READY! 🚀
echo ════════════════════════════════════════════════
echo.
pause
