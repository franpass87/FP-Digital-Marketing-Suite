# Applicazione Desktop/Taskbar per FP DMS

## 🎯 Obiettivo

Creare un'applicazione desktop che gira in **taskbar/system tray** per:
- ✅ Monitoring continuo in background
- ✅ Notifiche desktop per anomalie
- ✅ Accesso rapido alle funzioni
- ✅ Controllo scheduler senza browser
- ✅ Status indicator visivo

## 🚀 Soluzioni Disponibili

### Opzione 1: Electron (Cross-Platform) ⭐ RACCOMANDATO

**Pro:**
- ✅ Windows, macOS, Linux
- ✅ JavaScript/TypeScript familiar
- ✅ UI moderna con HTML/CSS
- ✅ Accesso API native
- ✅ Auto-update integrato

**Contro:**
- ⚠️ Pesante (~100MB)
- ⚠️ Resource intensive

#### Implementazione Electron

```bash
# Struttura progetto
fpdms-desktop/
├── package.json
├── main.js                # Processo principale
├── preload.js            # Bridge sicuro
├── renderer/             # UI (HTML/CSS/JS)
│   ├── index.html
│   ├── app.js
│   └── styles.css
└── assets/
    └── icons/
        ├── icon.png
        ├── tray.png
        └── tray-alert.png
```

```json
// package.json
{
  "name": "fpdms-desktop",
  "version": "1.0.0",
  "main": "main.js",
  "scripts": {
    "start": "electron .",
    "build:win": "electron-builder --win",
    "build:mac": "electron-builder --mac",
    "build:linux": "electron-builder --linux"
  },
  "dependencies": {
    "electron": "^28.0.0",
    "axios": "^1.6.0",
    "node-notifier": "^10.0.1"
  },
  "devDependencies": {
    "electron-builder": "^24.9.0"
  },
  "build": {
    "appId": "com.francescopasseri.fpdms",
    "productName": "FP Digital Marketing Suite",
    "win": {
      "target": "nsis",
      "icon": "assets/icons/icon.ico"
    },
    "mac": {
      "target": "dmg",
      "icon": "assets/icons/icon.icns"
    },
    "linux": {
      "target": "AppImage",
      "icon": "assets/icons/icon.png"
    }
  }
}
```

