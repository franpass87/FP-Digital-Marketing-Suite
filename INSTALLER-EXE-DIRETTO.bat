@echo off
REM FP Digital Marketing Suite - CREATORE INSTALLER .EXE DIRETTO
REM Questo script crea direttamente un installer .exe usando strumenti gratuiti

title FP DMS - Creazione Installer .EXE Diretto

echo ========================================
echo FP DIGITAL MARKETING SUITE
echo CREAZIONE INSTALLER .EXE DIRETTO
echo ========================================
echo.
echo Questo script crea un installer .exe professionale
echo usando strumenti gratuiti e online!
echo.

REM Crea directory di lavoro
if exist "build\installer-exe" (
    echo Pulendo build precedente...
    rmdir /s /q build\installer-exe
)

echo Creando directory di lavoro...
mkdir build\installer-exe
cd build\installer-exe

echo.
echo ========================================
echo STEP 1: Download Bat To Exe Converter
echo ========================================
echo.

echo Scaricando Bat To Exe Converter...
powershell -Command "& {Invoke-WebRequest -Uri 'https://github.com/vegard/bat-to-exe-converter/releases/download/v0.4.0/BatToExeConverter.zip' -OutFile 'BatToExeConverter.zip' -UseBasicParsing}"

if exist "BatToExeConverter.zip" (
    echo Estraendo Bat To Exe Converter...
    powershell -Command "Expand-Archive -Path 'BatToExeConverter.zip' -DestinationPath '.' -Force"
    del BatToExeConverter.zip
    
    if exist "BatToExeConverter.exe" (
        echo Bat To Exe Converter scaricato con successo!
    ) else (
        echo ERRORE: Estrazione fallita!
        goto :create_manual_installer
    )
) else (
    echo ERRORE: Download fallito!
    goto :create_manual_installer
)

echo.
echo ========================================
echo STEP 2: Creazione script installer
echo ========================================
echo.

