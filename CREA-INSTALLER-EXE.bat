@echo off
REM FP Digital Marketing Suite - CREATORE INSTALLER .EXE
REM Questo script crea un installer .exe professionale

title FP DMS - Creatore Installer .EXE

echo ========================================
echo FP DIGITAL MARKETING SUITE
echo CREATORE INSTALLER .EXE
echo ========================================
echo.
echo Questo script crea un installer .exe professionale
echo che gli utenti possono eseguire direttamente!
echo.

REM Crea directory di lavoro
if exist "build\installer" (
    echo Pulendo build precedente...
    rmdir /s /q build\installer
)

echo Creando directory di lavoro...
mkdir build\installer
cd build\installer

echo.
echo ========================================
echo OPZIONE 1: Installer con IExpress (Windows integrato)
echo ========================================
echo.

echo Creando installer con IExpress di Windows...
echo Questo crea un installer .exe nativo di Windows.

REM Crea script batch per l'installer
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
echo if exist "%%INSTALL_DIR%%" rmdir /s /q "%%INSTALL_DIR%%"
echo mkdir "%%INSTALL_DIR%%"
echo cd /d "%%INSTALL_DIR%%"
echo.
echo REM Download PHP Desktop
echo echo Scaricando PHP Desktop...
echo powershell -Command "& {Invoke-WebRequest -Uri 'https://github.com/cztomczak/phpdesktop/releases/download/v57.0/phpdesktop-chrome-57.0-msvc-php-7.4.zip' -OutFile 'phpdesktop.zip' -UseBasicParsing}"
echo.
echo REM Estrai e configura
echo echo Configurando applicazione...
echo powershell -Command "Expand-Archive -Path 'phpdesktop.zip' -DestinationPath '.' -Force"
echo del phpdesktop.zip
echo if exist "phpdesktop-chrome.exe" move "phpdesktop-chrome.exe" "FP-DMS.exe"
echo.
echo REM Crea struttura applicazione
echo mkdir www\public\storage\logs
echo mkdir www\public\storage\uploads
echo mkdir www\public\storage\cache
echo.
echo REM Crea applicazione semplificata
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

REM Crea installer con IExpress
echo Creando installer .exe con IExpress di Windows...
echo.
echo IExpress creerà un installer .exe professionale.
echo Segui le istruzioni nella finestra che si apre:
echo.
echo 1. Seleziona "Create new Self Extraction Directive file"
echo 2. Seleziona "Extract files and run an installation command"
echo 3. Nome: "FP Digital Marketing Suite Installer"
echo 4. Aggiungi installer.bat come file da includere
echo 5. Comando: installer.bat
echo 6. Salva come: FP-DMS-Installer.exe
echo.

REM Avvia IExpress
iexpress

echo.
echo ========================================
echo OPZIONE 2: Installer con Bat To Exe Converter
echo ========================================
echo.
echo Per una soluzione ancora più professionale, puoi usare:
echo.
echo 1. Scarica "Bat To Exe Converter" da:
echo    https://battoexeconverter.com/
echo.
echo 2. Usa il file "installer.bat" che ho appena creato
echo.
echo 3. Configura:
echo    - Input: installer.bat
echo    - Output: FP-DMS-Installer.exe
echo    - Icona: Personalizzata (opzionale)
echo    - Visibilità: Invisibile (per esperienza più pulita)
echo.
echo 4. Clicca "Compile" per creare l'installer .exe
echo.

echo.
echo ========================================
echo OPZIONE 3: Installer con NSIS (Avanzato)
echo ========================================
echo.
echo Per un installer professionale completo:
echo.
echo 1. Scarica NSIS da: https://nsis.sourceforge.io/
echo.
echo 2. Usa questo script NSIS (lo creo automaticamente):
echo.