```javascript
// main.js - Processo principale Electron
const { app, BrowserWindow, Tray, Menu, ipcMain, Notification } = require('electron');
const path = require('path');
const axios = require('axios');

let tray = null;
let mainWindow = null;

// Configurazione
const CONFIG = {
    apiUrl: process.env.FPDMS_API_URL || 'http://localhost:8080',
    apiKey: process.env.FPDMS_API_KEY || '',
    checkInterval: 60000, // 1 minuto
};

// Crea finestra principale (nascosta)
function createWindow() {
    mainWindow = new BrowserWindow({
        width: 900,
        height: 700,
        show: false,
        icon: path.join(__dirname, 'assets/icons/icon.png'),
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false
        }
    });

    mainWindow.loadFile('renderer/index.html');

    // Nascondi invece di chiudere
    mainWindow.on('close', (event) => {
        if (!app.isQuitting) {
            event.preventDefault();
            mainWindow.hide();
        }
        return false;
    });
}

// Crea tray icon
function createTray() {
    const iconPath = path.join(__dirname, 'assets/icons/tray.png');
    tray = new Tray(iconPath);

    const contextMenu = Menu.buildFromTemplate([
        {
            label: 'FP Digital Marketing Suite',
            enabled: false
        },
        { type: 'separator' },
        {
            label: 'Dashboard',
            click: () => {
                mainWindow.show();
            }
        },
        {
            label: 'Status',
            submenu: [
                { label: 'Scheduler: ●', enabled: false, id: 'scheduler-status' },
                { label: 'Queue: ●', enabled: false, id: 'queue-status' },
                { label: 'Database: ●', enabled: false, id: 'db-status' }
            ]
        },
        { type: 'separator' },
        {
            label: 'Quick Actions',
            submenu: [
                {
                    label: 'Run Scheduler Now',
                    click: () => runScheduler()
                },
                {
                    label: 'Check Anomalies',
                    click: () => checkAnomalies()
                },
                {
                    label: 'View Logs',
                    click: () => openLogs()
                }
            ]
        },
        { type: 'separator' },
        {
            label: 'Open Web Interface',
            click: () => {
                require('electron').shell.openExternal(CONFIG.apiUrl);
            }
        },
        { type: 'separator' },
        {
            label: 'Settings',
            click: () => {
                mainWindow.show();
                mainWindow.webContents.send('show-settings');
            }
        },
        {
            label: 'Quit',
            click: () => {
                app.isQuitting = true;
                app.quit();
            }
        }
    ]);

    tray.setContextMenu(contextMenu);
    tray.setToolTip('FP Digital Marketing Suite');

    // Double click per mostrare finestra
    tray.on('double-click', () => {
        mainWindow.show();
    });
}

// Funzioni API
async function checkStatus() {
    try {
        const response = await axios.get(`${CONFIG.apiUrl}/api/v1/health`, {
            headers: { 'X-API-Key': CONFIG.apiKey },
            timeout: 5000
        });

        updateTrayStatus(response.data);
        return response.data;
    } catch (error) {
        console.error('Health check failed:', error.message);
        setTrayError();
        return null;
    }
}

async function checkAnomalies() {
    try {
        const response = await axios.get(`${CONFIG.apiUrl}/api/v1/anomalies/recent`, {
            headers: { 'X-API-Key': CONFIG.apiKey }
        });

        if (response.data.count > 0) {
            showNotification(
                'Anomalies Detected!',
                `${response.data.count} new anomalies found`
            );
        }

        return response.data;
    } catch (error) {
        console.error('Anomaly check failed:', error.message);
    }
}

async function runScheduler() {
    try {
        showNotification('Scheduler', 'Running scheduled tasks...');
        
        await axios.post(`${CONFIG.apiUrl}/api/v1/scheduler/run`, {}, {
            headers: { 'X-API-Key': CONFIG.apiKey }
        });

        showNotification('Scheduler', 'Tasks completed successfully!');
    } catch (error) {
        showNotification('Scheduler Error', error.message);
    }
}

// Update tray status
function updateTrayStatus(status) {
    const menu = tray.getContextMenu();
    
    // Update status indicators
    const schedulerItem = menu.getMenuItemById('scheduler-status');
    const queueItem = menu.getMenuItemById('queue-status');
    const dbItem = menu.getMenuItemById('db-status');

    if (schedulerItem) {
        schedulerItem.label = `Scheduler: ${status.scheduler === 'healthy' ? '🟢' : '🔴'}`;
    }
    if (queueItem) {
        queueItem.label = `Queue: ${status.queue === 'healthy' ? '🟢' : '🔴'}`;
    }
    if (dbItem) {
        dbItem.label = `Database: ${status.database === 'healthy' ? '🟢' : '🔴'}`;
    }

    // Update icon
    const iconName = status.overall === 'healthy' ? 'tray.png' : 'tray-alert.png';
    tray.setImage(path.join(__dirname, 'assets/icons', iconName));
}

function setTrayError() {
    tray.setImage(path.join(__dirname, 'assets/icons/tray-alert.png'));
}

// Notifiche desktop
function showNotification(title, body) {
    new Notification({
        title,
        body,
        icon: path.join(__dirname, 'assets/icons/icon.png')
    }).show();
}

function openLogs() {
    const logsPath = path.join(CONFIG.apiUrl.replace('http://localhost:8080', ''), 'storage/logs');
    require('electron').shell.openPath(logsPath);
}

// Auto-start monitoring
let statusInterval;

function startMonitoring() {
    // Check immediato
    checkStatus();
    checkAnomalies();

    // Check periodico
    statusInterval = setInterval(async () => {
        await checkStatus();
        
        // Check anomalies ogni 5 minuti
        if (Date.now() % (5 * 60 * 1000) < CONFIG.checkInterval) {
            await checkAnomalies();
        }
    }, CONFIG.checkInterval);
}

function stopMonitoring() {
    if (statusInterval) {
        clearInterval(statusInterval);
    }
}

// IPC Handlers
ipcMain.handle('get-status', async () => {
    return await checkStatus();
});

ipcMain.handle('get-config', () => {
    return CONFIG;
});

ipcMain.handle('update-config', (event, newConfig) => {
    Object.assign(CONFIG, newConfig);
    // Salva in file o registro
    return true;
});

// App lifecycle
app.whenReady().then(() => {
    createWindow();
    createTray();
    startMonitoring();

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) {
            createWindow();
        }
    });
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        // Non uscire, continua in background
    }
});

app.on('before-quit', () => {
    stopMonitoring();
});
```