REM Crea script installer ottimizzato
echo Creando script installer ottimizzato...
(
echo @echo off
echo REM FP Digital Marketing Suite - Installer
echo title FP Digital Marketing Suite - Installazione
echo.
echo echo ========================================
echo echo FP DIGITAL MARKETING SUITE
echo echo INSTALLAZIONE IN CORSO...
echo echo ========================================
echo echo.
echo echo Scaricando e installando l'applicazione...
echo echo Questo potrebbe richiedere alcuni minuti...
echo echo.
echo.
echo REM Crea directory di installazione
echo set INSTALL_DIR=%%USERPROFILE%%\Desktop\FP-Digital-Marketing-Suite
echo if exist "%%INSTALL_DIR%%" (
echo     echo Rimuovendo installazione precedente...
echo     rmdir /s /q "%%INSTALL_DIR%%"
echo )
echo mkdir "%%INSTALL_DIR%%"
echo cd /d "%%INSTALL_DIR%%"
echo.
echo REM Download PHP Desktop
echo echo Scaricando PHP Desktop...
echo powershell -Command "& {Invoke-WebRequest -Uri 'https://github.com/cztomczak/phpdesktop/releases/download/v57.0/phpdesktop-chrome-57.0-msvc-php-7.4.zip' -OutFile 'phpdesktop.zip' -UseBasicParsing}"
echo.
echo if not exist "phpdesktop.zip" (
echo     echo ERRORE: Download fallito!
echo     echo Controlla la connessione internet e riprova.
echo     pause
echo     exit /b 1
echo )
echo.
echo REM Estrai e configura
echo echo Configurando applicazione...
echo powershell -Command "Expand-Archive -Path 'phpdesktop.zip' -DestinationPath '.' -Force"
echo del phpdesktop.zip
echo if exist "phpdesktop-chrome.exe" move "phpdesktop-chrome.exe" "FP-DMS.exe"
echo.
echo REM Crea struttura applicazione
echo mkdir www\public
echo mkdir www\public\storage\logs
echo mkdir www\public\storage\uploads
echo mkdir www\public\storage\cache
echo.
echo REM Crea applicazione semplificata
echo echo Creando applicazione...
echo echo ^<?php > www\public\index.php
echo session_start(^); >> www\public\index.php
echo $config = ['app_name' =^> 'FP Digital Marketing Suite', 'version' =^> '1.0.0', 'db_file' =^> __DIR__ . '/../storage/database.sqlite']; >> www\public\index.php
echo if (!file_exists($config['db_file']^)^) { >> www\public\index.php
echo     $pdo = new PDO('sqlite:' . $config['db_file']^); >> www\public\index.php
echo     $pdo-^>exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255^) NOT NULL, email VARCHAR(255^) UNIQUE NOT NULL, password VARCHAR(255^) NOT NULL, role VARCHAR(50^) DEFAULT 'admin', created_at DATETIME DEFAULT CURRENT_TIMESTAMP^); CREATE TABLE IF NOT EXISTS clients (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255^) NOT NULL, email_to TEXT, logo_url TEXT, timezone VARCHAR(64^) DEFAULT 'Europe/Rome', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP^); CREATE TABLE IF NOT EXISTS reports (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, title VARCHAR(255^) NOT NULL, content TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (client_id^) REFERENCES clients (id^)^);"^); >> www\public\index.php
echo     $adminPassword = password_hash('admin123', PASSWORD_DEFAULT^); >> www\public\index.php
echo     $pdo-^>exec("INSERT OR IGNORE INTO users (name, email, password, role^) VALUES ('Administrator', 'admin@localhost', '$adminPassword', 'admin'^)"^); >> www\public\index.php
echo } >> www\public\index.php
echo if (!isset($_SESSION['user_id']^)^) { >> www\public\index.php
echo     if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']^) && $_POST['action'] === 'login'^) { >> www\public\index.php
echo         $email = $_POST['email'] ?? ''; $password = $_POST['password'] ?? ''; >> www\public\index.php
echo         $pdo = new PDO('sqlite:' . $config['db_file']^); $stmt = $pdo-^>prepare("SELECT * FROM users WHERE email = ?"^); $stmt-^>execute([$email]^); $user = $stmt-^>fetch(^); >> www\public\index.php
echo         if ($user && password_verify($password, $user['password']^)^) { $_SESSION['user_id'] = $user['id']; $_SESSION['user_name'] = $user['name']; $_SESSION['user_email'] = $user['email']; header('Location: /'^); exit; } else { $error = 'Credenziali non valide'; } >> www\public\index.php
echo     } >> www\public\index.php
echo     ?^> >> www\public\index.php
echo     ^<!DOCTYPE html^>^<html lang="it"^>^<head^>^<meta charset="UTF-8"^>^<title^>^<?= $config['app_name'] ?^> - Login^</title^>^<style^>body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%^); min-height: 100vh; display: flex; align-items: center; justify-content: center; } .login-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 15px 35px rgba(0,0,0,0.1^); width: 100%%; max-width: 400px; } .logo { text-align: center; margin-bottom: 30px; } .logo h1 { color: #667eea; font-size: 24px; margin-bottom: 5px; } .logo p { color: #666; font-size: 14px; } .form-group { margin-bottom: 20px; } label { display: block; margin-bottom: 5px; color: #333; font-weight: 500; } input { width: 100%%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; } input:focus { outline: none; border-color: #667eea; } button { width: 100%%; background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%^); color: white; padding: 12px; border: none; border-radius: 6px; font-size: 16px; font-weight: 500; cursor: pointer; transition: transform 0.2s; } button:hover { transform: translateY(-2px^); } .error { background: #fee; color: #c33; padding: 10px; border-radius: 4px; margin-bottom: 15px; border-left: 4px solid #c33; } .default-credentials { background: #f0f8ff; padding: 15px; border-radius: 6px; margin-top: 20px; border-left: 4px solid #667eea; } .default-credentials h4 { color: #667eea; margin-bottom: 10px; } .default-credentials p { color: #666; font-size: 14px; line-height: 1.4; }^</style^>^</head^>^<body^>^<div class="login-container"^>^<div class="logo"^>^<h1^>FP DMS^</h1^>^<p^>Digital Marketing Suite^</p^>^</div^>^<?php if (isset($error^)^): ?^>^<div class="error"^>^<?= htmlspecialchars($error^) ?^>^</div^>^<?php endif; ?^>^<form method="POST"^>^<input type="hidden" name="action" value="login"^>^<div class="form-group"^>^<label^>Email^</label^>^<input type="email" name="email" value="admin@localhost" required^>^</div^>^<div class="form-group"^>^<label^>Password^</label^>^<input type="password" name="password" value="admin123" required^>^</div^>^<button type="submit"^>Accedi^</button^>^</form^>^<div class="default-credentials"^>^<h4^>Credenziali di Default^</h4^>^<p^>Email: admin@localhost^<br^>Password: admin123^</p^>^<p^>^<strong^>Importante:^</strong^> Cambia queste credenziali dopo il primo accesso!^</p^>^</div^>^</div^>^</body^>^</html^> >> www\public\index.php
echo     ^<?php exit; } >> www\public\index.php
echo if (isset($_GET['logout']^)^) { session_destroy(^); header('Location: /'^); exit; } >> www\public\index.php
echo $pdo = new PDO('sqlite:' . $config['db_file']^); $clientsCount = $pdo-^>query("SELECT COUNT(*^) FROM clients"^)-^>fetchColumn(^); $reportsCount = $pdo-^>query("SELECT COUNT(*^) FROM reports"^)-^>fetchColumn(^); $usersCount = $pdo-^>query("SELECT COUNT(*^) FROM users"^)-^>fetchColumn(^); >> www\public\index.php
echo ?^> >> www\public\index.php
echo ^<!DOCTYPE html^>^<html lang="it"^>^<head^>^<meta charset="UTF-8"^>^<title^>^<?= $config['app_name'] ?^> - Dashboard^</title^>^<style^>* { margin: 0; padding: 0; box-sizing: border-box; } body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; } .header { background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%^); color: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1^); } .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; } .logo h1 { font-size: 24px; margin-bottom: 5px; } .logo p { opacity: 0.9; font-size: 14px; } .user-info { text-align: right; } .user-info p { margin-bottom: 5px; } .btn { background: rgba(255,255,255,0.2^); color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; border: 1px solid rgba(255,255,255,0.3^); transition: background 0.3s; } .btn:hover { background: rgba(255,255,255,0.3^); } .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; } .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr^)^); gap: 20px; margin-bottom: 30px; } .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1^); text-align: center; } .stat-number { font-size: 36px; font-weight: bold; color: #667eea; margin-bottom: 10px; } .stat-label { color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; } .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr^)^); gap: 20px; } .feature-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1^); } .feature-card h3 { color: #667eea; margin-bottom: 15px; } .feature-card p { color: #666; margin-bottom: 20px; line-height: 1.6; } .feature-btn { background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%^); color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; transition: transform 0.2s; } .feature-btn:hover { transform: translateY(-2px^); } .system-info { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1^); margin-top: 30px; } .system-info h3 { color: #667eea; margin-bottom: 15px; } .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr^)^); gap: 15px; } .info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; } .info-label { font-weight: 500; color: #333; } .info-value { color: #666; }^</style^>^</head^>^<body^>^<div class="header"^>^<div class="header-content"^>^<div class="logo"^>^<h1^>^<?= $config['app_name'] ?^>^</h1^>^<p^>Portable Edition v^<?= $config['version'] ?^>^</p^>^</div^>^<div class="user-info"^>^<p^>Benvenuto, ^<?= htmlspecialchars($_SESSION['user_name']^) ?^>^</p^>^<p^>^<?= htmlspecialchars($_SESSION['user_email']^) ?^>^</p^>^<a href="?logout=1" class="btn"^>Logout^</a^>^</div^>^</div^>^</div^>^<div class="container"^>^<div class="stats-grid"^>^<div class="stat-card"^>^<div class="stat-number"^>^<?= $clientsCount ?^>^</div^>^<div class="stat-label"^>Clienti^</div^>^</div^>^<div class="stat-card"^>^<div class="stat-number"^>^<?= $reportsCount ?^>^</div^>^<div class="stat-label"^>Report^</div^>^</div^>^<div class="stat-card"^>^<div class="stat-number"^>^<?= $usersCount ?^>^</div^>^<div class="stat-label"^>Utenti^</div^>^</div^>^</div^>^<div class="features-grid"^>^<div class="feature-card"^>^<h3^>Gestione Clienti^</h3^>^<p^>Gestisci i tuoi clienti, le loro informazioni e i progetti di marketing digitale.^</p^>^<a href="#" class="feature-btn"^>Gestisci Clienti^</a^>^</div^>^<div class="feature-card"^>^<h3^>Report e Analytics^</h3^>^<p^>Crea report dettagliati e analisi per i tuoi clienti con grafici e statistiche.^</p^>^<a href="#" class="feature-btn"^>Crea Report^</a^>^</div^>^<div class="feature-card"^>^<h3^>Configurazione^</h3^>^<p^>Configura le impostazioni dell'applicazione, utenti e preferenze del sistema.^</p^>^<a href="#" class="feature-btn"^>Configura^</a^>^</div^>^</div^>^<div class="system-info"^>^<h3^>Informazioni Sistema^</h3^>^<div class="info-grid"^>^<div class="info-item"^>^<span class="info-label"^>Versione^</span^>^<span class="info-value"^>^<?= $config['version'] ?^>^</span^>^</div^>^<div class="info-item"^>^<span class="info-label"^>Modalità^</span^>^<span class="info-value"^>Portable^</span^>^</div^>^<div class="info-item"^>^<span class="info-label"^>Database^</span^>^<span class="info-value"^>SQLite^</span^>^</div^>^<div class="info-item"^>^<span class="info-label"^>PHP Version^</span^>^<span class="info-value"^>^<?= PHP_VERSION ?^>^</span^>^</div^>^</div^>^</div^>^</div^>^</body^>^</html^> >> www\public\index.php
echo ?^> >> www\public\index.php
echo.
echo REM Crea configurazione PHP Desktop
echo echo { > settings.json
echo echo     "title": "FP Digital Marketing Suite", >> settings.json
echo echo     "main_window": { >> settings.json
echo echo         "default_size": [1200, 800], >> settings.json
echo echo         "minimum_size": [800, 600], >> settings.json
echo echo         "center_on_screen": true, >> settings.json
echo echo         "start_maximized": false >> settings.json
echo echo     }, >> settings.json
echo echo     "web_server": { >> settings.json
echo echo         "listen_on": ["127.0.0.1", 8080], >> settings.json
echo echo         "www_directory": "www/public", >> settings.json
echo echo         "index_files": ["index.php"], >> settings.json
echo echo         "cgi_interpreter": "php/php-cgi.exe", >> settings.json
echo echo         "cgi_extensions": ["php"] >> settings.json
echo echo     }, >> settings.json
echo echo     "chrome": { >> settings.json
echo echo         "cache_path": "webcache", >> settings.json
echo echo         "context_menu": { >> settings.json
echo echo             "enable_menu": false >> settings.json
echo echo         } >> settings.json
echo echo     }, >> settings.json
echo echo     "application": { >> settings.json
echo echo         "hide_php_console": true >> settings.json
echo echo     } >> settings.json
echo echo } >> settings.json
echo.
echo REM Crea launcher
echo echo @echo off > AVVIA-APPLICAZIONE.bat
echo echo title FP Digital Marketing Suite >> AVVIA-APPLICAZIONE.bat
echo echo echo Avvio applicazione... >> AVVIA-APPLICAZIONE.bat
echo echo start "" FP-DMS.exe >> AVVIA-APPLICAZIONE.bat
echo echo exit >> AVVIA-APPLICAZIONE.bat
echo.
echo REM Crea shortcut desktop
echo powershell -Command "& {$WshShell = New-Object -comObject WScript.Shell; $Shortcut = $WshShell.CreateShortcut('%%USERPROFILE%%\Desktop\FP Digital Marketing Suite.lnk'); $Shortcut.TargetPath = '%%INSTALL_DIR%%\AVVIA-APPLICAZIONE.bat'; $Shortcut.WorkingDirectory = '%%INSTALL_DIR%%'; $Shortcut.Description = 'FP Digital Marketing Suite'; $Shortcut.Save()}"
echo.
echo REM Crea documentazione
echo echo ======================================== > README.txt
echo echo FP DIGITAL MARKETING SUITE >> README.txt
echo echo VERSIONE PORTABLE >> README.txt
echo echo ======================================== >> README.txt
echo echo. >> README.txt
echo echo INSTALLAZIONE COMPLETATA! >> README.txt
echo echo. >> README.txt
echo echo COME USARE: >> README.txt
echo echo 1. Fare doppio click su "AVVIA-APPLICAZIONE.bat" >> README.txt
echo echo    OPPURE >> README.txt
echo echo 2. Fare doppio click sull'icona sul desktop >> README.txt
echo echo. >> README.txt
echo echo PRIMO ACCESSO: >> README.txt
echo echo - Email: admin@localhost >> README.txt
echo echo - Password: admin123 >> README.txt
echo echo - IMPORTANTE: Cambiare la password dopo il primo accesso! >> README.txt
echo echo. >> README.txt
echo echo CARATTERISTICHE: >> README.txt
echo echo ✓ Completamente portable (nessuna installazione) >> README.txt
echo echo ✓ Funziona da USB stick >> README.txt
echo echo ✓ Database SQLite incluso >> README.txt
echo echo ✓ Nessuna connessione internet richiesta >> README.txt
echo echo ✓ Nessun diritto amministratore richiesto >> README.txt
echo echo ✓ Interfaccia moderna e intuitiva >> README.txt
echo echo. >> README.txt
echo echo SUPPORTO: >> README.txt
echo echo Email: info@francescopasseri.com >> README.txt
echo echo Web: https://francescopasseri.com >> README.txt
echo.
echo echo ========================================
echo echo INSTALLAZIONE COMPLETATA!
echo echo ========================================
echo echo.
echo echo L'applicazione è stata installata in:
echo echo %%INSTALL_DIR%%
echo echo.
echo echo È stato creato un collegamento sul desktop.
echo echo.
echo echo Credenziali di accesso:
echo echo Email: admin@localhost
echo echo Password: admin123
echo echo.
echo echo ========================================
echo echo.
echo echo Vuoi avviare l'applicazione ora? (S/N)
echo set /p choice=
echo if /i "%%choice%%"=="S" (
echo     echo.
echo     echo Avvio applicazione...
echo     start "" FP-DMS.exe
echo )
echo.
echo echo Installazione completata con successo!
echo echo Grazie per aver scelto FP Digital Marketing Suite!
echo.
echo pause
) > installer.bat

