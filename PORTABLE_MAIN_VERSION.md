# FP DMS Portable - Versione Principale per Agency/Freelancer

## 🎯 Il Tuo Caso d'Uso

**Scenario:** Gestisci marketing per più clienti e vuoi:
- ✅ Un'unica applicazione desktop
- ✅ Gestire tutti i clienti da una sola interfaccia
- ✅ Nessuna installazione server
- ✅ Lavorare offline quando serve
- ✅ Portare tutto su USB/laptop
- ✅ Demo rapide per potenziali clienti

**Soluzione:** FP DMS Portable - Applicazione desktop Windows standalone

## 🚀 Setup Rapido (First Time)

### Passo 1: Scarica PHP Desktop

```powershell
# Vai su: https://github.com/cztomczak/phpdesktop/releases
# Scarica: phpdesktop-chrome-57.0-msvc-php-7.4.zip
# Salva in: C:\Temp\
```

### Passo 2: Build Automatico

```batch
REM Esegui build script
build-portable.bat

REM Segui wizard (3-5 minuti)
REM Output: build/FP-DMS-Portable-v1.0.0.zip
```

### Passo 3: Setup Personale

```batch
REM Estrai ZIP dove vuoi lavorare
unzip FP-DMS-Portable-v1.0.0.zip -d "C:\FP-DMS"

REM Doppio click
C:\FP-DMS\FP-DMS.exe

REM First run: Setup wizard
1. Crea admin user (il TUO account)
2. Configura email SMTP (per invii)
3. FATTO!
```

## 💼 Ottimizzazioni per Agency

### Multi-Client Management

**Struttura Database Ottimizzata:**

```sql
-- Già pronto nel portable!
CREATE TABLE clients (
    id INTEGER PRIMARY KEY,
    name VARCHAR(190),           -- Nome cliente
    company VARCHAR(190),         -- Azienda
    email_to TEXT,               -- Email report
    logo_url TEXT,               -- Logo cliente
    -- Billing info
    contract_start DATE,
    contract_end DATE,
    monthly_fee DECIMAL(10,2),
    -- Custom branding
    primary_color VARCHAR(7),    -- #667eea
    secondary_color VARCHAR(7),  -- #764ba2
    -- Stats
    total_reports INT DEFAULT 0,
    last_report_at DATETIME,
    -- Meta
    notes TEXT,
    created_at DATETIME,
    updated_at DATETIME
);
```

### Dashboard Agency-Focused

**Aggiungi al setup wizard:**

```php
// www/setup/agency-config.php
<?php

// Configurazione agency
$agencyConfig = [
    'agency_name' => 'Il Tuo Nome/Agenzia',
    'agency_email' => 'tuo@email.com',
    'agency_phone' => '+39 xxx xxx xxxx',
    'agency_logo' => 'path/to/logo.png',
    'agency_website' => 'https://tuosito.com',
    
    // Report branding
    'report_footer' => 'Powered by Il Tuo Nome',
    'report_disclaimer' => 'Report confidenziale per uso interno',
    
    // Notifications
    'send_copy_to_agency' => true,  // Copia email a te
    'agency_cc_email' => 'tuo@email.com',
    
    // Billing
    'default_currency' => 'EUR',
    'invoice_enabled' => false,     // Per fatturazione integrata (futuro)
];

// Salva config
Config::set('agency_settings', $agencyConfig);
```

### Client Quick Add

**Wizard rapido per aggiungere clienti:**