```javascript
// preload.js - Bridge sicuro
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('fpdms', {
    getStatus: () => ipcRenderer.invoke('get-status'),
    getConfig: () => ipcRenderer.invoke('get-config'),
    updateConfig: (config) => ipcRenderer.invoke('update-config', config),
    
    onShowSettings: (callback) => {
        ipcRenderer.on('show-settings', callback);
    }
});
```

```html
<!-- renderer/index.html - UI principale -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FP Digital Marketing Suite</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🚀 FP Digital Marketing Suite</h1>
            <div id="connection-status" class="status offline">●</div>
        </header>

        <main>
            <!-- Dashboard Tab -->
            <section id="dashboard" class="tab-content active">
                <h2>Dashboard</h2>
                
                <div class="cards">
                    <div class="card">
                        <h3>Scheduler Status</h3>
                        <div id="scheduler-status" class="metric">
                            <span class="value">--</span>
                            <span class="label">Last Run</span>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Queue</h3>
                        <div id="queue-status" class="metric">
                            <span class="value">--</span>
                            <span class="label">Pending Jobs</span>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Anomalies</h3>
                        <div id="anomalies-status" class="metric">
                            <span class="value">--</span>
                            <span class="label">Recent Alerts</span>
                        </div>
                    </div>
                </div>

                <div class="recent-activity">
                    <h3>Recent Activity</h3>
                    <div id="activity-log"></div>
                </div>
            </section>

            <!-- Settings Tab -->
            <section id="settings" class="tab-content">
                <h2>Settings</h2>
                
                <form id="settings-form">
                    <div class="form-group">
                        <label>API URL</label>
                        <input type="text" id="api-url" placeholder="http://localhost:8080">
                    </div>

                    <div class="form-group">
                        <label>API Key</label>
                        <input type="password" id="api-key" placeholder="Your API key">
                    </div>

                    <div class="form-group">
                        <label>Check Interval (seconds)</label>
                        <input type="number" id="check-interval" value="60" min="10" max="3600">
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="start-on-boot">
                            Start on system boot
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="show-notifications">
                            Show desktop notifications
                        </label>
                    </div>

                    <button type="submit">Save Settings</button>
                </form>
            </section>
        </main>

        <nav class="tabs">
            <button class="tab active" data-tab="dashboard">Dashboard</button>
            <button class="tab" data-tab="settings">Settings</button>
        </nav>
    </div>

    <script src="app.js"></script>
</body>
</html>
```

```javascript
// renderer/app.js - Logic UI
let statusInterval;

// Tab switching
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const tabName = tab.dataset.tab;
        
        // Update tabs
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Update content
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
    });
});

// Update status
async function updateStatus() {
    try {
        const status = await window.fpdms.getStatus();
        
        // Update connection indicator
        const indicator = document.getElementById('connection-status');
        indicator.className = `status ${status.overall === 'healthy' ? 'online' : 'offline'}`;
        
        // Update metrics
        document.querySelector('#scheduler-status .value').textContent = 
            status.scheduler?.last_run || 'Never';
        
        document.querySelector('#queue-status .value').textContent = 
            status.queue?.pending || '0';
        
        document.querySelector('#anomalies-status .value').textContent = 
            status.anomalies?.count || '0';
        
        // Update activity log
        if (status.recent_activity) {
            const log = document.getElementById('activity-log');
            log.innerHTML = status.recent_activity.map(activity => `
                <div class="activity-item">
                    <span class="time">${activity.time}</span>
                    <span class="message">${activity.message}</span>
                </div>
            `).join('');
        }
        
    } catch (error) {
        console.error('Failed to update status:', error);
        document.getElementById('connection-status').className = 'status offline';
    }
}

// Settings
async function loadSettings() {
    const config = await window.fpdms.getConfig();
    
    document.getElementById('api-url').value = config.apiUrl;
    document.getElementById('api-key').value = config.apiKey;
    document.getElementById('check-interval').value = config.checkInterval / 1000;
}

document.getElementById('settings-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const config = {
        apiUrl: document.getElementById('api-url').value,
        apiKey: document.getElementById('api-key').value,
        checkInterval: parseInt(document.getElementById('check-interval').value) * 1000
    };
    
    await window.fpdms.updateConfig(config);
    alert('Settings saved!');
});

// Listen for settings request from tray
window.fpdms.onShowSettings(() => {
    document.querySelector('[data-tab="settings"]').click();
});

// Auto-refresh
function startAutoRefresh() {
    updateStatus();
    statusInterval = setInterval(updateStatus, 60000); // Ogni minuto
}

function stopAutoRefresh() {
    if (statusInterval) {
        clearInterval(statusInterval);
    }
}

// Init
window.addEventListener('DOMContentLoaded', () => {
    loadSettings();
    startAutoRefresh();
});

window.addEventListener('beforeunload', () => {
    stopAutoRefresh();
});
```