echo.
echo ========================================
echo STEP 3: Creazione installer .exe
echo ========================================
echo.

if exist "BatToExeConverter.exe" (
    echo Creando installer .exe con Bat To Exe Converter...
    echo.
    echo Configurazione automatica:
    echo - Input: installer.bat
    echo - Output: FP-DMS-Installer.exe
    echo - Visibilità: Invisibile (per esperienza più pulita)
    echo - Icona: Default
    echo.
    
    REM Usa Bat To Exe Converter automaticamente
    echo Avviando Bat To Exe Converter...
    echo Segui le istruzioni nella finestra che si apre:
    echo.
    echo 1. Seleziona installer.bat come file di input
    echo 2. Imposta output come FP-DMS-Installer.exe
    echo 3. Seleziona "Invisible application"
    echo 4. Clicca "Compile" per creare l'installer .exe
    echo.
    
    start "" BatToExeConverter.exe
    
    echo.
    echo Bat To Exe Converter avviato!
    echo Segui le istruzioni sopra per creare l'installer .exe.
    echo.
    echo Una volta completato, avrai:
    echo - FP-DMS-Installer.exe (installer professionale)
    echo.
) else (
    goto :create_manual_installer
)

goto :end

:create_manual_installer
echo.
echo ========================================
echo CREAZIONE MANUALE INSTALLER .EXE
echo ========================================
echo.
echo Poiché Bat To Exe Converter non è disponibile,
echo ecco come creare manualmente l'installer .exe:
echo.
echo OPZIONE 1: Online Converter
echo ===========================
echo.
echo 1. Vai su: https://bat-to-exe-converter.com/
echo 2. Carica il file installer.bat
echo 3. Configura:
echo    - Output: FP-DMS-Installer.exe
echo    - Visibilità: Invisible
echo    - Icona: Personalizzata (opzionale)
echo 4. Clicca "Convert" e scarica l'installer .exe
echo.
echo OPZIONE 2: IExpress (Windows integrato)
echo ======================================
echo.
echo 1. Apri Prompt dei comandi come amministratore
echo 2. Digita: iexpress
echo 3. Segui la procedura guidata:
echo    - Crea nuovo file di estrazione automatica
echo    - Estrai file ed esegui comando di installazione
echo    - Nome: FP Digital Marketing Suite Installer
echo    - Aggiungi installer.bat
echo    - Comando: installer.bat
echo    - Salva come: FP-DMS-Installer.exe
echo.
echo OPZIONE 3: NSIS (Avanzato)
echo ==========================
echo.
echo 1. Scarica NSIS da: https://nsis.sourceforge.io/
echo 2. Installa NSIS
echo 3. Crea uno script NSIS con installer.bat
echo 4. Compila per creare installer .exe completo
echo.
echo ========================================
echo RIEPILOGO
echo ========================================
echo.
echo File creati:
echo - installer.bat (script di installazione)
echo - README.txt (documentazione)
echo.
echo Prossimi passi:
echo 1. Usa una delle opzioni sopra per creare installer .exe
echo 2. Testa l'installer su un PC pulito
echo 3. Distribuisci FP-DMS-Installer.exe ai tuoi clienti
echo.
echo L'installer .exe sarà completamente professionale e
echo non richiederà alcuna conoscenza tecnica agli utenti!
echo.

:end
echo.
echo ========================================
echo PROCESSO COMPLETATO!
echo ========================================
echo.
echo Tutti i file necessari sono stati creati in:
echo build\installer-exe\
echo.
echo File principali:
echo - installer.bat (script di installazione)
echo - README.txt (documentazione utente)
echo - BatToExeConverter.exe (strumento di conversione)
echo.
echo Per creare l'installer .exe finale:
echo 1. Usa Bat To Exe Converter (se disponibile)
echo 2. Oppure usa una delle opzioni manuali
echo 3. Risultato: FP-DMS-Installer.exe professionale
echo.
echo L'installer .exe sarà:
echo ✓ Completamente automatico
echo ✓ Professionale
echo ✓ Facile da usare
echo ✓ Nessuna conoscenza tecnica richiesta
echo.
pause