```php
// www/public/clients/quick-add.php
<!DOCTYPE html>
<html>
<head>
    <title>Aggiungi Cliente Rapido</title>
    <style>
        body { font-family: Arial; max-width: 600px; margin: 50px auto; }
        .form-group { margin: 15px 0; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input, select { width: 100%; padding: 8px; font-size: 14px; }
        button { background: #4CAF50; color: white; padding: 10px 30px; border: none; cursor: pointer; }
        .templates { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 10px 0; }
        .template-card { border: 2px solid #ddd; padding: 10px; cursor: pointer; }
        .template-card.selected { border-color: #4CAF50; background: #f0f8f0; }
    </style>
</head>
<body>
    <h1>🚀 Aggiungi Nuovo Cliente</h1>
    
    <form id="quick-add-form" method="POST" action="/api/clients/quick-add">
        <!-- Basic Info -->
        <div class="form-group">
            <label>Nome Cliente *</label>
            <input type="text" name="name" required placeholder="es. Mario Rossi">
        </div>
        
        <div class="form-group">
            <label>Azienda</label>
            <input type="text" name="company" placeholder="es. Rossi S.r.l.">
        </div>
        
        <div class="form-group">
            <label>Email (per invio report) *</label>
            <input type="email" name="email" required placeholder="cliente@azienda.it">
        </div>
        
        <div class="form-group">
            <label>Telefono</label>
            <input type="tel" name="phone" placeholder="+39 333 123 4567">
        </div>
        
        <!-- Quick Setup Templates -->
        <h3>📋 Template Setup Rapido</h3>
        <div class="templates">
            <div class="template-card" onclick="selectTemplate('basic')">
                <h4>🌟 Base</h4>
                <p>• GA4<br>• Report mensili<br>• Email notifiche</p>
            </div>
            
            <div class="template-card" onclick="selectTemplate('complete')">
                <h4>⭐ Completo</h4>
                <p>• GA4 + GSC + Ads<br>• Report settimanali<br>• Anomaly detection</p>
            </div>
            
            <div class="template-card" onclick="selectTemplate('ecommerce')">
                <h4>🛒 E-commerce</h4>
                <p>• GA4 + Ads + Meta<br>• Report giornalieri<br>• Conversioni tracking</p>
            </div>
            
            <div class="template-card" onclick="selectTemplate('custom')">
                <h4>⚙️ Personalizzato</h4>
                <p>Setup manuale dopo creazione</p>
            </div>
        </div>
        <input type="hidden" name="template" id="template" value="basic">
        
        <!-- Data Sources Quick Config -->
        <div id="datasources-config" style="display:none;">
            <h3>🔌 Data Sources</h3>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="enable_ga4" checked>
                    Google Analytics 4
                </label>
                <input type="text" name="ga4_property_id" placeholder="GA4 Property ID" style="margin-top:5px;">
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="enable_gsc">
                    Google Search Console
                </label>
                <input type="text" name="gsc_site_url" placeholder="https://example.com" style="margin-top:5px;">
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="enable_ads">
                    Google Ads
                </label>
                <input type="text" name="ads_customer_id" placeholder="123-456-7890" style="margin-top:5px;">
            </div>
        </div>
        
        <!-- Reporting -->
        <h3>📊 Reporting</h3>
        <div class="form-group">
            <label>Frequenza Report</label>
            <select name="report_frequency">
                <option value="daily">Giornaliero</option>
                <option value="weekly">Settimanale</option>
                <option value="monthly" selected>Mensile</option>
                <option value="none">Manuale</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Giorno Invio (se settimanale/mensile)</label>
            <select name="report_day">
                <option value="monday">Lunedì</option>
                <option value="1">1° del mese</option>
                <option value="15">15 del mese</option>
            </select>
        </div>
        
        <!-- Contract -->
        <h3>💼 Contratto (opzionale)</h3>
        <div class="form-group">
            <label>Inizio Contratto</label>
            <input type="date" name="contract_start" value="<?= date('Y-m-d') ?>">
        </div>
        
        <div class="form-group">
            <label>Fee Mensile (€)</label>
            <input type="number" name="monthly_fee" step="0.01" placeholder="500.00">
        </div>
        
        <button type="submit">✅ Crea Cliente</button>
        <a href="/clients" style="margin-left:10px;">Annulla</a>
    </form>
    
    <script>
        function selectTemplate(template) {
            // Deselect all
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Select clicked
            event.target.closest('.template-card').classList.add('selected');
            document.getElementById('template').value = template;
            
            // Show/hide datasources based on template
            const dsConfig = document.getElementById('datasources-config');
            if (template === 'custom') {
                dsConfig.style.display = 'block';
            } else {
                dsConfig.style.display = 'none';
            }
        }
        
        // Auto-fill form on template change
        document.getElementById('quick-add-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            const response = await fetch('/api/clients/quick-add', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('✅ Cliente creato con successo!');
                window.location = '/clients/' + result.client_id;
            } else {
                alert('❌ Errore: ' + result.error);
            }
        });
    </script>
</body>
</html>
```

### Dashboard Agency-Optimized

**Homepage per agency:**

```php
// www/public/index.php - Redirect to agency dashboard
<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Redirect to agency dashboard
header('Location: /agency/dashboard');
exit;
```

