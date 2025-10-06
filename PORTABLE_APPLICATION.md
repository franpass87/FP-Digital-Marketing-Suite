# FP DMS - Applicazione Portable Windows

## 🎯 Obiettivo

Creare un'applicazione **completamente portable** per Windows:
- ✅ Un solo file .exe da cliccare
- ✅ Nessuna installazione richiesta
- ✅ Tutto incluso (PHP, database, server web)
- ✅ Funziona da USB stick
- ✅ Nessuna configurazione necessaria

## 🚀 Soluzioni Disponibili

### Opzione 1: PHP Desktop (Chromium Embedded) ⭐ RACCOMANDATO

**Caratteristiche:**
- ✅ PHP + Chromium in un unico .exe
- ✅ UI nativa tipo applicazione desktop
- ✅ SQLite database embedded
- ✅ Completamente portable
- ✅ ~50MB totale

#### Struttura Progetto

```
FP-DMS-Portable/
├── FP-DMS.exe              # Eseguibile principale (PHP Desktop)
├── www/                     # Applicazione PHP
│   ├── public/
│   │   └── index.php
│   ├── src/
│   ├── storage/
│   │   ├── database.sqlite  # Database embedded
│   │   ├── logs/
│   │   └── uploads/
│   ├── vendor/
│   └── .env
├── php/                     # PHP runtime embedded
│   ├── php.exe
│   └── extensions/
├── settings.json            # Configurazione PHP Desktop
└── README.txt
```

#### Setup PHP Desktop

**1. Download PHP Desktop**

```bash
# Download da: https://github.com/cztomczak/phpdesktop/releases
# File: phpdesktop-chrome-57.0-msvc-php-7.1.3.zip

# Extract contenuto
```

**2. Configura settings.json**

```json
{
    "title": "FP Digital Marketing Suite",
    "main_window": {
        "default_size": [1200, 800],
        "minimum_size": [800, 600],
        "maximum_size": [0, 0],
        "disable_maximize_button": false,
        "center_on_screen": true,
        "start_maximized": false,
        "start_minimized": false
    },
    "web_server": {
        "listen_on": ["127.0.0.1", 8080],
        "www_directory": "www",
        "index_files": ["index.php", "index.html"],
        "cgi_interpreter": "php/php-cgi.exe",
        "cgi_extensions": ["php"],
        "cgi_temp_dir": ""
    },
    "chrome": {
        "cache_path": "webcache",
        "context_menu": {
            "enable_menu": false
        },
        "command_line_switches": {
            "enable-media-stream": "",
            "disable-web-security": "",
            "allow-file-access-from-files": ""
        },
        "external_navigation": {
            "allow_popups": false,
            "allow_navigation": false
        }
    },
    "application": {
        "hide_php_console": true
    }
}
```

**3. Adatta Database per SQLite**

```php
// www/.env
APP_NAME="FP Digital Marketing Suite"
DB_CONNECTION=sqlite
DB_DATABASE=storage/database.sqlite
APP_PORTABLE=true
```

**4. Database Adapter SQLite**

```php
// src/App/Database/Database.php - Aggiorna per supportare SQLite
<?php

namespace FP\DMS\App\Database;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private ?PDO $pdo = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $driver = $this->config['driver'] ?? 'mysql';

        try {
            if ($driver === 'sqlite') {
                // SQLite per versione portable
                $dbPath = $this->config['database'] ?? 'storage/database.sqlite';
                
                // Crea directory se non esiste
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                $dsn = "sqlite:{$dbPath}";
                $this->pdo = new PDO($dsn);
            } else {
                // MySQL per versione server
                $host = $this->config['host'] ?? 'localhost';
                $port = $this->config['port'] ?? 3306;
                $database = $this->config['database'] ?? '';
                $charset = $this->config['charset'] ?? 'utf8mb4';

                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $host,
                    $port,
                    $database,
                    $charset
                );

                $this->pdo = new PDO(
                    $dsn,
                    $this->config['username'] ?? '',
                    $this->config['password'] ?? '',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            }

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        return $this->pdo;
    }

    public function getPrefix(): string
    {
        return $this->config['prefix'] ?? '';
    }

    public function table(string $name): string
    {
        return $this->getPrefix() . $name;
    }

    // ... altri metodi rimangono uguali ...
}
```

