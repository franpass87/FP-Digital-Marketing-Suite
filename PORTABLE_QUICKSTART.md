# Quick Start: Applicazione Portable .exe

## 🎯 Cosa Ottieni

Un singolo file .exe che:
- ✅ Si apre con doppio click
- ✅ Non richiede installazione
- ✅ Funziona da USB stick
- ✅ Include tutto (PHP, database, server)
- ✅ ~50MB totale

## 🚀 Build in 3 Passi

### Passo 1: Scarica PHP Desktop

```bash
1. Vai su: https://github.com/cztomczak/phpdesktop/releases
2. Scarica: phpdesktop-chrome-57.0-msvc-php-7.4.zip
3. Estrai in: build/portable/
```

### Passo 2: Esegui Build Script

```bash
# Windows
build-portable.bat

# Segui le istruzioni a schermo
```

### Passo 3: Distribuisci

```bash
# Troverai:
build/FP-DMS-Portable-v1.0.0.zip

# Distribuisci questo ZIP!
```

## 📦 Cosa Include il Package

```
FP-DMS-Portable/
├── FP-DMS.exe              # ← DOPPIO CLICK QUI!
├── README.txt              # Istruzioni utente
├── www/                    # Applicazione PHP
│   ├── storage/
│   │   └── database.sqlite # Database embedded
│   └── ...
└── php/                    # PHP runtime
```

## 👤 Esperienza Utente

### Download & Avvio (Utente Finale)

```
1. Scarica FP-DMS-Portable-v1.0.0.zip
   ↓
2. Estrai in qualsiasi cartella
   (Desktop, Documenti, USB, ovunque!)
   ↓
3. Doppio click su FP-DMS.exe
   ↓
4. Prima volta: Setup Wizard
   - Crea database
   - Crea utente admin
   - Configura base
   ↓
5. Volte successive: Login diretto
   ↓
6. FATTO! ✅
```

### Screenshot Setup Wizard

```
┌──────────────────────────────────────┐
│ 🚀 Welcome to FP DMS                │
├──────────────────────────────────────┤
│                                      │
│ Step 1: Initialize Database          │
│ ┌──────────────────────────────────┐ │
│ │ [Initialize Database]             │ │
│ └──────────────────────────────────┘ │
│                                      │
│ Step 2: Create Admin User            │
│ Email:    [admin@example.com]        │
│ Password: [********]                 │
│ Name:     [Admin User]               │
│ ┌──────────────────────────────────┐ │
│ │ [Create Admin]                    │ │
│ └──────────────────────────────────┘ │
│                                      │
│ Step 3: Complete Setup               │
│ ┌──────────────────────────────────┐ │
│ │ [Go to Application]               │ │
│ └──────────────────────────────────┘ │
│                                      │
└──────────────────────────────────────┘
```

## 🔧 Personalizzazione Pre-Build

### Logo & Branding

```bash
# Sostituisci icon
build/portable/icon.ico          # Icona applicazione
build/portable/www/public/logo.png # Logo nel sito
```

### Credenziali Default

```php
// www/public/setup/defaults.php
<?php

return [
    'admin_email' => 'admin@yourcompany.com',
    'admin_password' => 'ChangeMe123!',
    'company_name' => 'Your Company Name',
    'app_name' => 'FP DMS - Your Company'
];
```

### Impostazioni Pre-configurate

```env
# www/.env
APP_NAME="Your Company - Marketing Suite"
APP_TIMEZONE=Europe/Rome
```

## 📊 Versioni Portable

### Versione 1: Base (Raccomandato)

```
Dimensione: ~50MB
Include:
- PHP Desktop
- SQLite database
- Applicazione completa
- Background scheduler

Ideale per:
- Demo clienti
- Uso personale
- Testing
- Distribuzioni USB
```

### Versione 2: Con MySQL Embedded

```
Dimensione: ~120MB
Include:
- Tutto della versione base
- MySQL Embedded
- phpMyAdmin

Ideale per:
- Produzioni piccole
- Migrazioni da server
- Backup completi
```

### Versione 3: Desktop App (Electron)

```
Dimensione: ~60MB
Include:
- UI nativa Electron
- PHP embedded
- SQLite database
- Auto-update

Ideale per:
- Utenti non tecnici
- Installazioni permanenti
- Aggiornamenti frequenti
```

## 🎨 Customizzazione UI

### Splash Screen

```html
<!-- www/public/splash.html -->
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: white;
            font-family: Arial;
        }
        .loader {
            text-align: center;
        }
        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loader">
        <div class="spinner"></div>
        <h2>FP Digital Marketing Suite</h2>
        <p>Loading application...</p>
    </div>
    <script>
        setTimeout(() => {
            window.location = '/';
        }, 2000);
    </script>
</body>
</html>
```

