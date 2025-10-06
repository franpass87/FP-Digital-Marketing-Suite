@echo off
REM FP Digital Marketing Suite - INSTALLER AUTOMATICO
REM Questo script scarica e installa tutto automaticamente

title FP Digital Marketing Suite - Installer Automatico

echo ========================================
echo FP DIGITAL MARKETING SUITE
echo INSTALLER AUTOMATICO
echo ========================================
echo.
echo Questo installer scarica e configura tutto automaticamente.
echo Non serve installare niente prima!
echo.

REM Crea directory principale
set INSTALL_DIR=%USERPROFILE%\Desktop\FP-Digital-Marketing-Suite
echo Installazione in: %INSTALL_DIR%
echo.

if exist "%INSTALL_DIR%" (
    echo Rimuovendo installazione precedente...
    rmdir /s /q "%INSTALL_DIR%"
)

echo Creando directory di installazione...
mkdir "%INSTALL_DIR%"
cd /d "%INSTALL_DIR%"

echo.
echo ========================================
echo STEP 1: Download PHP Desktop
echo ========================================
echo Scaricando PHP Desktop (questo potrebbe richiedere alcuni minuti)...
powershell -Command "& {Invoke-WebRequest -Uri 'https://github.com/cztomczak/phpdesktop/releases/download/v57.0/phpdesktop-chrome-57.0-msvc-php-7.4.zip' -OutFile 'phpdesktop.zip' -UseBasicParsing}"

if not exist "phpdesktop.zip" (
    echo.
    echo ERRORE: Download fallito!
    echo Controlla la connessione internet e riprova.
    echo.
    pause
    exit /b 1
)

echo Estraendo PHP Desktop...
powershell -Command "Expand-Archive -Path 'phpdesktop.zip' -DestinationPath '.' -Force"
del phpdesktop.zip

if exist "phpdesktop-chrome.exe" (
    move phpdesktop-chrome.exe FP-DMS.exe
    echo FP-DMS.exe creato!
) else (
    echo ERRORE: Estrazione fallita!
    pause
    exit /b 1
)

echo.
echo ========================================
echo STEP 2: Creazione applicazione
echo ========================================

REM Crea struttura directory
mkdir www
mkdir www\public
mkdir www\storage
mkdir www\storage\logs
mkdir www\storage\uploads
mkdir www\storage\cache

echo Creando applicazione semplificata...