```php
// www/public/agency/dashboard.php
<?php

require __DIR__ . '/../../bootstrap.php';

$totalClients = ClientsRepo::count();
$activeClients = ClientsRepo::countActive();
$reportsThisMonth = ReportsRepo::countThisMonth();
$revenueThisMonth = ClientsRepo::sumMonthlyFees();

?>
<!DOCTYPE html>
<html>
<head>
    <title>FP DMS - Agency Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 24px; }
        .header .user { font-size: 14px; }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 30px; }
        
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        .btn {
            background: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary { background: #667eea; color: white; }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .stat-card .label {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .stat-card .value {
            font-size: 36px;
            font-weight: bold;
            color: #333;
        }
        .stat-card .trend {
            font-size: 12px;
            color: #10b981;
            margin-top: 5px;
        }
        
        .clients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .client-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .client-card:hover { transform: translateY(-4px); }
        .client-card .name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .client-card .company {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .client-card .stats {
            display: flex;
            gap: 20px;
            font-size: 12px;
            color: #666;
        }
        .client-card .actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .client-card .actions button {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .status-active { color: #10b981; }
        .status-warning { color: #f59e0b; }
        .status-inactive { color: #ef4444; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚀 FP Digital Marketing Suite</h1>
        <div class="user">
            👤 <?= $_SESSION['user_name'] ?? 'Admin' ?>
            <a href="/logout" style="color:white;margin-left:10px;">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="/clients/quick-add" class="btn btn-primary">➕ Nuovo Cliente</a>
            <a href="/reports/generate" class="btn">📊 Genera Report</a>
            <a href="/scheduler/run" class="btn">▶️ Esegui Tasks</a>
            <a href="/settings" class="btn">⚙️ Impostazioni</a>
        </div>
        
        <!-- Stats Overview -->
        <div class="stats">
            <div class="stat-card">
                <div class="label">Clienti Totali</div>
                <div class="value"><?= $totalClients ?></div>
                <div class="trend">↗ +2 questo mese</div>
            </div>
            
            <div class="stat-card">
                <div class="label">Clienti Attivi</div>
                <div class="value"><?= $activeClients ?></div>
                <div class="trend">✅ Tutti aggiornati</div>
            </div>
            
            <div class="stat-card">
                <div class="label">Report Questo Mese</div>
                <div class="value"><?= $reportsThisMonth ?></div>
                <div class="trend">📈 +15% vs mese scorso</div>
            </div>
            
            <div class="stat-card">
                <div class="label">Revenue Mensile</div>
                <div class="value">€<?= number_format($revenueThisMonth, 0) ?></div>
                <div class="trend">💰 MRR</div>
            </div>
        </div>
        
        <!-- Recent Clients -->
        <h2 style="margin-bottom:20px;">I Tuoi Clienti</h2>
        <div class="clients-grid">
            <?php foreach (ClientsRepo::getRecent(20) as $client): ?>
            <div class="client-card" onclick="window.location='/clients/<?= $client->id ?>'">
                <div class="name"><?= htmlspecialchars($client->name) ?></div>
                <div class="company"><?= htmlspecialchars($client->company ?? '') ?></div>
                
                <div class="stats">
                    <div>📊 <?= $client->total_reports ?? 0 ?> report</div>
                    <div>📅 Ultimo: <?= date('d/m/Y', strtotime($client->last_report_at ?? 'now')) ?></div>
                </div>
                
                <div class="actions">
                    <button onclick="event.stopPropagation(); generateReport(<?= $client->id ?>)">
                        📊 Report
                    </button>
                    <button onclick="event.stopPropagation(); viewAnalytics(<?= $client->id ?>)">
                        📈 Analytics
                    </button>
                    <button onclick="event.stopPropagation(); sendEmail(<?= $client->id ?>)">
                        📧 Email
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        function generateReport(clientId) {
            if (confirm('Generare report per questo cliente?')) {
                window.location = '/reports/generate?client=' + clientId;
            }
        }
        
        function viewAnalytics(clientId) {
            window.location = '/analytics?client=' + clientId;
        }
        
        function sendEmail(clientId) {
            window.location = '/email/compose?to=' + clientId;
        }
    </script>
</body>
</html>
```

## 🎨 Personalizzazione Agency

### Logo & Branding

```batch
REM Aggiungi il TUO logo
copy "C:\Il-Tuo-Logo.png" "build\portable\www\public\assets\images\agency-logo.png"

REM Sostituisci icona app
copy "C:\Il-Tuo-Icon.ico" "build\portable\icon.ico"
```

### Colori Brand

```css
/* www/public/assets/css/agency-theme.css */
:root {
    /* I TUOI colori */
    --agency-primary: #667eea;
    --agency-secondary: #764ba2;
    --agency-accent: #10b981;
    
    /* Dark mode (opzionale) */
    --agency-bg-dark: #1a1a1a;
    --agency-text-dark: #ffffff;
}
```

### Email Templates Branded

```php
// www/templates/email/report-client.php
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { 
            background: linear-gradient(135deg, <?= $agencyColor1 ?>, <?= $agencyColor2 ?>);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="<?= $agencyLogo ?>" alt="Logo" height="50">
        <h1>Report Mensile - <?= $clientName ?></h1>
    </div>
    
    <div class="content" style="padding: 30px;">
        <!-- Report content -->
    </div>
    
    <div class="footer">
        <p><strong><?= $agencyName ?></strong></p>
        <p><?= $agencyEmail ?> | <?= $agencyPhone ?></p>
        <p><?= $agencyWebsite ?></p>
    </div>
</body>
</html>
```