REM Crea script NSIS
(
echo !define PRODUCT_NAME "FP Digital Marketing Suite"
echo !define PRODUCT_VERSION "1.0.0"
echo !define PRODUCT_PUBLISHER "Francesco Passeri"
echo !define PRODUCT_WEB_SITE "https://francescopasseri.com"
echo !define PRODUCT_DIR_REGKEY "Software\Microsoft\Windows\CurrentVersion\App Paths\FP-DMS.exe"
echo !define PRODUCT_UNINST_KEY "Software\Microsoft\Windows\CurrentVersion\Uninstall\${PRODUCT_NAME}"
echo !define PRODUCT_UNINST_ROOT_KEY "HKLM"
echo.
echo SetCompressor lzma
echo.
echo !include "MUI2.nsh"
echo.
echo !define MUI_ABORTWARNING
echo !define MUI_ICON "${NSISDIR}\Contrib\Graphics\Icons\modern-install.ico"
echo !define MUI_UNICON "${NSISDIR}\Contrib\Graphics\Icons\modern-uninstall.ico"
echo.
echo !insertmacro MUI_PAGE_WELCOME
echo !insertmacro MUI_PAGE_LICENSE "LICENSE.txt"
echo !insertmacro MUI_PAGE_DIRECTORY
echo !insertmacro MUI_PAGE_INSTFILES
echo !insertmacro MUI_PAGE_FINISH
echo.
echo !insertmacro MUI_UNPAGE_INSTFILES
echo.
echo !insertmacro MUI_LANGUAGE "Italian"
echo.
echo Name "${PRODUCT_NAME} ${PRODUCT_VERSION}"
echo OutFile "FP-DMS-Installer.exe"
echo InstallDir "$DESKTOP\FP-Digital-Marketing-Suite"
echo InstallDirRegKey HKLM "${PRODUCT_DIR_REGKEY}" ""
echo ShowInstDetails show
echo ShowUnInstDetails show
echo.
echo Section "MainSection" SEC01
echo   SetOutPath "$INSTDIR"
echo   SetOverwrite ifnewer
echo   File "installer.bat"
echo   File "LICENSE.txt"
echo   File "README.txt"
echo   
echo   ExecWait "$INSTDIR\installer.bat"
echo   
echo   CreateDirectory "$SMPROGRAMS\FP Digital Marketing Suite"
echo   CreateShortCut "$SMPROGRAMS\FP Digital Marketing Suite\FP Digital Marketing Suite.lnk" "$INSTDIR\AVVIA-APPLICAZIONE.bat"
echo   CreateShortCut "$DESKTOP\FP Digital Marketing Suite.lnk" "$INSTDIR\AVVIA-APPLICAZIONE.bat"
echo SectionEnd
echo.
echo Section -AdditionalIcons
echo   WriteIniStr "$INSTDIR\${PRODUCT_NAME}.url" "InternetShortcut" "URL" "${PRODUCT_WEB_SITE}"
echo   CreateShortCut "$SMPROGRAMS\FP Digital Marketing Suite\Website.lnk" "$INSTDIR\${PRODUCT_NAME}.url"
echo   CreateShortCut "$SMPROGRAMS\FP Digital Marketing Suite\Uninstall.lnk" "$INSTDIR\uninst.exe"
echo SectionEnd
echo.
echo Section Uninstall
echo   Delete "$INSTDIR\${PRODUCT_NAME}.url"
echo   Delete "$INSTDIR\uninst.exe"
echo   Delete "$INSTDIR\installer.bat"
echo   Delete "$INSTDIR\LICENSE.txt"
echo   Delete "$INSTDIR\README.txt"
echo   RMDir /r "$INSTDIR"
echo   Delete "$SMPROGRAMS\FP Digital Marketing Suite\Uninstall.lnk"
echo   Delete "$SMPROGRAMS\FP Digital Marketing Suite\Website.lnk"
echo   Delete "$SMPROGRAMS\FP Digital Marketing Suite\FP Digital Marketing Suite.lnk"
echo   Delete "$DESKTOP\FP Digital Marketing Suite.lnk"
echo   RMDir "$SMPROGRAMS\FP Digital Marketing Suite"
echo   DeleteRegKey ${PRODUCT_UNINST_ROOT_KEY} "${PRODUCT_UNINST_KEY}"
echo   DeleteRegKey HKLM "${PRODUCT_DIR_REGKEY}"
echo   SetAutoClose true
echo SectionEnd
echo.
echo !insertmacro MUI_FUNCTION_DESCRIPTION_BEGIN
echo   !insertmacro MUI_DESCRIPTION_TEXT ${SEC01} "Installa FP Digital Marketing Suite"
echo !insertmacro MUI_FUNCTION_DESCRIPTION_END
echo.
echo Function un.onInit
echo   MessageBox MB_ICONQUESTION|MB_YESNO|MB_DEFBUTTON2 "Sei sicuro di voler disinstallare ${PRODUCT_NAME}?" IDYES +2
echo   Abort
echo FunctionEnd
echo.
echo Function un.onUninstSuccess
echo   HideWindow
echo   MessageBox MB_ICONINFORMATION|MB_OK "${PRODUCT_NAME} è stato disinstallato con successo dal tuo computer."
echo FunctionEnd
) > installer.nsi

echo Script NSIS creato: installer.nsi
echo.
echo Per usarlo:
echo 1. Installa NSIS
echo 2. Apri installer.nsi con NSIS
echo 3. Compila per creare FP-DMS-Installer.exe
echo.

echo.
echo ========================================
echo RIEPILOGO OPZIONI
echo ========================================
echo.
echo OPZIONE 1 (Più semplice):
echo - Usa IExpress (già avviato)
echo - Crea installer .exe nativo Windows
echo - Risultato: FP-DMS-Installer.exe
echo.
echo OPZIONE 2 (Consigliata):
echo - Scarica Bat To Exe Converter
echo - Usa installer.bat
echo - Risultato: FP-DMS-Installer.exe professionale
echo.
echo OPZIONE 3 (Più avanzata):
echo - Installa NSIS
echo - Usa installer.nsi
echo - Risultato: Installer completo con disinstallazione
echo.
echo ========================================
echo.
echo Tutti i file necessari sono stati creati in:
echo build\installer\
echo.
echo Il file installer.bat contiene tutto il codice di installazione.
echo Puoi usarlo con qualsiasi strumento per creare un .exe!
echo.
pause