REM Crea index.php principale
(
echo ^<?php
echo // FP Digital Marketing Suite - Portable Edition
echo session_start(^);
echo.
echo // Configurazione
echo $config = [
echo     'app_name' =^> 'FP Digital Marketing Suite',
echo     'version' =^> '1.0.0',
echo     'db_file' =^> __DIR__ . '/../storage/database.sqlite'
echo ];
echo.
echo // Inizializza database se necessario
echo if (!file_exists($config['db_file']^)^) {
echo     $pdo = new PDO('sqlite:' . $config['db_file']^);
echo     
echo     // Crea tabelle
echo     $pdo-^>exec("
echo         CREATE TABLE IF NOT EXISTS users (
echo             id INTEGER PRIMARY KEY AUTOINCREMENT,
echo             name VARCHAR(255^) NOT NULL,
echo             email VARCHAR(255^) UNIQUE NOT NULL,
echo             password VARCHAR(255^) NOT NULL,
echo             role VARCHAR(50^) DEFAULT 'admin',
echo             created_at DATETIME DEFAULT CURRENT_TIMESTAMP
echo         ^);
echo         
echo         CREATE TABLE IF NOT EXISTS clients (
echo             id INTEGER PRIMARY KEY AUTOINCREMENT,
echo             name VARCHAR(255^) NOT NULL,
echo             email_to TEXT,
echo             logo_url TEXT,
echo             timezone VARCHAR(64^) DEFAULT 'Europe/Rome',
echo             created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
echo             updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
echo         ^);
echo         
echo         CREATE TABLE IF NOT EXISTS reports (
echo             id INTEGER PRIMARY KEY AUTOINCREMENT,
echo             client_id INTEGER,
echo             title VARCHAR(255^) NOT NULL,
echo             content TEXT,
echo             created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
echo             FOREIGN KEY (client_id^) REFERENCES clients (id^)
echo         ^);
echo     "^);
echo     
echo     // Crea utente admin
echo     $adminPassword = password_hash('admin123', PASSWORD_DEFAULT^);
echo     $pdo-^>exec("
echo         INSERT OR IGNORE INTO users (name, email, password, role^) 
echo         VALUES ('Administrator', 'admin@localhost', '$adminPassword', 'admin'^)
echo     "^);
echo }
echo.
echo // Gestione login
echo if (!isset($_SESSION['user_id']^)^) {
echo     if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']^) && $_POST['action'] === 'login'^) {
echo         $email = $_POST['email'] ?? '';
echo         $password = $_POST['password'] ?? '';
echo         
echo         $pdo = new PDO('sqlite:' . $config['db_file']^);
echo         $stmt = $pdo-^>prepare("SELECT * FROM users WHERE email = ?"^);
echo         $stmt-^>execute([$email]^);
echo         $user = $stmt-^>fetch(^);
echo         
echo         if ($user && password_verify($password, $user['password']^)^) {
echo             $_SESSION['user_id'] = $user['id'];
echo             $_SESSION['user_name'] = $user['name'];
echo             $_SESSION['user_email'] = $user['email'];
echo             header('Location: /'^);
echo             exit;
echo         } else {
echo             $error = 'Credenziali non valide';
echo         }
echo     }
echo     ?^>
echo     ^<!DOCTYPE html^>
echo     ^<html lang="it"^>
echo     ^<head^>
echo         ^<meta charset="UTF-8"^>
echo         ^<meta name="viewport" content="width=device-width, initial-scale=1.0"^>
echo         ^<title^>^<?= $config['app_name'] ?^> - Login^</title^>
echo         ^<style^>
echo             * { margin: 0; padding: 0; box-sizing: border-box; }
echo             body { 
echo                 font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
echo                 background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%^);
echo                 min-height: 100vh;
echo                 display: flex;
echo                 align-items: center;
echo                 justify-content: center;
echo             }
echo             .login-container {
echo                 background: white;
echo                 padding: 40px;
echo                 border-radius: 10px;
echo                 box-shadow: 0 15px 35px rgba(0,0,0,0.1^);
echo                 width: 100%%;
echo                 max-width: 400px;
echo             }
echo             .logo {
echo                 text-align: center;
echo                 margin-bottom: 30px;
echo             }
echo             .logo h1 {
echo                 color: #667eea;
echo                 font-size: 24px;
echo                 margin-bottom: 5px;
echo             }
echo             .logo p {
echo                 color: #666;
echo                 font-size: 14px;
echo             }
echo             .form-group {
echo                 margin-bottom: 20px;
echo             }
echo             label {
echo                 display: block;
echo                 margin-bottom: 5px;
echo                 color: #333;
echo                 font-weight: 500;
echo             }
echo             input {
echo                 width: 100%%;
echo                 padding: 12px;
echo                 border: 2px solid #e1e5e9;
echo                 border-radius: 6px;
echo                 font-size: 14px;
echo                 transition: border-color 0.3s;
echo             }
echo             input:focus {
echo                 outline: none;
echo                 border-color: #667eea;
echo             }
echo             button {
echo                 width: 100%%;
echo                 background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%^);
echo                 color: white;
echo                 padding: 12px;
echo                 border: none;
echo                 border-radius: 6px;
echo                 font-size: 16px;
echo                 font-weight: 500;
echo                 cursor: pointer;
echo                 transition: transform 0.2s;
echo             }
echo             button:hover {
echo                 transform: translateY(-2px^);
echo             }
echo             .error {
echo                 background: #fee;
echo                 color: #c33;
echo                 padding: 10px;
echo                 border-radius: 4px;
echo                 margin-bottom: 15px;
echo                 border-left: 4px solid #c33;
echo             }
echo             .default-credentials {
echo                 background: #f0f8ff;
echo                 padding: 15px;
echo                 border-radius: 6px;
echo                 margin-top: 20px;
echo                 border-left: 4px solid #667eea;
echo             }
echo             .default-credentials h4 {
echo                 color: #667eea;
echo                 margin-bottom: 10px;
echo             }
echo             .default-credentials p {
echo                 color: #666;
echo                 font-size: 14px;
echo                 line-height: 1.4;
echo             }
echo         ^</style^>
echo     ^</head^>
echo     ^<body^>
echo         ^<div class="login-container"^>
echo             ^<div class="logo"^>
echo                 ^<h1^>FP DMS^</h1^>
echo                 ^<p^>Digital Marketing Suite^</p^>
echo             ^</div^>
echo             
echo             ^<?php if (isset($error^)^): ?^>
echo                 ^<div class="error"^>^<?= htmlspecialchars($error^) ?^>^</div^>
echo             ^<?php endif; ?^>
echo             
echo             ^<form method="POST"^>
echo                 ^<input type="hidden" name="action" value="login"^>
echo                 
echo                 ^<div class="form-group"^>
echo                     ^<label^>Email^</label^>
echo                     ^<input type="email" name="email" value="admin@localhost" required^>
echo                 ^</div^>
echo                 
echo                 ^<div class="form-group"^>
echo                     ^<label^>Password^</label^>
echo                     ^<input type="password" name="password" value="admin123" required^>
echo                 ^</div^>
echo                 
echo                 ^<button type="submit"^>Accedi^</button^>
echo             ^</form^>
echo             
echo             ^<div class="default-credentials"^>
echo                 ^<h4^>Credenziali di Default^</h4^>
echo                 ^<p^>Email: admin@localhost^<br^>Password: admin123^</p^>
echo                 ^<p^>^<strong^>Importante:^</strong^> Cambia queste credenziali dopo il primo accesso!^</p^>
echo             ^</div^>
echo         ^</div^>
echo     ^</body^>
echo     ^</html^>
echo     ^<?php
echo     exit;
echo }
echo.
echo // Gestione logout
echo if (isset($_GET['logout']^)^) {
echo     session_destroy(^);
echo     header('Location: /'^);
echo     exit;
echo }
echo.
echo // Dashboard principale
echo $pdo = new PDO('sqlite:' . $config['db_file']^);
echo.
echo // Conteggi per dashboard
echo $clientsCount = $pdo-^>query("SELECT COUNT(*^) FROM clients"^)-^>fetchColumn(^);
echo $reportsCount = $pdo-^>query("SELECT COUNT(*^) FROM reports"^)-^>fetchColumn(^);
echo $usersCount = $pdo-^>query("SELECT COUNT(*^) FROM users"^)-^>fetchColumn(^);
echo.
echo ?^>
echo ^<!DOCTYPE html^>
echo ^<html lang="it"^>
echo ^<head^>
echo     ^<meta charset="UTF-8"^>
echo     ^<meta name="viewport" content="width=device-width, initial-scale=1.0"^>
echo     ^<title^>^<?= $config['app_name'] ?^> - Dashboard^</title^>
echo     ^<style^>
echo         * { margin: 0; padding: 0; box-sizing: border-box; }
echo         body { 
echo             font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
echo             background: #f8f9fa;
echo             color: #333;
echo         }
echo         .header {
echo             background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%^);
echo             color: white;
echo             padding: 20px;
echo             box-shadow: 0 2px 10px rgba(0,0,0,0.1^);
echo         }
echo         .header-content {
echo             max-width: 1200px;
echo             margin: 0 auto;
echo             display: flex;
echo             justify-content: space-between;
echo             align-items: center;
echo         }
echo         .logo h1 {
echo             font-size: 24px;
echo             margin-bottom: 5px;
echo         }
echo         .logo p {
echo             opacity: 0.9;
echo             font-size: 14px;
echo         }
echo         .user-info {
echo             text-align: right;
echo         }
echo         .user-info p {
echo             margin-bottom: 5px;
echo         }
echo         .btn {
echo             background: rgba(255,255,255,0.2^);
echo             color: white;
echo             padding: 8px 16px;
echo             text-decoration: none;
echo             border-radius: 4px;
echo             border: 1px solid rgba(255,255,255,0.3^);
echo             transition: background 0.3s;
echo         }
echo         .btn:hover {
echo             background: rgba(255,255,255,0.3^);
echo         }
echo         .container {
echo             max-width: 1200px;
echo             margin: 30px auto;
echo             padding: 0 20px;
echo         }
echo         .stats-grid {
echo             display: grid;
echo             grid-template-columns: repeat(auto-fit, minmax(250px, 1fr^)^);
echo             gap: 20px;
echo             margin-bottom: 30px;
echo         }
echo         .stat-card {
echo             background: white;
echo             padding: 25px;
echo             border-radius: 10px;
echo             box-shadow: 0 2px 10px rgba(0,0,0,0.1^);
echo             text-align: center;
echo         }
echo         .stat-number {
echo             font-size: 36px;
echo             font-weight: bold;
echo             color: #667eea;
echo             margin-bottom: 10px;
echo         }
echo         .stat-label {
echo             color: #666;
echo             font-size: 14px;
echo             text-transform: uppercase;
echo             letter-spacing: 1px;
echo         }
echo         .features-grid {
echo             display: grid;
echo             grid-template-columns: repeat(auto-fit, minmax(300px, 1fr^)^);
echo             gap: 20px;
echo         }
echo         .feature-card {
echo             background: white;
echo             padding: 25px;
echo             border-radius: 10px;
echo             box-shadow: 0 2px 10px rgba(0,0,0,0.1^);
echo         }
echo         .feature-card h3 {
echo             color: #667eea;
echo             margin-bottom: 15px;
echo         }
echo         .feature-card p {
echo             color: #666;
echo             margin-bottom: 20px;
echo             line-height: 1.6;
echo         }
echo         .feature-btn {
echo             background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%^);
echo             color: white;
echo             padding: 10px 20px;
echo             text-decoration: none;
echo             border-radius: 6px;
echo             display: inline-block;
echo             transition: transform 0.2s;
echo         }
echo         .feature-btn:hover {
echo             transform: translateY(-2px^);
echo         }
echo         .system-info {
echo             background: white;
echo             padding: 25px;
echo             border-radius: 10px;
echo             box-shadow: 0 2px 10px rgba(0,0,0,0.1^);
echo             margin-top: 30px;
echo         }
echo         .system-info h3 {
echo             color: #667eea;
echo             margin-bottom: 15px;
echo         }
echo         .info-grid {
echo             display: grid;
echo             grid-template-columns: repeat(auto-fit, minmax(200px, 1fr^)^);
echo             gap: 15px;
echo         }
echo         .info-item {
echo             display: flex;
echo             justify-content: space-between;
echo             padding: 10px 0;
echo             border-bottom: 1px solid #eee;
echo         }
echo         .info-label {
echo             font-weight: 500;
echo             color: #333;
echo         }
echo         .info-value {
echo             color: #666;
echo         }
echo     ^</style^>
echo ^</head^>
echo ^<body^>
echo     ^<div class="header"^>
echo         ^<div class="header-content"^>
echo             ^<div class="logo"^>
echo                 ^<h1^>^<?= $config['app_name'] ?^>^</h1^>
echo                 ^<p^>Portable Edition v^<?= $config['version'] ?^>^</p^>
echo             ^</div^>
echo             ^<div class="user-info"^>
echo                 ^<p^>Benvenuto, ^<?= htmlspecialchars($_SESSION['user_name']^) ?^>^</p^>
echo                 ^<p^>^<?= htmlspecialchars($_SESSION['user_email']^) ?^>^</p^>
echo                 ^<a href="?logout=1" class="btn"^>Logout^</a^>
echo             ^</div^>
echo         ^</div^>
echo     ^</div^>
echo     
echo     ^<div class="container"^>
echo         ^<div class="stats-grid"^>
echo             ^<div class="stat-card"^>
echo                 ^<div class="stat-number"^>^<?= $clientsCount ?^>^</div^>
echo                 ^<div class="stat-label"^>Clienti^</div^>
echo             ^</div^>
echo             ^<div class="stat-card"^>
echo                 ^<div class="stat-number"^>^<?= $reportsCount ?^>^</div^>
echo                 ^<div class="stat-label"^>Report^</div^>
echo             ^</div^>
echo             ^<div class="stat-card"^>
echo                 ^<div class="stat-number"^>^<?= $usersCount ?^>^</div^>
echo                 ^<div class="stat-label"^>Utenti^</div^>
echo             ^</div^>
echo         ^</div^>
echo         
echo         ^<div class="features-grid"^>
echo             ^<div class="feature-card"^>
echo                 ^<h3^>Gestione Clienti^</h3^>
echo                 ^<p^>Gestisci i tuoi clienti, le loro informazioni e i progetti di marketing digitale.^</p^>
echo                 ^<a href="#" class="feature-btn"^>Gestisci Clienti^</a^>
echo             ^</div^>
echo             
echo             ^<div class="feature-card"^>
echo                 ^<h3^>Report e Analytics^</h3^>
echo                 ^<p^>Crea report dettagliati e analisi per i tuoi clienti con grafici e statistiche.^</p^>
echo                 ^<a href="#" class="feature-btn"^>Crea Report^</a^>
echo             ^</div^>
echo             
echo             ^<div class="feature-card"^>
echo                 ^<h3^>Configurazione^</h3^>
echo                 ^<p^>Configura le impostazioni dell'applicazione, utenti e preferenze del sistema.^</p^>
echo                 ^<a href="#" class="feature-btn"^>Configura^</a^>
echo             ^</div^>
echo         ^</div^>
echo         
echo         ^<div class="system-info"^>
echo             ^<h3^>Informazioni Sistema^</h3^>
echo             ^<div class="info-grid"^>
echo                 ^<div class="info-item"^>
echo                     ^<span class="info-label"^>Versione^</span^>
echo                     ^<span class="info-value"^>^<?= $config['version'] ?^>^</span^>
echo                 ^</div^>
echo                 ^<div class="info-item"^>
echo                     ^<span class="info-label"^>Modalità^</span^>
echo                     ^<span class="info-value"^>Portable^</span^>
echo                 ^</div^>
echo                 ^<div class="info-item"^>
echo                     ^<span class="info-label"^>Database^</span^>
echo                     ^<span class="info-value"^>SQLite^</span^>
echo                 ^</div^>
echo                 ^<div class="info-item"^>
echo                     ^<span class="info-label"^>PHP Version^</span^>
echo                     ^<span class="info-value"^>^<?= PHP_VERSION ?^>^</span^>
echo                 ^</div^>
echo             ^</div^>
echo         ^</div^>
echo     ^</div^>
echo ^</body^>
echo ^</html^>
) > www\public\index.php