**5. Migration per SQLite**

```php
// src/App/Commands/DatabaseMigrateCommand.php - Aggiorna schema per SQLite
private function getSchema(Database $db): array
{
    $prefix = $db->getPrefix();
    $driver = $_ENV['DB_CONNECTION'] ?? 'mysql';
    
    // SQLite usa AUTOINCREMENT invece di AUTO_INCREMENT
    $autoIncrement = $driver === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT';
    
    // SQLite non supporta tutte le opzioni di charset
    $charset = $driver === 'sqlite' ? '' : $db->get_charset_collate();

    return [
        "CREATE TABLE IF NOT EXISTS {$prefix}clients (
            id INTEGER PRIMARY KEY {$autoIncrement},
            name VARCHAR(190) NOT NULL,
            email_to TEXT,
            email_cc TEXT,
            logo_id INTEGER,
            logo_url TEXT,
            timezone VARCHAR(64) DEFAULT 'UTC',
            notes TEXT,
            ga4_property_id VARCHAR(32),
            ga4_stream_id VARCHAR(32),
            ga4_measurement_id VARCHAR(32),
            gsc_site_property VARCHAR(255),
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) {$charset}",
        
        // ... altre tabelle con stessa sintassi SQLite-compatibile
    ];
}
```

**6. Scheduler Background per Portable**

```php
// www/scheduler.php - Script che gira in background
<?php

// Carica autoloader
require __DIR__ . '/vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use FP\DMS\App\ScheduleProvider;
use FP\DMS\Infra\Scheduler;

// Esegui in loop infinito
while (true) {
    try {
        $scheduler = new Scheduler();
        ScheduleProvider::register($scheduler);
        $scheduler->run();
        
        // Log
        file_put_contents(
            'storage/logs/scheduler.log',
            date('Y-m-d H:i:s') . " - Scheduler tick completed\n",
            FILE_APPEND
        );
        
    } catch (Exception $e) {
        file_put_contents(
            'storage/logs/scheduler-error.log',
            date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n",
            FILE_APPEND
        );
    }
    
    // Sleep 60 secondi
    sleep(60);
}
```

**7. Launcher Script**

```batch
@echo off
REM FP-DMS-Launcher.bat

echo Starting FP Digital Marketing Suite...
echo.

REM Avvia scheduler in background
start /B php\php.exe www\scheduler.php > NUL 2>&1

REM Avvia applicazione principale
start "" FP-DMS.exe

echo Application started!
echo Close this window to stop the scheduler.
echo.

REM Mantieni la finestra aperta per scheduler
pause
```

**8. Build Script**