```css
/* renderer/styles.css */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f5f5f5;
    color: #333;
}

.container {
    display: flex;
    flex-direction: column;
    height: 100vh;
}

header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

header h1 {
    font-size: 24px;
}

.status {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.status.online {
    background: #10b981;
    box-shadow: 0 0 10px #10b981;
}

.status.offline {
    background: #ef4444;
    box-shadow: 0 0 10px #ef4444;
}

main {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card h3 {
    font-size: 16px;
    color: #666;
    margin-bottom: 15px;
}

.metric {
    display: flex;
    flex-direction: column;
}

.metric .value {
    font-size: 32px;
    font-weight: bold;
    color: #667eea;
}

.metric .label {
    font-size: 12px;
    color: #999;
    margin-top: 5px;
}

.recent-activity {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.activity-item {
    padding: 10px 0;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item .time {
    color: #999;
    font-size: 12px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-group input[type="text"],
.form-group input[type="password"],
.form-group input[type="number"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.form-group input[type="checkbox"] {
    margin-right: 8px;
}

button[type="submit"] {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: transform 0.2s;
}

button[type="submit"]:hover {
    transform: translateY(-2px);
}

.tabs {
    display: flex;
    background: white;
    border-top: 1px solid #eee;
}

.tabs button {
    flex: 1;
    padding: 15px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 14px;
    color: #666;
    transition: all 0.3s;
}

.tabs button.active {
    color: #667eea;
    border-top: 3px solid #667eea;
}

.tabs button:hover {
    background: #f9f9f9;
}
```

#### Build e Distribuzione

```bash
# Installa dipendenze
npm install

# Test in sviluppo
npm start

# Build per tutte le piattaforme
npm run build:win    # Windows
npm run build:mac    # macOS
npm run build:linux  # Linux

# Output in dist/
# - Windows: FPDMS-Setup-1.0.0.exe
# - macOS: FPDMS-1.0.0.dmg
# - Linux: FPDMS-1.0.0.AppImage
```

### Opzione 2: Tauri (Rust + Web) 🦀

**Pro:**
- ✅ Leggerissimo (~3MB vs 100MB Electron)
- ✅ Velocissimo (Rust backend)
- ✅ Sicuro by design
- ✅ Cross-platform

**Contro:**
- ⚠️ Richiede Rust toolchain
- ⚠️ Comunità più piccola di Electron

### Opzione 3: Python + Qt (PyQt/PySide) 🐍

**Pro:**
- ✅ Integrazione diretta con backend PHP
- ✅ UI native
- ✅ Leggero

**Contro:**
- ⚠️ Python runtime necessario
- ⚠️ Packaging complesso

### Opzione 4: Native (C#/Swift/Java)

**Windows - C# WPF:**
```csharp
// Tray application
using System.Windows.Forms;

public class FPDMSTrayApp : ApplicationContext
{
    private NotifyIcon trayIcon;
    
    public FPDMSTrayApp()
    {
        trayIcon = new NotifyIcon()
        {
            Icon = new Icon("icon.ico"),
            ContextMenu = new ContextMenu(new MenuItem[] {
                new MenuItem("Dashboard", OnDashboard),
                new MenuItem("Settings", OnSettings),
                new MenuItem("Exit", OnExit)
            }),
            Visible = true
        };
    }
}
```

**macOS - Swift:**
```swift
// Menu bar app
import Cocoa

class StatusBarController {
    let statusItem = NSStatusBar.system.statusItem(withLength: NSStatusItem.squareLength)
    
    init() {
        if let button = statusItem.button {
            button.image = NSImage(named: "StatusIcon")
        }
        
        let menu = NSMenu()
        menu.addItem(NSMenuItem(title: "Dashboard", action: #selector(openDashboard), keyEquivalent: "d"))
        menu.addItem(NSMenuItem.separator())
        menu.addItem(NSMenuItem(title: "Quit", action: #selector(quit), keyEquivalent: "q"))
        
        statusItem.menu = menu
    }
}
```

## 📋 Funzionalità Desktop App

### 1. System Tray Icon
- 🔵 Blu: Tutto OK
- 🟡 Giallo: Warning
- 🔴 Rosso: Errore
- 🔄 Animato: Task in esecuzione

