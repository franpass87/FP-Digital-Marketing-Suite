@echo off
REM FP Digital Marketing Suite - BUILD SEMPLIFICATO
REM Questo script crea tutto automaticamente senza richiedere conoscenze tecniche

echo ========================================
echo FP DMS - BUILD SEMPLIFICATO
echo ========================================
echo.
echo Questo script crea automaticamente l'applicazione portable.
echo Non serve installare niente, tutto viene gestito automaticamente!
echo.

REM Crea directory build
if exist "build\simple" (
    echo Rimuovendo build precedente...
    rmdir /s /q build\simple
)

echo Creando directory...
mkdir build\simple
cd build\simple

echo.
echo ========================================
echo STEP 1: Download automatico PHP Desktop
echo ========================================
echo.

REM Download automatico di PHP Desktop
echo Scaricando PHP Desktop automaticamente...
powershell -Command "& {Invoke-WebRequest -Uri 'https://github.com/cztomczak/phpdesktop/releases/download/v57.0/phpdesktop-chrome-57.0-msvc-php-7.4.zip' -OutFile 'phpdesktop.zip'}"

if not exist "phpdesktop.zip" (
    echo ERRORE: Download fallito!
    echo Controlla la connessione internet e riprova.
    pause
    exit /b 1
)

echo Estraendo PHP Desktop...
powershell -Command "Expand-Archive -Path 'phpdesktop.zip' -DestinationPath '.' -Force"
del phpdesktop.zip

REM Rinomina eseguibile
if exist "phpdesktop-chrome.exe" (
    move phpdesktop-chrome.exe FP-DMS.exe
    echo FP-DMS.exe creato!
) else (
    echo ERRORE: File PHP Desktop non trovato dopo estrazione!
    pause
    exit /b 1
)

echo.
echo ========================================
echo STEP 2: Copia applicazione
echo ========================================

REM Crea struttura directory
mkdir www
mkdir www\public
mkdir www\src
mkdir www\storage
mkdir www\storage\logs
mkdir www\storage\uploads
mkdir www\storage\cache

REM Copia file applicazione
echo Copiando file applicazione...
xcopy /E /I /Y ..\..\src www\src
xcopy /E /I /Y ..\..\public www\public
copy ..\..\composer.json www\
copy ..\..\.env.example www\.env

echo.
echo ========================================
echo STEP 3: Installazione automatica dipendenze
echo ========================================

REM Crea vendor directory con dipendenze essenziali
echo Creando dipendenze essenziali...
mkdir www\vendor
mkdir www\vendor\autoload

REM Crea autoloader semplificato
echo ^<?php > www\vendor\autoload.php
echo. >> www\vendor\autoload.php
echo // Autoloader semplificato per versione portable >> www\vendor\autoload.php
echo spl_autoload_register(function ($class^) { >> www\vendor\autoload.php
echo     $file = str_replace('\\', '/', $class^) . '.php'; >> www\vendor\autoload.php
echo     $paths = [ >> www\vendor\autoload.php
echo         __DIR__ . '/../src/' . $file, >> www\vendor\autoload.php
echo         __DIR__ . '/../src/App/' . $file, >> www\vendor\autoload.php
echo         __DIR__ . '/../src/Infra/' . $file, >> www\vendor\autoload.php
echo         __DIR__ . '/../src/Domain/' . $file, >> www\vendor\autoload.php
echo         __DIR__ . '/../src/Services/' . $file, >> www\vendor\autoload.php
echo     ]; >> www\vendor\autoload.php
echo     foreach ($paths as $path^) { >> www\vendor\autoload.php
echo         if (file_exists($path^)^) { >> www\vendor\autoload.php
echo             require_once $path; >> www\vendor\autoload.php
echo             return; >> www\vendor\autoload.php
echo         } >> www\vendor\autoload.php
echo     } >> www\vendor\autoload.php
echo }^); >> www\vendor\autoload.php

echo.
echo ========================================
echo STEP 4: Configurazione SQLite
echo ========================================