```bash
#!/bin/bash
# build-portable.sh

echo "Building FP DMS Portable Application..."

# 1. Crea directory build
mkdir -p build/portable
cd build/portable

# 2. Download PHP Desktop
echo "Downloading PHP Desktop..."
wget https://github.com/cztomczak/phpdesktop/releases/download/v57.0/phpdesktop-chrome-57.0-msvc-php-7.1.3.zip
unzip phpdesktop-chrome-57.0-msvc-php-7.1.3.zip
rm phpdesktop-chrome-57.0-msvc-php-7.1.3.zip

# 3. Rinomina eseguibile
mv phpdesktop-chrome.exe FP-DMS.exe

# 4. Copia applicazione
echo "Copying application files..."
cp -r ../../src www/src
cp -r ../../public/* www/public/
cp ../../composer.json www/
cp ../../.env.example www/.env

# 5. Install dependencies (no-dev)
echo "Installing dependencies..."
cd www
composer install --no-dev --optimize-autoloader
cd ..

# 6. Crea database SQLite vuoto
echo "Creating SQLite database..."
touch www/storage/database.sqlite
php php/php.exe www/public/../cli.php db:migrate

# 7. Crea settings.json
cat > settings.json << 'EOF'
{
    "title": "FP Digital Marketing Suite",
    "main_window": {
        "default_size": [1200, 800],
        "center_on_screen": true
    },
    "web_server": {
        "listen_on": ["127.0.0.1", 8080],
        "www_directory": "www",
        "index_files": ["index.php"]
    },
    "application": {
        "hide_php_console": true
    }
}
EOF

# 8. Crea launcher
cat > FP-DMS-Launcher.bat << 'EOF'
@echo off
echo Starting FP Digital Marketing Suite...
start /B php\php.exe www\scheduler.php > NUL 2>&1
start "" FP-DMS.exe
pause
EOF

# 9. Crea README
cat > README.txt << 'EOF'
FP Digital Marketing Suite - Portable Edition

QUICK START:
1. Double-click FP-DMS.exe to start
2. The application will open automatically
3. Default login: admin / admin123 (change immediately!)

FEATURES:
- Completely portable (no installation)
- Works from USB stick
- SQLite database included
- Background scheduler included

REQUIREMENTS:
- Windows 7 or higher
- No internet connection required (after first setup)

SUPPORT:
Email: info@francescopasseri.com
Web: https://francescopasseri.com

EOF

# 10. Comprimi per distribuzione
echo "Creating distribution package..."
cd ..
zip -r FP-DMS-Portable-v1.0.0.zip portable/

echo "Build complete! Package: FP-DMS-Portable-v1.0.0.zip"
```

### Opzione 2: Electron + PHP Embedded 🔋

Per un'app ancora più moderna con UI nativa.

```
FP-DMS-Desktop/
├── FP-DMS.exe              # Electron app
├── resources/
│   ├── app/                # Frontend Electron
│   └── php/                # PHP embedded
│       ├── php.exe
│       ├── application/    # Codice PHP
│       └── database.sqlite
```

**Vantaggi:**
- UI più moderna e responsive
- Migliore integrazione sistema operativo
- Aggiornamenti automatici
- Cross-platform (se serve Mac/Linux)

**Package.json per build:**

```json
{
  "name": "fpdms-desktop",
  "version": "1.0.0",
  "main": "main.js",
  "scripts": {
    "start": "electron .",
    "pack": "electron-builder --dir",
    "dist": "electron-builder"
  },
  "build": {
    "appId": "com.francescopasseri.fpdms",
    "productName": "FP Digital Marketing Suite",
    "win": {
      "target": "portable",
      "icon": "icon.ico"
    },
    "extraResources": [
      {
        "from": "php",
        "to": "php",
        "filter": ["**/*"]
      }
    ],
    "files": [
      "**/*",
      "!php/**/*"
    ]
  },
  "dependencies": {
    "electron": "^28.0.0"
  },
  "devDependencies": {
    "electron-builder": "^24.9.0"
  }
}
```

**main.js - Electron con PHP Backend:**