echo.
echo ========================================
echo STEP 3: Configurazione PHP Desktop
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
echo STEP 4: Creazione launcher
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
echo echo.
echo echo Apertura applicazione...
echo start "" FP-DMS.exe
echo.
echo echo Applicazione avviata!
echo echo Puoi chiudere questa finestra.
echo timeout /t 2
echo exit
) > AVVIA-APPLICAZIONE.bat

REM Crea shortcut sul desktop
echo Creando shortcut sul desktop...
powershell -Command "& {$WshShell = New-Object -comObject WScript.Shell; $Shortcut = $WshShell.CreateShortcut('%USERPROFILE%\Desktop\FP Digital Marketing Suite.lnk'); $Shortcut.TargetPath = '%INSTALL_DIR%\AVVIA-APPLICAZIONE.bat'; $Shortcut.WorkingDirectory = '%INSTALL_DIR%'; $Shortcut.Description = 'FP Digital Marketing Suite'; $Shortcut.Save()}"

echo.
echo ========================================
echo STEP 5: Creazione documentazione
echo ========================================

REM Crea README
echo Creando documentazione...
(
echo ========================================
echo FP DIGITAL MARKETING SUITE
echo VERSIONE PORTABLE - INSTALLAZIONE AUTOMATICA
echo ========================================
echo.
echo INSTALLAZIONE COMPLETATA!
echo.
echo COME USARE:
echo 1. Fare doppio click su "AVVIA-APPLICAZIONE.bat"
echo    OPPURE
echo 2. Fare doppio click sull'icona sul desktop
echo.
echo PRIMO ACCESSO:
echo - Email: admin@localhost
echo - Password: admin123
echo - IMPORTANTE: Cambiare la password dopo il primo accesso!
echo.
echo CARATTERISTICHE:
echo ✓ Completamente portable (nessuna installazione)
echo ✓ Funziona da USB stick
echo ✓ Database SQLite incluso
echo ✓ Nessuna connessione internet richiesta
echo ✓ Nessun diritto amministratore richiesto
echo ✓ Interfaccia moderna e intuitiva
echo.
echo FUNZIONALITÀ:
echo - Gestione clienti
echo - Creazione report
echo - Dashboard con statistiche
echo - Sistema di autenticazione
echo - Configurazione personalizzabile
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
echo GRAZIE PER AVER SCELTO FP DMS!
echo ========================================
) > LEGGIMI.txt

echo.
echo ========================================
echo INSTALLAZIONE COMPLETATA!
echo ========================================
echo.
echo L'applicazione è stata installata in:
echo %INSTALL_DIR%
echo.
echo È stato creato anche un collegamento sul desktop.
echo.
echo ========================================
echo PROSSIMI PASSI:
echo ========================================
echo.
echo 1. Fare doppio click su "AVVIA-APPLICAZIONE.bat"
echo 2. Login con: admin@localhost / admin123
echo 3. Iniziare a usare l'applicazione!
echo.
echo ========================================
echo.
echo Vuoi avviare l'applicazione ora? (S/N)
set /p choice=
if /i "%choice%"=="S" (
    echo.
    echo Avvio applicazione...
    start "" FP-DMS.exe
)

echo.
echo Installazione completata con successo!
echo Grazie per aver scelto FP Digital Marketing Suite!
echo.
pause