### 2. Quick Actions Menu
- ▶️ Run Scheduler Now
- 📊 View Dashboard
- 🔔 Check Anomalies
- 📝 View Logs
- ⚙️ Settings
- ❌ Quit

### 3. Desktop Notifications
- 🚨 Nuove anomalie rilevate
- ✅ Report generati con successo
- ⚠️ Errori scheduler
- 📧 Email inviate

### 4. Auto-Start
- ✅ Avvia con Windows/macOS/Linux
- ✅ Minimizza in tray all'avvio
- ✅ Background worker sempre attivo

### 5. Dashboard Window
- 📊 Status overview
- 📈 Grafici real-time
- 📋 Recent activity log
- ⚙️ Settings panel

## 🎯 Architettura Desktop + Backend

```
┌────────────────────────────────────────┐
│  Desktop App (Electron)                │
│  ┌──────────────────────────────────┐  │
│  │  Tray Icon (always visible)      │  │
│  └──────────────────────────────────┘  │
│  ┌──────────────────────────────────┐  │
│  │  Background Worker               │  │
│  │  - Check status ogni minuto      │  │
│  │  - Monitor anomalies             │  │
│  │  - Show notifications            │  │
│  └──────────────────────────────────┘  │
│  ┌──────────────────────────────────┐  │
│  │  Dashboard Window (hidden)       │  │
│  │  - Opens on demand               │  │
│  │  - Real-time updates             │  │
│  └──────────────────────────────────┘  │
└────────────────┬───────────────────────┘
                 │ HTTP/REST API
                 ▼
┌────────────────────────────────────────┐
│  Backend (PHP Standalone)              │
│  ┌──────────────────────────────────┐  │
│  │  REST API                        │  │
│  │  /api/v1/health                  │  │
│  │  /api/v1/status                  │  │
│  │  /api/v1/anomalies               │  │
│  │  /api/v1/scheduler/run           │  │
│  └──────────────────────────────────┘  │
│  ┌──────────────────────────────────┐  │
│  │  Background Scheduler (Cron)     │  │
│  └──────────────────────────────────┘  │
└────────────────────────────────────────┘
```

## 🚀 Quick Start

```bash
# 1. Clone desktop app
git clone https://github.com/francescopasseri/fpdms-desktop
cd fpdms-desktop

# 2. Install dependencies
npm install

# 3. Configure backend URL
echo "FPDMS_API_URL=http://localhost:8080" > .env
echo "FPDMS_API_KEY=your-api-key" >> .env

# 4. Start in development
npm start

# 5. Build for production
npm run build:win    # or build:mac, build:linux
```

## 📦 Distribuzione

### Windows
```
FPDMS-Setup-1.0.0.exe (installer)
- Auto-update support
- Start with Windows
- System tray integration
```

### macOS
```
FPDMS-1.0.0.dmg (disk image)
- Code signed
- Notarized by Apple
- Menu bar integration
```

### Linux
```
FPDMS-1.0.0.AppImage (portable)
- No installation needed
- Works on all distros
- System tray support
```

## 🔔 Esempio Notifiche

```javascript
// Notifica anomalia
new Notification('🚨 Anomaly Detected!', {
    body: 'Client ABC: Traffic drop -45%',
    icon: 'alert-icon.png',
    urgency: 'critical',
    actions: [
        { action: 'view', title: 'View Details' },
        { action: 'dismiss', title: 'Dismiss' }
    ]
});

// Notifica success
new Notification('✅ Report Generated', {
    body: '15 reports sent successfully',
    icon: 'success-icon.png'
});
```

## 🎨 UI Preview

```
┌─────────────────────────────────────┐
│ 🚀 FP Digital Marketing Suite    ● │
├─────────────────────────────────────┤
│                                     │
│  ┌──────────┐ ┌──────────┐ ┌──────┐│
│  │Scheduler │ │  Queue   │ │Alerts││
│  │          │ │          │ │      ││
│  │  🟢      │ │   3      │ │  2   ││
│  │ Healthy  │ │ Pending  │ │ New  ││
│  └──────────┘ └──────────┘ └──────┘│
│                                     │
│  Recent Activity:                   │
│  ────────────────────────────────   │
│  14:35 ✅ Report generated          │
│  14:30 📧 Email sent to client      │
│  14:25 🔄 Scheduler tick            │
│  14:20 ⚠️  Anomaly detected         │
│                                     │
├─────────────────────────────────────┤
│ [Dashboard] [Settings]              │
└─────────────────────────────────────┘
```

Vuoi che proceda con l'implementazione completa dell'app Electron?