```javascript
const { app, BrowserWindow } = require('electron');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

let phpServer = null;
let mainWindow = null;

// Start PHP built-in server
function startPhpServer() {
    const phpPath = path.join(
        process.resourcesPath,
        'php',
        'php.exe'
    );
    
    const appPath = path.join(
        process.resourcesPath,
        'php',
        'application'
    );
    
    // Avvia PHP built-in server
    phpServer = spawn(phpPath, [
        '-S', '127.0.0.1:8080',
        '-t', path.join(appPath, 'public')
    ], {
        cwd: appPath,
        env: {
            ...process.env,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: path.join(appPath, 'storage', 'database.sqlite'),
            APP_PORTABLE: 'true'
        }
    });
    
    phpServer.stdout.on('data', (data) => {
        console.log(`PHP: ${data}`);
    });
    
    phpServer.stderr.on('data', (data) => {
        console.error(`PHP Error: ${data}`);
    });
    
    return new Promise((resolve) => {
        // Attendi che server sia pronto
        setTimeout(resolve, 2000);
    });
}

// Start background scheduler
function startScheduler() {
    const phpPath = path.join(
        process.resourcesPath,
        'php',
        'php.exe'
    );
    
    const schedulerScript = path.join(
        process.resourcesPath,
        'php',
        'application',
        'scheduler.php'
    );
    
    const scheduler = spawn(phpPath, [schedulerScript], {
        detached: true,
        stdio: 'ignore'
    });
    
    scheduler.unref();
}

// Create main window
async function createWindow() {
    // Start PHP server first
    await startPhpServer();
    
    // Start scheduler
    startScheduler();
    
    mainWindow = new BrowserWindow({
        width: 1200,
        height: 800,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true
        },
        icon: path.join(__dirname, 'icon.ico'),
        autoHideMenuBar: true
    });
    
    // Load PHP application
    mainWindow.loadURL('http://127.0.0.1:8080');
    
    // Open DevTools in development
    if (process.env.NODE_ENV === 'development') {
        mainWindow.webContents.openDevTools();
    }
    
    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
    // Kill PHP server
    if (phpServer) {
        phpServer.kill();
    }
    
    app.quit();
});

app.on('activate', () => {
    if (mainWindow === null) {
        createWindow();
    }
});

// Handle app quit
app.on('before-quit', () => {
    if (phpServer) {
        phpServer.kill();
    }
});
```

**Build Portable .exe:**

```bash
# Build per Windows portable
npm run dist

# Output: dist/FP-DMS-1.0.0.exe (portable, ~60MB)
```

### Opzione 3: XAMPP Portable Customizzato 📦

Per massima compatibilità con ambiente LAMP completo.

```
FP-DMS-XAMPP/
├── Start-FP-DMS.exe        # Launcher personalizzato
├── xampp/
│   ├── apache/
│   ├── php/
│   ├── mysql/
│   └── htdocs/
│       └── fpdms/          # Applicazione
├── config/
└── data/
```

## 🎯 Comparazione Soluzioni

| Feature | PHP Desktop | Electron+PHP | XAMPP Custom |
|---------|------------|--------------|--------------|
| **Size** | ~50MB | ~60MB | ~150MB |
| **Setup** | Click & Run | Click & Run | Click & Run |
| **UI** | Browser embedded | Native Electron | Browser |
| **Performance** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Updates** | Manual | Auto-update | Manual |
| **Cross-platform** | Windows only | Win/Mac/Linux | Win/Mac/Linux |
| **Complessità** | Bassa | Media | Alta |
| **Raccomandato** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |

## 📦 Package Finale

### Struttura Distribuzione (PHP Desktop)

```
FP-DMS-Portable-v1.0.0/
├── FP-DMS.exe                    # 📱 Click to start!
├── FP-DMS-Launcher.bat           # Alternative launcher
├── README.txt                    # Quick start guide
├── LICENSE.txt
├── www/                          # Applicazione PHP
│   ├── public/
│   ├── src/
│   ├── vendor/
│   ├── storage/
│   │   ├── database.sqlite       # 💾 Database embedded
│   │   ├── logs/
│   │   ├── uploads/
│   │   └── cache/
│   └── .env
├── php/                          # PHP runtime
│   ├── php.exe
│   ├── php-cgi.exe
│   └── ext/
├── settings.json                 # PHP Desktop config
└── webcache/                     # Browser cache

TOTALE: ~50MB
```

### User Experience

```
1. Utente scarica: FP-DMS-Portable-v1.0.0.zip
2. Estrae in qualsiasi cartella (anche USB stick)
3. Doppio click su FP-DMS.exe
4. Applicazione si apre automaticamente
5. Login con credenziali default
6. DONE! ✅
```

### First Run Setup

```php
// www/public/index.php - Detect first run
<?php

$dbFile = __DIR__ . '/../storage/database.sqlite';

if (!file_exists($dbFile) || filesize($dbFile) === 0) {
    // First run - redirect to setup wizard
    header('Location: /setup');
    exit;
}

// Normal application bootstrap
require __DIR__ . '/../bootstrap.php';
```

