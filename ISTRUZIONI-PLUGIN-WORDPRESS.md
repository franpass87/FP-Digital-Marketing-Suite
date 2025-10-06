# 🎯 FP Digital Marketing Suite - Plugin WordPress

## ✅ SCELTA SAGGIA!

Hai scelto la soluzione più semplice e pratica: **Plugin WordPress**. È molto più facile da usare e distribuire!

## 🚀 COME USARE IL PLUGIN

### 📋 PREREQUISITI
- WordPress 6.4 o superiore
- PHP 8.1 o superiore
- Composer (per sviluppo)

### 🔧 INSTALLAZIONE

#### Metodo 1: Upload Manuale (Raccomandato)
1. **Comprimi il plugin**:
   ```bash
   # Esegui questo comando per creare il ZIP
   .\build.sh
   ```

2. **Upload su WordPress**:
   - Vai su **WordPress Admin → Plugin → Aggiungi nuovo**
   - Clicca **"Carica plugin"**
   - Seleziona il file ZIP generato
   - Clicca **"Installa ora"**
   - Clicca **"Attiva plugin"**

#### Metodo 2: Upload via FTP
1. **Estrai il plugin** in una cartella temporanea
2. **Rinomina** la cartella in `fp-digital-marketing-suite`
3. **Upload** via FTP nella cartella `/wp-content/plugins/`
4. **Attiva** il plugin da WordPress Admin

#### Metodo 3: Sviluppo Locale
1. **Installa dipendenze**:
   ```bash
   composer install
   ```

2. **Collega al WordPress**:
   ```bash
   # Crea collegamento simbolico o copia nella cartella plugins
   ln -s $(pwd) /path/to/wordpress/wp-content/plugins/fp-digital-marketing-suite
   ```

## 🎨 COME FUNZIONA

### 📱 Interfaccia Utente
- **Dashboard**: Overview completa del marketing
- **Connection Wizard**: Setup guidato dei connettori
- **Reports**: Generazione automatica report
- **Settings**: Configurazione avanzata

### 🔌 Connettori Disponibili
- ✅ **Google Analytics 4**
- ✅ **Google Search Console**
- ✅ **Meta Ads (Facebook)**
- ✅ **Google Ads**
- ✅ **Mailchimp**
- ✅ **Twilio** (notifiche SMS)
- ✅ **Email** (SMTP)

### 📊 Funzionalità
- ✅ **Report automatici** con PDF
- ✅ **Rilevamento anomalie** automatico
- ✅ **Notifiche multi-canale** (Email, SMS, Slack)
- ✅ **Dashboard personalizzabile**
- ✅ **Multi-client** support
- ✅ **Scheduler** automatico

## 🚀 QUICK START

### 1. Attiva il Plugin
Dopo l'installazione, vai su **WordPress Admin → Plugin** e attiva "FP Digital Marketing Suite".

### 2. Setup Iniziale
- Vai su **FP DMS → Connection Wizard**
- Segui la procedura guidata per configurare i connettori
- Aggiungi i tuoi clienti

### 3. Configurazione Automatica
- Il plugin crea automaticamente le tabelle del database
- Configura gli hook WordPress per il scheduler
- Imposta le notifiche

### 4. Primo Report
- Vai su **FP DMS → Reports**
- Clicca **"Generate Report"**
- Il plugin genererà automaticamente il primo report

## 🔧 CONFIGURAZIONE AVANZATA

### Database
Il plugin crea automaticamente le tabelle necessarie:
- `fp_dms_clients` - Gestione clienti
- `fp_dms_reports` - Report generati
- `fp_dms_notifications` - Log notifiche
- `fp_dms_anomalies` - Anomalie rilevate

### Cron Jobs
Il plugin usa il sistema cron di WordPress:
```php
// Scheduler automatico ogni ora
wp_schedule_event(time(), 'hourly', 'fp_dms_process_queue');

// Report automatici giornalieri
wp_schedule_event(time(), 'daily', 'fp_dms_generate_reports');
```

### Notifiche
Configura le notifiche in **FP DMS → Settings → Notifications**:
- Email SMTP
- SMS via Twilio
- Slack webhooks
- Webhook personalizzati

## 📦 DISTRIBUZIONE

### Per Sviluppatori
```bash
# Build del plugin
./build.sh

# Risultato: fp-digital-marketing-suite-YYYYMMDD.zip
```

### Per Clienti
1. **Invia** il file ZIP
2. **Istruzioni**: "Upload su WordPress → Plugin → Carica plugin"
3. **Attiva** il plugin
4. **Setup** tramite Connection Wizard

## 🎯 VANTAGGI DEL PLUGIN WORDPRESS

| Caratteristica | Plugin WordPress | Versione Standalone |
|---------------|------------------|---------------------|
| **Installazione** | ✅ Upload semplice | ❌ Complessa |
| **Manutenzione** | ✅ Aggiornamenti automatici | ❌ Manuale |
| **Sicurezza** | ✅ WordPress security | ❌ Gestione manuale |
| **Backup** | ✅ Integrato WordPress | ❌ Separato |
| **Multi-site** | ✅ Supporto nativo | ❌ Non supportato |
| **Permessi** | ✅ Sistema WordPress | ❌ Gestione manuale |
| **Cron** | ✅ WordPress cron | ❌ Sistema separato |
| **Database** | ✅ Integrato WordPress | ❌ Database separato |

## 🔧 SVILUPPO

### Struttura del Plugin
```
fp-digital-marketing-suite/
├── fp-digital-marketing-suite.php  # File principale
├── src/                            # Codice sorgente
│   ├── Admin/                      # Interfaccia admin
│   ├── App/                        # Logica applicazione
│   ├── Domain/                     # Entità di dominio
│   ├── Services/                   # Servizi business
│   └── Infra/                      # Infrastruttura
├── assets/                         # CSS/JS
├── tests/                          # Test unitari
└── vendor/                         # Dipendenze Composer
```

### Comandi CLI
```bash
# Test connettori
wp fp-dms test-connector google-analytics

# Genera report manuale
wp fp-dms generate-report --client=1

# Processa coda
wp fp-dms process-queue

# Setup database
wp fp-dms db:migrate
```

## 📞 SUPPORTO

### Documentazione
- **Codice**: Tutto documentato inline
- **API**: Documentazione completa
- **Esempi**: Test unitari come esempi

### Assistenza
- **Email**: info@francescopasseri.com
- **Web**: https://francescopasseri.com
- **GitHub**: Issues per bug report

## 🎉 RISULTATO FINALE

Con il plugin WordPress hai:
- ✅ **Installazione semplice** (upload ZIP)
- ✅ **Manutenzione automatica** (aggiornamenti WordPress)
- ✅ **Sicurezza integrata** (WordPress security)
- ✅ **Backup automatico** (WordPress backup)
- ✅ **Multi-site support** (WordPress multisite)
- ✅ **Permessi gestiti** (WordPress user system)
- ✅ **Cron integrato** (WordPress cron)
- ✅ **Database integrato** (WordPress database)

**Molto più semplice e professionale!** 🎉

## ✅ CHECKLIST FINALE

- [ ] Testare plugin su WordPress locale
- [ ] Verificare installazione via upload
- [ ] Testare Connection Wizard
- [ ] Verificare generazione report
- [ ] Testare notifiche
- [ ] Verificare scheduler automatico
- [ ] Creare package ZIP con build.sh
- [ ] Testare su WordPress di produzione
- [ ] **PLUGIN WORDPRESS PRONTO!** 🚀

**La scelta migliore per semplicità e funzionalità!** ✅
