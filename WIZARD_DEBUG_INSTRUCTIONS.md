# 🔍 Istruzioni per Debuggare il Wizard

## Problema
Il wizard rimane in loading infinito allo step 2 e la console del browser non mostra niente.

## ✅ Fix Applicati

Ho applicato diverse modifiche per risolvere il problema:

### 1. Fix Caricamento Script
- Aggiunto controllo aggiuntivo per caricare gli script sulla pagina del wizard
- Aggiunto logging dell'hook corrente (visibile nei log PHP)

### 2. Script di Debug
Creato `wizard-debug.js` che viene caricato per primo e verifica:
- Se jQuery è caricato
- Se il container del wizard esiste
- Se i moduli ES6 sono supportati
- Quali script sono stati caricati
- Se le variabili globali esistono

### 3. Messaggi di Errore Migliorati
- Rimosso `wp_die()` che bloccava completamente la pagina
- Aggiunti messaggi di debug con le informazioni sull'URL corretto

## 🧪 Come Testare

### Step 1: Verificare l'URL
Il wizard richiede questi parametri nell'URL:
```
/wp-admin/admin.php?page=fpdms-connection-wizard&provider=ga4&client=1
```

Parametri obbligatori:
- `page=fpdms-connection-wizard`
- `provider=` uno tra: `ga4`, `gsc`, `google_ads`, `meta_ads`, `clarity`, `csv_generic`
- `client=` ID del cliente (numero intero > 0)

### Step 2: Aprire la Console del Browser
1. Apri la pagina del wizard
2. Premi **F12** per aprire gli strumenti sviluppatore
3. Vai alla tab **Console**

### Step 3: Verificare il Debug Output
Dovresti vedere questi messaggi nella console:

```
✅ wizard-debug.js caricato!
✅ jQuery caricato: 3.x.x
Wizard container trovato: 1 [...]
✅ Browser supporta ES6 modules
Scripts caricati: [...]
fpdmsWizard: {nonce: "...", redirectUrl: "..."}
fpdmsI18n: {...}
ajaxurl: "/wp-admin/admin-ajax.php"
```

### Step 4: Verificare Errori di Rete
1. Vai alla tab **Network** (Rete)
2. Ricarica la pagina
3. Cerca file con estensione `.js`
4. Verifica che NON ci siano file con status **404** (rosso)

### Step 5: Verificare Chiamate AJAX
1. Nella tab **Network**, filtra per "admin-ajax.php"
2. Quando provi ad avanzare allo step successivo, dovresti vedere una chiamata a:
   - Action: `fpdms_wizard_load_step`
   - Status: **200 OK** (verde)
3. Clicca sulla chiamata e verifica la risposta nella tab **Response**

## 🐛 Problemi Comuni e Soluzioni

### Problema: "wizard-debug.js caricato!" non appare
**Causa**: Gli script non si stanno caricando  
**Soluzione**: 
- Verifica che l'URL contenga `page=fpdms-connection-wizard`
- Controlla i log PHP per vedere quale hook viene chiamato
- Verifica che `ConnectionWizardIntegration::init()` sia stato chiamato

### Problema: "Wizard container trovato: 0"
**Causa**: Il PHP non sta renderizzando il wizard  
**Soluzione**:
- Verifica che i parametri `provider` e `client` siano nell'URL
- Controlla se appare un messaggio di errore nella pagina
- Verifica che il client ID sia valido nel database

### Problema: Scripts caricati mostra 404
**Causa**: Path degli script non corretto  
**Soluzione**:
- Verifica che i file esistano in `/wp-content/plugins/fp-digital-marketing-suite/assets/js/`
- Controlla i permessi dei file
- Verifica che il plugin sia attivato

### Problema: ES6 modules non supportati
**Causa**: Browser vecchio o configurazione server  
**Soluzione**:
- Aggiorna il browser (Chrome 61+, Firefox 60+, Safari 11+)
- Verifica che il server invii il MIME type corretto per i file `.js`

### Problema: AJAX call ritorna 403
**Causa**: Nonce non valido  
**Soluzione**:
- Verifica che `fpdmsWizard.nonce` sia presente nella console
- Ricarica la pagina per generare un nuovo nonce
- Verifica che l'utente abbia i permessi `manage_options`

### Problema: AJAX call ritorna 500
**Causa**: Errore PHP lato server  
**Soluzione**:
- Attiva il debug WordPress: `define('WP_DEBUG', true);` in `wp-config.php`
- Controlla il file di log: `wp-content/debug.log`
- Verifica che tutte le classi PHP siano caricate correttamente

## 📊 Log da Controllare

### Log PHP (se WP_DEBUG è attivo)
```
[timestamp] FPDMS Hook: admin_page_fpdms-connection-wizard
```

### Console Browser - Output Atteso
```javascript
// Step 1: Debug script caricato
✅ wizard-debug.js caricato!
✅ jQuery caricato: 3.7.1
Wizard container trovato: 1
✅ Browser supporta ES6 modules

// Step 2: Wizard inizializzato (se tutto OK)
// Non ci saranno log a meno che window.fpdmsDebug = true

// Step 3: Chiamata AJAX (quando clicchi "Continua")
AJAX Call: action=fpdms_wizard_load_step&nonce=...&provider=ga4&step=1
Response: {success: true, data: {html: "...", step: 1}}
Status: 200
```

## 🔧 Debug Avanzato

Per abilitare il debug completo, apri la console e digita:

```javascript
// Abilita debug mode
window.fpdmsDebug = true;

// Monitora tutti gli eventi AJAX
jQuery(document).ajaxSend(function(event, xhr, settings) {
    if (settings.url.includes('admin-ajax.php')) {
        console.log('🚀 AJAX Request:', settings.data);
    }
});

jQuery(document).ajaxComplete(function(event, xhr, settings) {
    if (settings.url.includes('admin-ajax.php')) {
        console.log('✅ AJAX Response:', xhr.responseJSON);
    }
});

jQuery(document).ajaxError(function(event, xhr, settings, error) {
    if (settings.url.includes('admin-ajax.php')) {
        console.error('❌ AJAX Error:', error, xhr.responseText);
    }
});
```

## 📞 Prossimi Passi

Se dopo aver verificato tutti questi punti il problema persiste:

1. Fornisci questi dettagli:
   - Output completo della console (screenshot)
   - URL completo che stai usando
   - Status delle chiamate AJAX nella tab Network
   - Eventuali errori nei log PHP

2. Verifica che il database contenga almeno un client valido

3. Prova con un provider diverso (es. `clarity` invece di `ga4`)

## 🎯 URL di Test Completo

Usa questo URL per testare (sostituisci `SITO` con il tuo dominio):

```
https://SITO/wp-admin/admin.php?page=fpdms-connection-wizard&provider=ga4&client=1
```

Provider disponibili:
- `ga4` - Google Analytics 4
- `gsc` - Google Search Console  
- `google_ads` - Google Ads
- `meta_ads` - Meta (Facebook) Ads
- `clarity` - Microsoft Clarity
- `csv_generic` - CSV Import Generico