## 🔧 Features Specifiche per Agency

### 1. Bulk Operations

```php
// Azioni multiple sui clienti
- Genera report per tutti i clienti
- Invia email batch
- Esporta tutti i dati
- Backup completo
```

### 2. Client Templates

```php
// Template pre-configurati per tipologie cliente
- E-commerce (GA4 + Ads + Meta)
- Lead Generation (GSC + Ads)
- Brand Awareness (GA4 + Social)
- Local Business (GMB + GSC)
```

### 3. White Label Reports

```php
// PDF completamente brandizzati
- Il TUO logo
- I TUOI colori
- Il TUO footer
- Nessun riferimento FP DMS
```

### 4. Client Portal (Opzionale)

```php
// Mini portale per cliente
- URL univoco: fpdms.local/portal/ABC123
- Login cliente
- Download report storici
- View analytics real-time
```

## 📦 Distribuzione Multi-PC

### USB Stick Setup

```
USB:\
├── FP-DMS\
│   ├── FP-DMS.exe
│   ├── storage\
│   │   └── database.sqlite    # TUTTI i tuoi clienti qui!
│   └── ...
└── README.txt
```

**Usa su qualsiasi PC:**
1. Inserisci USB
2. Doppio click `FP-DMS.exe`
3. Lavora
4. Chiudi
5. Rimuovi USB
6. Dati al sicuro!

### Sync Multi-Device

```batch
REM Sincronizza tra PC tramite cloud
robocopy "C:\FP-DMS\storage" "C:\Dropbox\FP-DMS-Backup\storage" /MIR

REM O usa OneDrive
mklink /D "C:\FP-DMS\storage" "C:\Users\You\OneDrive\FP-DMS\storage"
```

## 🔐 Backup Automatico

```php
// www/storage/backup-script.php
<?php

// Backup automatico database
$date = date('Y-m-d_H-i-s');
$source = __DIR__ . '/database.sqlite';
$dest = __DIR__ . '/backups/database_' . $date . '.sqlite';

if (!is_dir(__DIR__ . '/backups')) {
    mkdir(__DIR__ . '/backups', 0755, true);
}

copy($source, $dest);

// Mantieni solo ultimi 30 backup
$backups = glob(__DIR__ . '/backups/*.sqlite');
if (count($backups) > 30) {
    usort($backups, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    $toDelete = array_slice($backups, 0, count($backups) - 30);
    foreach ($toDelete as $file) {
        unlink($file);
    }
}

echo "Backup completato: $dest\n";
```

## 📊 Analytics & Reporting

### Dashboard Metrics

```php
// Metriche agency nel tempo
- Numero clienti: grafico crescita
- Report generati: trend mensile
- Revenue tracking: MRR/ARR
- Client churn rate
- Average report delivery time
```

### Export per Fatturazione

```php
// Esporta dati per fatturazione
CSV export:
- Cliente
- Periodo
- Servizi erogati
- Fee
- Report count
- Ore stimate

Integrazione:
- Fatture in Cloud (API)
- Aruba Fatturazione Elettronica
- Excel/CSV generico
```

## 🎯 Workflow Tipo

```
1. LUNEDÌ MATTINA
   - Apri FP-DMS.exe
   - Check dashboard agency
   - Vedi nuove anomalie
   
2. GESTIONE CLIENTI
   - Aggiungi nuovo cliente (Quick Add)
   - Configura data sources
   - Setup report automatici
   
3. GENERAZIONE REPORT
   - Scheduler automatico (o manuale)
   - PDF branded generati
   - Email inviate ai clienti
   - Copia a te per verifica
   
4. FINE GIORNATA
   - Check log
   - Backup automatico
   - Chiudi app
   - Dati al sicuro su USB/Cloud
```

## ✅ Checklist Setup Completo

- [ ] Build portable completato
- [ ] Logo agency aggiunto
- [ ] Colori brand configurati
- [ ] Email SMTP configurato
- [ ] Template report personalizzato
- [ ] Primo cliente aggiunto
- [ ] Test report generato
- [ ] Test email inviata
- [ ] Backup configurato
- [ ] App funziona da USB
- [ ] Dashboard agency OK
- [ ] Tutto brandizzato

## 🚀 Prossimi Passi

1. **Setup Iniziale**
   ```bash
   build-portable.bat
   # Segui wizard
   ```

2. **Personalizzazione**
   - Aggiungi logo
   - Configura colori
   - Setup email

3. **Primo Cliente**
   - Quick Add
   - Test report
   - Verifica email

4. **Go Live!**
   - Usa con tutti i clienti
   - Portable sempre con te

Vuoi che creo il build script ottimizzato per agency?
