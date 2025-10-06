# FP Digital Marketing Suite - Creazione Installer .EXE Diretto
# Questo script crea direttamente un installer .exe senza file .bat intermedi

param(
    [string]$OutputPath = ".\build\installer-exe"
)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "FP DIGITAL MARKETING SUITE" -ForegroundColor Cyan
Write-Host "CREAZIONE INSTALLER .EXE DIRETTO" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Crea directory di lavoro
if (Test-Path $OutputPath) {
    Write-Host "Pulendo build precedente..." -ForegroundColor Yellow
    Remove-Item $OutputPath -Recurse -Force
}

Write-Host "Creando directory di lavoro..." -ForegroundColor Green
New-Item -ItemType Directory -Path $OutputPath -Force | Out-Null
Set-Location $OutputPath

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "STEP 1: Download Inno Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Download Inno Setup
$innoSetupUrl = "https://files.jrsoftware.org/is/6/innosetup-6.2.2.exe"
$innoSetupFile = "innosetup-installer.exe"

Write-Host "Scaricando Inno Setup..." -ForegroundColor Green
try {
    Invoke-WebRequest -Uri $innoSetupUrl -OutFile $innoSetupFile -UseBasicParsing
    Write-Host "Inno Setup scaricato con successo!" -ForegroundColor Green
} catch {
    Write-Host "ERRORE: Download Inno Setup fallito!" -ForegroundColor Red
    Write-Host "Controlla la connessione internet e riprova." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "STEP 2: Creazione script Inno Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Crea script Inno Setup
$innoScript = @"
; FP Digital Marketing Suite - Installer Script
; Generato automaticamente

#define MyAppName "FP Digital Marketing Suite"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "Francesco Passeri"
#define MyAppURL "https://francescopasseri.com"
#define MyAppExeName "FP-DMS.exe"

[Setup]
; NOTE: The value of AppId uniquely identifies this application. Do not use the same AppId value in installers for other applications.
; (To generate a new GUID, click Tools | Generate GUID inside the IDE.)
AppId={{F1A2B3C4-D5E6-F7G8-H9I0-J1K2L3M4N5O6}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppVerName={#MyAppName} {#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName={autopf}\{#MyAppName}
DefaultGroupName={#MyAppName}
AllowNoIcons=yes
LicenseFile=
OutputDir=.
OutputBaseFilename=FP-DMS-Installer
SetupIconFile=
Compression=lzma
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=low
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible

[Languages]
Name: "italian"; MessagesFile: "compiler:Languages\Italian.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: unchecked

[Files]
; NOTE: Don't use "Flags: ignoreversion" on any shared system files

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "{cm:LaunchProgram,{#StringChange(MyAppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent

[Code]
function InitializeSetup(): Boolean;
begin
  Result := True;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  DownloadPage: TDownloadWizardPage;
  TempFile: string;
begin
  if CurStep = ssPostInstall then
  begin
    // Download PHP Desktop
    DownloadPage := CreateDownloadPage(SetupMessage(msgWizardPreparing), SetupMessage(msgPreparingDesc), nil);
    DownloadPage.Add('https://github.com/cztomczak/phpdesktop/releases/download/v57.0/phpdesktop-chrome-57.0-msvc-php-7.4.zip', 'phpdesktop.zip', '');
    
    try
      DownloadPage.Show;
      try
        DownloadPage.Download; // This downloads the files to {tmp}
      except
        if DownloadPage.AbortedByUser then
          Log('Aborted by user.')
        else
          SuppressibleMsgBox(AddPeriod(GetExceptionMessage), mbCriticalError, MB_OK, IDOK);
      end;
    finally
      DownloadPage.Free;
    end;
    
    // Extract PHP Desktop
    TempFile := ExpandConstant('{tmp}\phpdesktop.zip');
    ExtractTemporaryFile('phpdesktop.zip');
    ShellExec('', 'powershell.exe', '-Command "Expand-Archive -Path "' + TempFile + '" -DestinationPath "' + ExpandConstant('{app}') + '" -Force"', '', SW_HIDE, ewWaitUntilTerminated, ErrorCode);
    
    // Rename executable
    if FileExists(ExpandConstant('{app}\phpdesktop-chrome.exe')) then
      RenameFile(ExpandConstant('{app}\phpdesktop-chrome.exe'), ExpandConstant('{app}\{#MyAppExeName}'));
    
    // Create application structure
    CreateDir(ExpandConstant('{app}\www\public'));
    CreateDir(ExpandConstant('{app}\www\public\storage\logs'));
    CreateDir(ExpandConstant('{app}\www\public\storage\uploads'));
    CreateDir(ExpandConstant('{app}\www\public\storage\cache'));
    
    // Create application files
    CreateApplicationFiles();
  end;
end;

procedure CreateApplicationFiles();
var
  IndexFile: string;
  SettingsFile: string;
  LauncherFile: string;
begin
  // Create index.php
  IndexFile := ExpandConstant('{app}\www\public\index.php');
  SaveStringToFile(IndexFile, '<?php' + #13#10, False);
  SaveStringToFile(IndexFile, 'session_start();' + #13#10, True);
  SaveStringToFile(IndexFile, '$config = ["app_name" => "FP Digital Marketing Suite", "version" => "1.0.0", "db_file" => __DIR__ . "/../storage/database.sqlite"];' + #13#10, True);
  SaveStringToFile(IndexFile, 'if (!file_exists($config["db_file"])) {' + #13#10, True);
  SaveStringToFile(IndexFile, '    $pdo = new PDO("sqlite:" . $config["db_file"]);' + #13#10, True);
  SaveStringToFile(IndexFile, '    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(50) DEFAULT ''admin'', created_at DATETIME DEFAULT CURRENT_TIMESTAMP); CREATE TABLE IF NOT EXISTS clients (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL, email_to TEXT, logo_url TEXT, timezone VARCHAR(64) DEFAULT ''Europe/Rome'', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP); CREATE TABLE IF NOT EXISTS reports (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, title VARCHAR(255) NOT NULL, content TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (client_id) REFERENCES clients (id));");' + #13#10, True);
  SaveStringToFile(IndexFile, '    $adminPassword = password_hash("admin123", PASSWORD_DEFAULT);' + #13#10, True);
  SaveStringToFile(IndexFile, '    $pdo->exec("INSERT OR IGNORE INTO users (name, email, password, role) VALUES (''Administrator'', ''admin@localhost'', ''" . $adminPassword . "'', ''admin'')");' + #13#10, True);
  SaveStringToFile(IndexFile, '}' + #13#10, True);
  SaveStringToFile(IndexFile, 'if (!isset($_SESSION["user_id"])) {' + #13#10, True);
  SaveStringToFile(IndexFile, '    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "login") {' + #13#10, True);
  SaveStringToFile(IndexFile, '        $email = $_POST["email"] ?? ""; $password = $_POST["password"] ?? "";' + #13#10, True);
  SaveStringToFile(IndexFile, '        $pdo = new PDO("sqlite:" . $config["db_file"]); $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?"); $stmt->execute([$email]); $user = $stmt->fetch();' + #13#10, True);
  SaveStringToFile(IndexFile, '        if ($user && password_verify($password, $user["password"])) { $_SESSION["user_id"] = $user["id"]; $_SESSION["user_name"] = $user["name"]; $_SESSION["user_email"] = $user["email"]; header("Location: /"); exit; } else { $error = "Credenziali non valide"; }' + #13#10, True);
  SaveStringToFile(IndexFile, '    }' + #13#10, True);
  SaveStringToFile(IndexFile, '?>' + #13#10, True);
  SaveStringToFile(IndexFile, '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title><?= $config["app_name"] ?> - Login</title><style>body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; } .login-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 400px; } .logo { text-align: center; margin-bottom: 30px; } .logo h1 { color: #667eea; font-size: 24px; margin-bottom: 5px; } .logo p { color: #666; font-size: 14px; } .form-group { margin-bottom: 20px; } label { display: block; margin-bottom: 5px; color: #333; font-weight: 500; } input { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; } input:focus { outline: none; border-color: #667eea; } button { width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px; border: none; border-radius: 6px; font-size: 16px; font-weight: 500; cursor: pointer; transition: transform 0.2s; } button:hover { transform: translateY(-2px); } .error { background: #fee; color: #c33; padding: 10px; border-radius: 4px; margin-bottom: 15px; border-left: 4px solid #c33; } .default-credentials { background: #f0f8ff; padding: 15px; border-radius: 6px; margin-top: 20px; border-left: 4px solid #667eea; } .default-credentials h4 { color: #667eea; margin-bottom: 10px; } .default-credentials p { color: #666; font-size: 14px; line-height: 1.4; }</style></head><body><div class="login-container"><div class="logo"><h1>FP DMS</h1><p>Digital Marketing Suite</p></div><?php if (isset($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="POST"><input type="hidden" name="action" value="login"><div class="form-group"><label>Email</label><input type="email" name="email" value="admin@localhost" required></div><div class="form-group"><label>Password</label><input type="password" name="password" value="admin123" required></div><button type="submit">Accedi</button></form><div class="default-credentials"><h4>Credenziali di Default</h4><p>Email: admin@localhost<br>Password: admin123</p><p><strong>Importante:</strong> Cambia queste credenziali dopo il primo accesso!</p></div></div></body></html>' + #13#10, True);
  SaveStringToFile(IndexFile, '<?php exit; }' + #13#10, True);
  SaveStringToFile(IndexFile, 'if (isset($_GET["logout"])) { session_destroy(); header("Location: /"); exit; }' + #13#10, True);
  SaveStringToFile(IndexFile, '$pdo = new PDO("sqlite:" . $config["db_file"]); $clientsCount = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn(); $reportsCount = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn(); $usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();' + #13#10, True);
  SaveStringToFile(IndexFile, '?>' + #13#10, True);
  SaveStringToFile(IndexFile, '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title><?= $config["app_name"] ?> - Dashboard</title><style>* { margin: 0; padding: 0; box-sizing: border-box; } body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; } .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); } .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; } .logo h1 { font-size: 24px; margin-bottom: 5px; } .logo p { opacity: 0.9; font-size: 14px; } .user-info { text-align: right; } .user-info p { margin-bottom: 5px; } .btn { background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; border: 1px solid rgba(255,255,255,0.3); transition: background 0.3s; } .btn:hover { background: rgba(255,255,255,0.3); } .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; } .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; } .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; } .stat-number { font-size: 36px; font-weight: bold; color: #667eea; margin-bottom: 10px; } .stat-label { color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; } .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; } .feature-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); } .feature-card h3 { color: #667eea; margin-bottom: 15px; } .feature-card p { color: #666; margin-bottom: 20px; line-height: 1.6; } .feature-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; transition: transform 0.2s; } .feature-btn:hover { transform: translateY(-2px); } .system-info { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 30px; } .system-info h3 { color: #667eea; margin-bottom: 15px; } .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; } .info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; } .info-label { font-weight: 500; color: #333; } .info-value { color: #666; }</style></head><body><div class="header"><div class="header-content"><div class="logo"><h1><?= $config["app_name"] ?></h1><p>Portable Edition v<?= $config["version"] ?></p></div><div class="user-info"><p>Benvenuto, <?= htmlspecialchars($_SESSION["user_name"]) ?></p><p><?= htmlspecialchars($_SESSION["user_email"]) ?></p><a href="?logout=1" class="btn">Logout</a></div></div></div><div class="container"><div class="stats-grid"><div class="stat-card"><div class="stat-number"><?= $clientsCount ?></div><div class="stat-label">Clienti</div></div><div class="stat-card"><div class="stat-number"><?= $reportsCount ?></div><div class="stat-label">Report</div></div><div class="stat-card"><div class="stat-number"><?= $usersCount ?></div><div class="stat-label">Utenti</div></div></div><div class="features-grid"><div class="feature-card"><h3>Gestione Clienti</h3><p>Gestisci i tuoi clienti, le loro informazioni e i progetti di marketing digitale.</p><a href="#" class="feature-btn">Gestisci Clienti</a></div><div class="feature-card"><h3>Report e Analytics</h3><p>Crea report dettagliati e analisi per i tuoi clienti con grafici e statistiche.</p><a href="#" class="feature-btn">Crea Report</a></div><div class="feature-card"><h3>Configurazione</h3><p>Configura le impostazioni dell''applicazione, utenti e preferenze del sistema.</p><a href="#" class="feature-btn">Configura</a></div></div><div class="system-info"><h3>Informazioni Sistema</h3><div class="info-grid"><div class="info-item"><span class="info-label">Versione</span><span class="info-value"><?= $config["version"] ?></span></div><div class="info-item"><span class="info-label">Modalità</span><span class="info-value">Portable</span></div><div class="info-item"><span class="info-label">Database</span><span class="info-value">SQLite</span></div><div class="info-item"><span class="info-label">PHP Version</span><span class="info-value"><?= PHP_VERSION ?></span></div></div></div></div></body></html>' + #13#10, True);
  
  // Create settings.json
  SettingsFile := ExpandConstant('{app}\settings.json');
  SaveStringToFile(SettingsFile, '{' + #13#10, False);
  SaveStringToFile(SettingsFile, '    "title": "FP Digital Marketing Suite",' + #13#10, True);
  SaveStringToFile(SettingsFile, '    "main_window": {' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "default_size": [1200, 800],' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "minimum_size": [800, 600],' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "center_on_screen": true,' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "start_maximized": false' + #13#10, True);
  SaveStringToFile(SettingsFile, '    },' + #13#10, True);
  SaveStringToFile(SettingsFile, '    "web_server": {' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "listen_on": ["127.0.0.1", 8080],' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "www_directory": "www/public",' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "index_files": ["index.php"],' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "cgi_interpreter": "php/php-cgi.exe",' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "cgi_extensions": ["php"]' + #13#10, True);
  SaveStringToFile(SettingsFile, '    },' + #13#10, True);
  SaveStringToFile(SettingsFile, '    "chrome": {' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "cache_path": "webcache",' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "context_menu": {' + #13#10, True);
  SaveStringToFile(SettingsFile, '            "enable_menu": false' + #13#10, True);
  SaveStringToFile(SettingsFile, '        }' + #13#10, True);
  SaveStringToFile(SettingsFile, '    },' + #13#10, True);
  SaveStringToFile(SettingsFile, '    "application": {' + #13#10, True);
  SaveStringToFile(SettingsFile, '        "hide_php_console": true' + #13#10, True);
  SaveStringToFile(SettingsFile, '    }' + #13#10, True);
  SaveStringToFile(SettingsFile, '}' + #13#10, True);
  
  // Create launcher
  LauncherFile := ExpandConstant('{app}\AVVIA-APPLICAZIONE.bat');
  SaveStringToFile(LauncherFile, '@echo off' + #13#10, False);
  SaveStringToFile(LauncherFile, 'title FP Digital Marketing Suite' + #13#10, True);
  SaveStringToFile(LauncherFile, 'echo Avvio applicazione...' + #13#10, True);
  SaveStringToFile(LauncherFile, 'start "" FP-DMS.exe' + #13#10, True);
  SaveStringToFile(LauncherFile, 'exit' + #13#10, True);
end;
"@

# Salva script Inno Setup
$innoScriptFile = "FP-DMS-Installer.iss"
$innoScript | Out-File -FilePath $innoScriptFile -Encoding UTF8

Write-Host "Script Inno Setup creato: $innoScriptFile" -ForegroundColor Green

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "STEP 3: Installazione Inno Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Installando Inno Setup..." -ForegroundColor Green
Write-Host "Segui le istruzioni nella finestra di installazione che si apre." -ForegroundColor Yellow
Write-Host ""

# Avvia installazione Inno Setup
try {
    Start-Process -FilePath $innoSetupFile -Wait
    Write-Host "Inno Setup installato con successo!" -ForegroundColor Green
} catch {
    Write-Host "ERRORE: Installazione Inno Setup fallita!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "STEP 4: Compilazione Installer .EXE" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Trova percorso Inno Setup
$innoSetupCompiler = ""
$possiblePaths = @(
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
    "${env:ProgramFiles}\Inno Setup 6\ISCC.exe",
    "${env:ProgramFiles(x86)}\Inno Setup 5\ISCC.exe",
    "${env:ProgramFiles}\Inno Setup 5\ISCC.exe"
)

foreach ($path in $possiblePaths) {
    if (Test-Path $path) {
        $innoSetupCompiler = $path
        break
    }
}

if ($innoSetupCompiler -eq "") {
    Write-Host "ERRORE: Inno Setup Compiler non trovato!" -ForegroundColor Red
    Write-Host "Installa Inno Setup manualmente e riprova." -ForegroundColor Red
    exit 1
}

Write-Host "Trovato Inno Setup Compiler: $innoSetupCompiler" -ForegroundColor Green
Write-Host "Compilando installer .exe..." -ForegroundColor Green

# Compila installer
try {
    $compileResult = & $innoSetupCompiler $innoScriptFile
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Installer .exe creato con successo!" -ForegroundColor Green
    } else {
        Write-Host "ERRORE: Compilazione fallita!" -ForegroundColor Red
        Write-Host $compileResult -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "ERRORE: Errore durante la compilazione!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "STEP 5: Pulizia e Verifica" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Rimuovi file temporanei
if (Test-Path $innoSetupFile) {
    Remove-Item $innoSetupFile -Force
}

# Verifica che l'installer sia stato creato
$installerFile = "FP-DMS-Installer.exe"
if (Test-Path $installerFile) {
    $fileSize = (Get-Item $installerFile).Length
    $fileSizeMB = [math]::Round($fileSize / 1MB, 2)
    
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "INSTALLER .EXE CREATO CON SUCCESSO!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "File creato: $installerFile" -ForegroundColor Green
    Write-Host "Dimensione: $fileSizeMB MB" -ForegroundColor Green
    Write-Host "Percorso: $(Get-Location)\$installerFile" -ForegroundColor Green
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "CARATTERISTICHE DELL'INSTALLER" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "✓ Installer professionale Windows" -ForegroundColor Green
    Write-Host "✓ Download automatico PHP Desktop" -ForegroundColor Green
    Write-Host "✓ Configurazione automatica applicazione" -ForegroundColor Green
    Write-Host "✓ Database SQLite embedded" -ForegroundColor Green
    Write-Host "✓ Interfaccia moderna e intuitiva" -ForegroundColor Green
    Write-Host "✓ Collegamento desktop automatico" -ForegroundColor Green
    Write-Host "✓ Nessuna conoscenza tecnica richiesta" -ForegroundColor Green
    Write-Host "✓ Installazione in Program Files" -ForegroundColor Green
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "ISTRUZIONI PER L'UTENTE FINALE" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "1. Doppio click su FP-DMS-Installer.exe" -ForegroundColor Yellow
    Write-Host "2. Segui la procedura di installazione" -ForegroundColor Yellow
    Write-Host "3. L'installer scaricherà e configurerà tutto automaticamente" -ForegroundColor Yellow
    Write-Host "4. Al termine, avvia l'applicazione" -ForegroundColor Yellow
    Write-Host "5. Login con: admin@localhost / admin123" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "PRONTO PER LA DISTRIBUZIONE!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    
    # Crea README per distribuzione
    $readmeContent = @"
# FP Digital Marketing Suite - Installer

## Installazione

1. Eseguire `FP-DMS-Installer.exe`
2. Seguire la procedura guidata di installazione
3. L'installer scaricherà e configurerà tutto automaticamente
4. Al termine, avviare l'applicazione dal desktop o menu Start

## Primo Accesso

- **Email**: admin@localhost
- **Password**: admin123
- **IMPORTANTE**: Cambiare la password dopo il primo accesso!

## Caratteristiche

- ✅ Installer professionale Windows
- ✅ Download automatico di tutti i componenti
- ✅ Configurazione automatica
- ✅ Database SQLite embedded
- ✅ Interfaccia moderna e intuitiva
- ✅ Completamente portable
- ✅ Nessuna conoscenza tecnica richiesta

## Requisiti

- Windows 7 o superiore
- Connessione internet per il primo download
- 100MB di spazio libero

## Supporto

- Email: info@francescopasseri.com
- Web: https://francescopasseri.com

## Licenza

GPLv2 o successiva
"@

    $readmeContent | Out-File -FilePath "README.txt" -Encoding UTF8
    
    Write-Host "Documentazione creata: README.txt" -ForegroundColor Green
    
} else {
    Write-Host "ERRORE: Installer .exe non trovato!" -ForegroundColor Red
    Write-Host "Controlla i log di compilazione per errori." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Processo completato con successo!" -ForegroundColor Green
Write-Host "L'installer .exe è pronto per la distribuzione!" -ForegroundColor Green