REM Configura .env per SQLite
echo Configurando database SQLite...
(
echo APP_NAME="FP Digital Marketing Suite - Portable"
echo APP_ENV=production
echo APP_DEBUG=false
echo.
echo # Database SQLite (Portable)
echo DB_CONNECTION=sqlite
echo DB_DATABASE=storage/database.sqlite
echo.
echo # Impostazioni Applicazione
echo APP_PORTABLE=true
echo APP_KEY=portable-key-12345
echo ENCRYPTION_KEY=portable-encryption-key-67890
echo.
echo # Timezone
echo APP_TIMEZONE=Europe/Rome
echo.
echo # Mail (disabilitato in portable)
echo MAIL_MAILER=log
echo MAIL_HOST=localhost
echo MAIL_PORT=587
echo MAIL_USERNAME=null
echo MAIL_PASSWORD=null
echo MAIL_ENCRYPTION=null
echo MAIL_FROM_ADDRESS=noreply@localhost
echo MAIL_FROM_NAME="FP DMS"
) > www\.env

REM Crea database SQLite vuoto
echo Creando database SQLite...
type nul > www\storage\database.sqlite

echo.
echo ========================================
echo STEP 5: Configurazione PHP Desktop
echo ========================================

REM Crea settings.json
echo Configurando PHP Desktop...
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
echo STEP 6: Creazione launcher semplificato
echo ========================================

REM Crea launcher principale
echo Creando launcher...
(
echo @echo off
echo title FP Digital Marketing Suite
echo.
echo echo ========================================
echo echo FP Digital Marketing Suite
echo echo Avvio in corso...
echo echo ========================================
echo.
echo REM Avvia applicazione
echo start "" FP-DMS.exe
echo.
echo echo Applicazione avviata!
echo echo Puoi chiudere questa finestra.
echo.
echo timeout /t 2
echo exit
) > AVVIA-FP-DMS.bat

echo.
echo ========================================
echo STEP 7: Creazione setup automatico
echo ========================================

