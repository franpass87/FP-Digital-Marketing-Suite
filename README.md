# FP-Digital-Marketing-Suite

Sistema di reporting automatico per l'analisi delle performance di digital marketing con generazione di report HTML/PDF, dashboard amministrativa e scheduling automatico.

## 🚀 Funzionalità

### ✅ Report Automatici
- **Template HTML/PDF** con KPI standard (sessions, users, conversion rate, revenue)
- **Generazione PDF** con integrazione dompdf
- **Dati mock** per demo e testing
- **Preview HTML** in tempo reale

### ✅ Dashboard Amministrativa
- **Interfaccia web** per gestione report
- **Anteprima HTML** immediata
- **Bottone "Scarica PDF"** funzionante
- **Lista report generati** con download

### ✅ Scheduler Automatico
- **Cron job** per generazione periodica
- **Configurazione flessibile** (giornaliera, settimanale, mensile)
- **Log degli errori** automatico
- **Notifiche email** opzionali

## 📊 Screenshot

![Admin Interface](https://github.com/user-attachments/assets/31a852ed-a1d7-4939-ab6d-8348e3c4da34)

*Dashboard amministrativa con funzionalità di generazione e preview report*

## 🛠 Installazione

### Requisiti
- PHP >= 7.4
- Composer
- Estensioni: mbstring, dom, gd (per PDF)

### Setup
```bash
# 1. Clona il repository
git clone https://github.com/franpass87/FP-Digital-Marketing-Suite.git
cd FP-Digital-Marketing-Suite

# 2. Installa le dipendenze
composer install

# 3. Crea le directory necessarie
mkdir -p output

# 4. Imposta i permessi
chmod +x cron/generate_report.php
chmod 755 output/
```

## 🖥 Utilizzo

### Dashboard Web
```bash
# Avvia il server di sviluppo
php -S localhost:8000 -t admin/

# Apri nel browser
open http://localhost:8000/
```

### Generazione Report via CLI
```bash
# Test completo del sistema
php demo.php

# Generazione manuale
php cron/generate_report.php
```

### Scheduler Automatico
```bash
# Aggiungi al crontab per generazione mensile
crontab -e

# Aggiungi questa riga:
0 9 1 * * /usr/bin/php /path/to/project/cron/generate_report.php
```

Configurazioni scheduler disponibili:
- **Mensile**: `0 9 1 * *` - Primo giorno del mese alle 9:00
- **Settimanale**: `0 9 * * 1` - Ogni lunedì alle 9:00  
- **Giornaliero**: `0 9 * * *` - Ogni giorno alle 9:00
- **Ogni 6 ore**: `0 */6 * * *` - 4 volte al giorno

## 📁 Struttura del Progetto

```
/
├── composer.json              # Dipendenze PHP
├── demo.php                   # Script di test completo
├── src/
│   ├── ReportGenerator.php    # Classe principale per generazione report
│   ├── MockDataProvider.php   # Provider dati mock per demo
│   └── templates/
│       └── report_template.html # Template HTML responsive
├── admin/
│   └── index.php             # Dashboard amministrativa
├── cron/
│   └── generate_report.php   # Script per scheduler automatico
└── output/                   # Directory report generati
```

## 📊 KPI Inclusi nel Report

### Metriche Standard
- **Sessions**: Numero totale sessioni con variazione percentuale
- **Users**: Utenti unici con trend
- **Conversion Rate**: Tasso di conversione con analisi
- **Revenue**: Fatturato con crescita/decrescita

### Grafici e Analisi  
- **Traffic Trend**: Andamento traffico ultimi 7 giorni
- **Conversion Funnel**: Analisi del funnel di conversione
- **Revenue by Source**: Distribuzione ricavi per canale

## 🎯 Criteri di Accettazione Completati

- ✅ **Report HTML funzionante** - Template responsive con tutti i KPI
- ✅ **Bottone "Scarica PDF" in admin** - Generazione e download PDF operativo  
- ✅ **Scheduler operativo (cron)** - Script automatico configurabile
- ✅ **Demo report generabile con dati mock** - Sistema completo di test

## 🔧 Configurazione Avanzata

### Personalizzazione Template
Il template HTML è completamente personalizzabile in `src/templates/report_template.html` con supporto per:
- Variabili dinamiche `{{variable}}`
- Styling CSS responsive
- Grafici e chart interattivi

### Integrazione Email (Opzionale)
Uncommentare il codice nel file `cron/generate_report.php` per abilitare notifiche email automatiche.

### Log e Monitoraggio
- Log errori in `output/cron_errors.log`
- Tracking dimensioni file generati
- Cronologia report con timestamp

## 🚀 Valore per i Clienti

Questo sistema dimostra immediatamente valore attraverso:
- **Automazione completa** del reporting
- **Interface professionale** per gestione
- **Report PDF scaricabili** per presentazioni
- **Configurazione flessibile** per diverse esigenze
- **Dati visualizzati** in modo chiaro e professionale

Perfetto per agenzie di marketing digitale che vogliono automatizzare il reporting clienti e fornire report professionali su base regolare.