```php
// www/public/setup/index.php - Setup wizard
<!DOCTYPE html>
<html>
<head>
    <title>FP DMS - First Run Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }
        .step {
            background: #f5f5f5;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <h1>🚀 Welcome to FP Digital Marketing Suite</h1>
    
    <div class="step">
        <h2>Step 1: Create Database</h2>
        <p>Click to initialize the database...</p>
        <button onclick="createDatabase()">Initialize Database</button>
        <div id="db-status"></div>
    </div>
    
    <div class="step">
        <h2>Step 2: Create Admin User</h2>
        <form id="admin-form">
            <label>Email:</label>
            <input type="email" name="email" required><br><br>
            
            <label>Password:</label>
            <input type="password" name="password" required minlength="8"><br><br>
            
            <label>Display Name:</label>
            <input type="text" name="name" required><br><br>
            
            <button type="submit">Create Admin</button>
        </form>
        <div id="admin-status"></div>
    </div>
    
    <div class="step">
        <h2>Step 3: Start Application</h2>
        <p>Setup complete! Click to start using FP DMS.</p>
        <button onclick="window.location='/login'">Go to Login</button>
    </div>
    
    <script>
        async function createDatabase() {
            const status = document.getElementById('db-status');
            status.textContent = 'Creating database...';
            
            const response = await fetch('/api/setup/database', {
                method: 'POST'
            });
            
            const result = await response.json();
            
            if (result.success) {
                status.textContent = '✅ Database created successfully!';
                status.style.color = 'green';
            } else {
                status.textContent = '❌ Error: ' + result.error;
                status.style.color = 'red';
            }
        }
        
        document.getElementById('admin-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const status = document.getElementById('admin-status');
            
            status.textContent = 'Creating admin user...';
            
            const response = await fetch('/api/setup/admin', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                status.textContent = '✅ Admin user created!';
                status.style.color = 'green';
            } else {
                status.textContent = '❌ Error: ' + result.error;
                status.style.color = 'red';
            }
        });
    </script>
</body>
</html>
```

## 🚀 Distribuzione

### Build Command

```bash
# Build portable application
./build-portable.sh

# Output:
# build/FP-DMS-Portable-v1.0.0.zip (ready for distribution)
```

### Upload & Share

```bash
# Upload to GitHub Releases
gh release create v1.0.0 build/FP-DMS-Portable-v1.0.0.zip

# Or distribute via:
# - Website download
# - USB stick
# - Network share
# - Email (if under 25MB)
```

### User Instructions

```
📦 FP DIGITAL MARKETING SUITE - PORTABLE EDITION

INSTALLATION:
1. Extract FP-DMS-Portable-v1.0.0.zip to any folder
2. You can put it on:
   - Desktop
   - USB stick
   - Network drive
   - Any folder you want

USAGE:
1. Double-click FP-DMS.exe
2. First run: Follow setup wizard
3. Next runs: Just double-click!

NO INSTALLATION REQUIRED!
NO ADMINISTRATOR RIGHTS NEEDED!
NO INTERNET CONNECTION NEEDED (after setup)!

REQUIREMENTS:
- Windows 7 or later
- 100MB free disk space
- Nothing else!

SUPPORT:
info@francescopasseri.com
```

## ✅ Checklist Implementazione

- [ ] Setup PHP Desktop base
- [ ] Configurare settings.json
- [ ] Adattare database per SQLite
- [ ] Creare migration SQLite-compatibili
- [ ] Implementare setup wizard first-run
- [ ] Creare scheduler background
- [ ] Testare su Windows pulita (no PHP)
- [ ] Creare launcher batch
- [ ] Build script automatico
- [ ] Testare portabilità (USB stick)
- [ ] Creare documentazione utente
- [ ] Package per distribuzione

Vuoi che proceda con l'implementazione completa della versione portable?