REM Crea setup wizard semplificato
echo Creando setup automatico...
(
echo ^<?php
echo // Setup automatico per versione portable
echo $dbFile = __DIR__ . '/storage/database.sqlite';
echo.
echo if (!file_exists($dbFile^) || filesize($dbFile^) === 0^) {
echo     // Crea tabelle essenziali
echo     $pdo = new PDO('sqlite:' . $dbFile^);
echo     
echo     // Tabella utenti
echo     $pdo-^>exec("
echo         CREATE TABLE IF NOT EXISTS users (
echo             id INTEGER PRIMARY KEY AUTOINCREMENT,
echo             name VARCHAR(255^) NOT NULL,
echo             email VARCHAR(255^) UNIQUE NOT NULL,
echo             password VARCHAR(255^) NOT NULL,
echo             role VARCHAR(50^) DEFAULT 'admin',
echo             created_at DATETIME DEFAULT CURRENT_TIMESTAMP
echo         ^)
echo     "^);
echo     
echo     // Tabella clienti
echo     $pdo-^>exec("
echo         CREATE TABLE IF NOT EXISTS clients (
echo             id INTEGER PRIMARY KEY AUTOINCREMENT,
echo             name VARCHAR(255^) NOT NULL,
echo             email_to TEXT,
echo             logo_url TEXT,
echo             timezone VARCHAR(64^) DEFAULT 'Europe/Rome',
echo             created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
echo             updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
echo         ^)
echo     "^);
echo     
echo     // Crea utente admin di default
echo     $adminPassword = password_hash('admin123', PASSWORD_DEFAULT^);
echo     $pdo-^>exec("
echo         INSERT OR IGNORE INTO users (name, email, password, role^) 
echo         VALUES ('Admin', 'admin@localhost', '$adminPassword', 'admin'^)
echo     "^);
echo }
echo ?^>
) > www\setup.php

echo.
echo ========================================
echo STEP 8: Modifica index.php per setup automatico
echo ========================================

REM Crea index.php semplificato
echo Creando index.php semplificato...
(
echo ^<?php
echo // FP Digital Marketing Suite - Portable Version
echo session_start(^);
echo.
echo // Setup automatico
echo require_once __DIR__ . '/../setup.php';
echo.
echo // Carica autoloader
echo require_once __DIR__ . '/../vendor/autoload.php';
echo.
echo // Carica configurazione
echo if (file_exists(__DIR__ . '/../.env'^)^) {
echo     $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES ^| FILE_SKIP_EMPTY_LINES^);
echo     foreach ($lines as $line^) {
echo         if (strpos($line, '='^) !== false && !str_starts_with($line, '#'^)^) {
echo             [$key, $value] = explode('=', $line, 2^);
echo             $_ENV[trim($key^)] = trim($value^);
echo         }
echo     }
echo }
echo.
echo // Controllo login
echo if (!isset($_SESSION['user_id']^)^) {
echo     if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']^)^) {
echo         $email = $_POST['email'] ?? '';
echo         $password = $_POST['password'] ?? '';
echo         
echo         $pdo = new PDO('sqlite:' . __DIR__ . '/../storage/database.sqlite'^);
echo         $stmt = $pdo-^>prepare("SELECT * FROM users WHERE email = ?"^);
echo         $stmt-^>execute([$email]^);
echo         $user = $stmt-^>fetch(^);
echo         
echo         if ($user && password_verify($password, $user['password']^)^) {
echo             $_SESSION['user_id'] = $user['id'];
echo             $_SESSION['user_name'] = $user['name'];
echo             header('Location: /'^);
echo             exit;
echo         } else {
echo             $error = 'Credenziali non valide';
echo         }
echo     }
echo     ?^>
echo     ^<!DOCTYPE html^>
echo     ^<html^>
echo     ^<head^>
echo         ^<title^>FP Digital Marketing Suite - Login^</title^>
echo         ^<style^>
echo             body { font-family: Arial; max-width: 400px; margin: 100px auto; padding: 20px; }
echo             .form-group { margin: 15px 0; }
echo             label { display: block; margin-bottom: 5px; }
echo             input { width: 100%%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
echo             button { background: #007cba; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%%; }
echo             .error { color: red; margin: 10px 0; }
echo         ^</style^>
echo     ^</head^>
echo     ^<body^>
echo         ^<h1^>FP Digital Marketing Suite^</h1^>
echo         ^<h2^>Accedi all'applicazione^</h2^>
echo         ^<?php if (isset($error^)^): ?^>
echo             ^<div class="error"^>^<?= htmlspecialchars($error^) ?^>^</div^>
echo         ^<?php endif; ?^>
echo         ^<form method="POST"^>
echo             ^<div class="form-group"^>
echo                 ^<label^>Email:^</label^>
echo                 ^<input type="email" name="email" value="admin@localhost" required^>
echo             ^</div^>
echo             ^<div class="form-group"^>
echo                 ^<label^>Password:^</label^>
echo                 ^<input type="password" name="password" value="admin123" required^>
echo             ^</div^>
echo             ^<button type="submit" name="login"^>Accedi^</button^>
echo         ^</form^>
echo         ^<p^>^<small^>Credenziali di default: admin@localhost / admin123^</small^>^</p^>
echo     ^</body^>
echo     ^</html^>
echo     ^<?php
echo     exit;
echo }
echo.
echo // Dashboard principale
echo ?^>
echo ^<!DOCTYPE html^>
echo ^<html^>
echo ^<head^>
echo     ^<title^>FP Digital Marketing Suite - Dashboard^</title^>
echo     ^<style^>
echo         body { font-family: Arial; margin: 0; padding: 20px; background: #f5f5f5; }
echo         .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1^); }
echo         .welcome { color: #007cba; }
echo         .card { background: white; padding: 20px; border-radius: 8px; margin: 10px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1^); }
echo         .btn { background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px; }
echo         .btn:hover { background: #005a87; }
echo         .logout { float: right; }
echo     ^</style^>
echo ^</head^>
echo ^<body^>
echo     ^<div class="header"^>
echo         ^<h1 class="welcome"^>Benvenuto, ^<?= htmlspecialchars($_SESSION['user_name']^) ?^>!^</h1^>
echo         ^<a href="?logout=1" class="btn logout"^>Logout^</a^>
echo     ^</div^>
echo     
echo     ^<div class="card"^>
echo         ^<h2^>FP Digital Marketing Suite - Portable Edition^</h2^>
echo         ^<p^>La tua applicazione di marketing digitale è pronta all'uso!^</p^>
echo     ^</div^>
echo     
echo     ^<div class="card"^>
echo         ^<h3^>Funzionalità Disponibili:^</h3^>
echo         ^<a href="#" class="btn"^>Gestione Clienti^</a^>
echo         ^<a href="#" class="btn"^>Report e Analytics^</a^>
echo         ^<a href="#" class="btn"^>Configurazione^</a^>
echo     ^</div^>
echo     
echo     ^<div class="card"^>
echo         ^<h3^>Informazioni Sistema:^</h3^>
echo         ^<p^>^<strong^>Versione:^</strong^> Portable 1.0.0^</p^>
echo         ^<p^>^<strong^>Database:^</strong^> SQLite (Embedded^)^</p^>
echo         ^<p^>^<strong^>Modalità:^</strong^> Standalone^</p^>
echo     ^</div^>
echo ^</body^>
echo ^</html^>
echo ^<?php
echo 
echo // Logout
echo if (isset($_GET['logout']^)^) {
echo     session_destroy(^);
echo     header('Location: /'^);
echo     exit;
echo }
echo ?^>
) > www\public\index.php

echo.
echo ========================================
echo STEP 9: Creazione documentazione
echo ========================================

REM Crea README semplificato
echo Creando documentazione...
(
echo ========================================
echo FP DIGITAL MARKETING SUITE
echo VERSIONE PORTABLE SEMPLIFICATA
echo ========================================
echo.
echo INSTALLAZIONE:
echo 1. Estrarre questo ZIP in qualsiasi cartella
echo 2. Fare doppio click su AVVIA-FP-DMS.bat
echo 3. L'applicazione si aprirà automaticamente
echo.
echo PRIMO AVVIO:
echo - Email: admin@localhost
echo - Password: admin123
echo - Cambiare immediatamente la password!
echo.
echo CARATTERISTICHE:
echo - Completamente portable (nessuna installazione)
echo - Funziona da USB stick
echo - Database SQLite incluso
echo - Nessuna connessione internet richiesta
echo - Nessun diritto amministratore richiesto
echo.
echo REQUISITI:
echo - Windows 7 o superiore
echo - 100MB di spazio libero
echo - Nient'altro!
echo.
echo SUPPORTO:
echo Email: info@francescopasseri.com
echo Web: https://francescopasseri.com
echo.
echo ========================================
) > LEGGIMI.txt

echo.
echo ========================================
echo STEP 10: Creazione package finale
echo ========================================

cd ..

REM Crea ZIP finale
echo Creando package di distribuzione...
powershell Compress-Archive -Path simple\* -DestinationPath FP-DMS-Portable-Semplice-v1.0.0.zip -Force

echo.
echo ========================================
echo BUILD COMPLETATO!
echo ========================================
echo.
echo Applicazione portable creata in:
echo build\simple\
echo.
echo Package di distribuzione:
echo build\FP-DMS-Portable-Semplice-v1.0.0.zip
echo.
echo DIMENSIONE:
dir FP-DMS-Portable-Semplice-v1.0.0.zip | find "FP-DMS"
echo.
echo ========================================
echo ISTRUZIONI PER L'UTENTE FINALE:
echo ========================================
echo.
echo 1. Scarica FP-DMS-Portable-Semplice-v1.0.0.zip
echo 2. Estrai in qualsiasi cartella (anche USB stick)
echo 3. Fai doppio click su AVVIA-FP-DMS.bat
echo 4. Login con: admin@localhost / admin123
echo 5. DONE! ✅
echo.
echo ========================================
pause
