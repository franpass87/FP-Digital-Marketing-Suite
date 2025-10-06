# 🎯 FP Digital Marketing Suite - Plugin WordPress

> **Plugin WordPress per automatizzare report di marketing, rilevamento anomalie e notifiche multi-canale.**

## 🚀 INSTALLAZIONE SEMPLICE

### 1. Download
```bash
# Crea il package del plugin
./build.sh

# Risultato: fp-digital-marketing-suite-YYYYMMDD.zip
```

### 2. Upload su WordPress
- **WordPress Admin → Plugin → Aggiungi nuovo**
- **"Carica plugin"** → Seleziona il ZIP
- **"Installa ora"** → **"Attiva plugin"**

### 3. Setup
- **FP DMS → Connection Wizard**
- Segui la procedura guidata
- Configura i tuoi connettori

## ✨ CARATTERISTICHE

### 📊 Dashboard Completa
- Overview marketing in tempo reale
- Grafici e statistiche automatiche
- Multi-client support

### 🔌 Connettori Integrati
- ✅ Google Analytics 4
- ✅ Google Search Console  
- ✅ Meta Ads (Facebook)
- ✅ Google Ads
- ✅ Mailchimp
- ✅ Twilio (SMS)
- ✅ Email SMTP

### 📈 Report Automatici
- Generazione PDF automatica
- Scheduling personalizzabile
- Template personalizzabili
- Multi-formato export

### 🚨 Rilevamento Anomalie
- Analisi automatica performance
- Alert intelligenti
- Notifiche multi-canale

### 📱 Notifiche Multi-Canale
- Email automatiche
- SMS via Twilio
- Slack integration
- Webhook personalizzati

## 🎨 INTERFACCIA UTENTE

### Admin WordPress
```
WordPress Admin
├── FP DMS
│   ├── Dashboard          # Overview principale
│   ├── Connection Wizard  # Setup guidato
│   ├── Clients           # Gestione clienti
│   ├── Reports           # Report e analytics
│   ├── Notifications     # Configurazione notifiche
│   └── Settings          # Impostazioni avanzate
```

### Connection Wizard
1. **Seleziona Connettore** (Google Analytics, Meta Ads, etc.)
2. **Autorizzazione** (OAuth automatico)
3. **Configurazione** (Account, proprietà, etc.)
4. **Test Connessione** (Verifica funzionamento)
5. **Completato** ✅

## 🔧 CONFIGURAZIONE

### Database
Il plugin crea automaticamente:
```sql
-- Tabelle create automaticamente
fp_dms_clients      -- Gestione clienti
fp_dms_reports      -- Report generati  
fp_dms_notifications -- Log notifiche
fp_dms_anomalies    -- Anomalie rilevate
fp_dms_connectors   -- Configurazione connettori
```

### Cron Jobs
```php
// Scheduler automatico WordPress
wp_schedule_event(time(), 'hourly', 'fp_dms_process_queue');
wp_schedule_event(time(), 'daily', 'fp_dms_generate_reports');
```

### Permessi
```php
// Ruoli WordPress
'manage_fp_dms' => 'Gestire FP Digital Marketing Suite'
```

## 📦 SVILUPPO

### Struttura
```
fp-digital-marketing-suite/
├── fp-digital-marketing-suite.php  # Plugin header
├── src/
│   ├── Admin/                      # Interfaccia WordPress
│   ├── App/                        # Logica applicazione
│   ├── Domain/                     # Entità business
│   ├── Services/                   # Servizi connettori
│   └── Infra/                      # Infrastruttura
├── assets/                         # CSS/JS frontend
├── tests/                          # Test unitari
└── vendor/                         # Composer dependencies
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

### Build
```bash
# Build completo
./build.sh

# Build con bump versione
./build.sh --bump=minor

# Build con versione specifica
./build.sh --set-version=1.2.0
```

## 🎯 CASI D'USO

### Agenzia Marketing
- **Multi-client dashboard** per tutti i clienti
- **Report automatici** settimanali/mensili
- **Alert anomalie** per performance
- **Notifiche clienti** automatiche

### Freelancer
- **Setup rapido** per nuovi clienti
- **Report professionali** automatici
- **Monitoraggio continuo** performance
- **Clienti soddisfatti** con aggiornamenti

### Azienda Interna
- **Dashboard centralizzata** marketing
- **Integrazione team** (Slack, Email)
- **Report executive** automatici
- **Analisi trend** e anomalie

## 🔒 SICUREZZA

### WordPress Integration
- ✅ **Nonce verification** per tutte le richieste
- ✅ **Capability checks** per permessi utente
- ✅ **Data sanitization** input utente
- ✅ **SQL injection protection** con prepared statements
- ✅ **XSS protection** output escaping

### Connettori
- ✅ **OAuth 2.0** per autorizzazioni sicure
- ✅ **Token encryption** per credenziali
- ✅ **Rate limiting** per API calls
- ✅ **Error handling** robusto

## 📞 SUPPORTO

### Requisiti
- **WordPress**: 6.4+
- **PHP**: 8.1+
- **MySQL**: 5.7+
- **Composer**: Per sviluppo

### Assistenza
- **Email**: info@francescopasseri.com
- **Web**: https://francescopasseri.com
- **Documentation**: Codice documentato inline

### Community
- **GitHub**: Issues e feature requests
- **WordPress.org**: Plugin directory (futuro)
- **Documentation**: Wiki completa

## 🎉 RISULTATO FINALE

**Plugin WordPress professionale** che:
- ✅ **Si installa** con un upload
- ✅ **Si configura** con wizard guidato  
- ✅ **Funziona** automaticamente
- ✅ **Genera report** professionali
- ✅ **Notifica** automaticamente
- ✅ **Scala** per multi-client
- ✅ **Si mantiene** con WordPress

**La soluzione più semplice e professionale per il marketing automation!** 🚀

---

## 📋 QUICK START CHECKLIST

- [ ] Download plugin ZIP (`./build.sh`)
- [ ] Upload su WordPress (Plugin → Carica plugin)
- [ ] Attiva plugin
- [ ] Vai su FP DMS → Connection Wizard
- [ ] Configura Google Analytics
- [ ] Configura Meta Ads
- [ ] Aggiungi primo cliente
- [ ] Genera primo report
- [ ] Configura notifiche
- [ ] **DONE!** ✅

**Pronto per automatizzare il tuo marketing!** 🎯