### Custom Theme

```css
/* www/public/assets/css/custom-theme.css */
:root {
    --primary-color: #667eea;
    --secondary-color: #764ba2;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --error-color: #ef4444;
}

/* Personalizza tutto il look */
```

## 🔐 Sicurezza Portable

### Auto-generate Keys

```php
// www/public/index.php - First run
<?php

$envFile = __DIR__ . '/../.env';
$env = file_get_contents($envFile);

// Generate APP_KEY if empty
if (strpos($env, 'APP_KEY=') !== false && strpos($env, 'APP_KEY=""') !== false) {
    $appKey = bin2hex(random_bytes(32));
    $env = str_replace('APP_KEY=""', 'APP_KEY="' . $appKey . '"', $env);
}

// Generate ENCRYPTION_KEY if empty  
if (strpos($env, 'ENCRYPTION_KEY=') !== false && strpos($env, 'ENCRYPTION_KEY=""') !== false) {
    $encKey = bin2hex(random_bytes(32));
    $env = str_replace('ENCRYPTION_KEY=""', 'ENCRYPTION_KEY="' . $encKey . '"', $env);
}

file_put_contents($envFile, $env);
```

### Restrict External Access

```php
// www/public/index.php
<?php

// Solo localhost in versione portable
if ($_ENV['APP_PORTABLE'] === 'true') {
    $allowed = ['127.0.0.1', '::1', 'localhost'];
    
    if (!in_array($_SERVER['REMOTE_ADDR'], $allowed)) {
        die('Access denied. Portable version only accepts local connections.');
    }
}
```

## 📚 Documentazione per Utente Finale

### Quick Start Card

```
╔══════════════════════════════════════════╗
║  FP DIGITAL MARKETING SUITE              ║
║  Quick Start Card                        ║
╠══════════════════════════════════════════╣
║                                          ║
║  1. Extract ZIP to any folder            ║
║  2. Double-click FP-DMS.exe              ║
║  3. Follow setup wizard                  ║
║  4. Start working!                       ║
║                                          ║
║  📧 Support: info@francescopasseri.com  ║
║  🌐 Web: francescopasseri.com           ║
║                                          ║
╚══════════════════════════════════════════╝
```

### FAQ.txt

```
FREQUENTLY ASKED QUESTIONS

Q: Do I need to install anything?
A: No! Just extract and run.

Q: Can I use it on multiple computers?
A: Yes! Copy the entire folder to any Windows PC.

Q: Can I use it from USB stick?
A: Yes! Works perfectly from USB.

Q: Do I need internet connection?
A: No! Works 100% offline.

Q: Can I backup my data?
A: Yes! Just copy the entire folder.

Q: How do I update?
A: Download new version, copy your storage/ folder to new version.

Q: Is it safe?
A: Yes! All data stored locally, no external connections.

Q: Can multiple users use it simultaneously?
A: No, this is single-user portable version.
   For multi-user, use server version.
```

## 🎯 Testing Checklist

Prima di distribuire, testa:

- [ ] Estrazione ZIP funziona
- [ ] FP-DMS.exe si avvia
- [ ] Setup wizard completa correttamente
- [ ] Login funziona
- [ ] Dashboard si carica
- [ ] Upload file funziona
- [ ] Report PDF si generano
- [ ] Email test inviate
- [ ] Scheduler background attivo
- [ ] Chiusura app e riapertura OK
- [ ] Funziona da USB stick
- [ ] Funziona su Windows pulito (senza PHP)
- [ ] Database persiste tra riavvii
- [ ] Log files vengono creati
- [ ] Nessun errore nei log

## 🚀 Distribuisci

### Hosting su GitHub

```bash
# Tag release
git tag v1.0.0-portable
git push --tags

# Upload a GitHub Releases
gh release create v1.0.0-portable \
  build/FP-DMS-Portable-v1.0.0.zip \
  --title "FP DMS Portable v1.0.0" \
  --notes "Portable Windows application"
```

### Download Link

```
Direct download:
https://github.com/username/fpdms/releases/download/v1.0.0-portable/FP-DMS-Portable-v1.0.0.zip
```

## 💡 Tips & Tricks

### Performance

```ini
; php/php.ini - Ottimizza per portable
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 20M
post_max_size = 25M
```

### Logging

```php
// Disabilita logging verbose in produzione
// www/.env
APP_DEBUG=false
LOG_LEVEL=error
```

### Startup Speed

```php
// www/public/index.php
// Preload classi comuni
opcache_compile_file('vendor/autoload.php');
```

## ✅ Pronto!

Ora hai tutto per creare un'applicazione portable .exe professionale!

**Prossimi passi:**
1. Esegui `build-portable.bat`
2. Testa su PC pulito
3. Distribuisci ZIP
4. Profit! 💰
