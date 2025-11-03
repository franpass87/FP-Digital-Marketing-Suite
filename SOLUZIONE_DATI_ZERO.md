# 🔧 Soluzione: Dati Sempre a 0

## 🚨 Problema Identificato

I dati rimangono sempre a 0 nonostante il collegamento degli asset. Questo è un problema comune che può avere diverse cause.

## 📋 Cause Principali

### 1. **Nessun Data Source Configurato**
- Non sono stati aggiunti data source al sistema
- I data source sono stati eliminati o disattivati

### 2. **Data Source Non Attivi**
- I data source sono configurati ma disattivati
- Mancano le credenziali di autenticazione

### 3. **Problemi di Autenticazione**
- Credenziali scadute o errate
- Token di accesso non validi
- Permessi insufficienti

### 4. **Problemi di Sincronizzazione**
- I dati non sono stati sincronizzati
- Le API esterne non restituiscono dati
- Filtri di data troppo restrittivi

### 5. **Problemi di Configurazione**
- Configurazione del provider errata
- ID o parametri mancanti
- Rate limiting delle API

## 🔧 Soluzione Passo-Passo

### Passo 1: Verifica Data Sources
1. **Vai alla pagina di amministrazione del plugin**
2. **Naviga alla sezione 'Data Sources' o 'Connettori'**
3. **Verifica che ci siano data source configurati**
4. **Se non ce ne sono, aggiungi almeno uno:**
   - Google Analytics 4 (GA4)
   - Google Search Console (GSC)
   - Google Ads
   - Meta Ads (Facebook/Instagram)

### Passo 2: Configurazione Credenziali

#### Google Analytics 4 (GA4):
- Service Account JSON (scarica da Google Cloud Console)
- Property ID (formato: 123456789)
- Verifica che il Service Account abbia accesso alla proprietà GA4

#### Google Search Console (GSC):
- Service Account JSON (stesso del GA4)
- Site URL (es: https://example.com)
- Verifica che il sito sia verificato in GSC

#### Google Ads:
- Developer Token
- Client ID
- Client Secret
- Refresh Token

#### Meta Ads:
- App ID
- App Secret
- Access Token
- Ad Account ID

### Passo 3: Test Connessioni
1. **Testa la connessione** usando il pulsante 'Test Connection'
2. **Verifica che il test sia positivo**
3. **Se il test fallisce, controlla:**
   - Credenziali corrette
   - Permessi sufficienti
   - Connessione internet
   - Rate limiting delle API

### Passo 4: Attivazione e Sincronizzazione
1. **Attiva tutti i data source** configurati
2. **Esegui una sincronizzazione manuale**
3. **Verifica che i dati vengano recuperati**
4. **Controlla i log per eventuali errori**

### Passo 5: Debug Avanzato
Se i dati rimangono ancora a 0, usa questi strumenti di debug:

#### Strumenti di Debug Disponibili:
- **Pagina Debug:** Vai alla pagina di debug del plugin
- **Test Provider:** Usa la funzione di test per ogni provider
- **Log Sistema:** Controlla i log per errori specifici
- **Sincronizzazione Manuale:** Forza una sincronizzazione

#### Comandi CLI (se disponibili):
```bash
# Test data sources
php cli.php debug:data-sources

# Sincronizzazione forzata
php cli.php sync:all

# Test provider specifico
php cli.php test:provider ga4
```

### Passo 6: Verifica Frontend
Se i data source funzionano ma i dati rimangono a 0 nel frontend:
1. **Verifica endpoint API:** Controlla che il frontend chiami l'endpoint corretto
2. **Controlla console browser:** Cerca errori JavaScript
3. **Verifica permessi utente:** Assicurati che l'utente abbia accesso ai dati
4. **Pulisci cache:** Svuota la cache del browser e del plugin

## 🚨 Problemi Comuni e Soluzioni

### Problema: 'Nessun data source configurato'
**Soluzione:** Aggiungi almeno un data source e configuralo correttamente.

### Problema: 'Data source disattivati'
**Soluzione:** Attiva tutti i data source configurati.

### Problema: 'Test di connessione fallito'
**Soluzione:** Verifica credenziali e permessi.

### Problema: 'Dati sincronizzati ma frontend mostra 0'
**Soluzione:** Controlla endpoint API e permessi frontend.

### Problema: 'Rate limiting API'
**Soluzione:** Aspetta e riprova, o controlla i limiti delle API.

## 📋 Checklist Finale

### Verifica Configurazione:
- [ ] Almeno un data source configurato
- [ ] Data source attivato
- [ ] Credenziali di autenticazione valide
- [ ] Test di connessione positivo
- [ ] Sincronizzazione eseguita
- [ ] Dati presenti nel summary
- [ ] Periodo di dati corretto
- [ ] Permessi API sufficienti

### Verifica Frontend:
- [ ] Endpoint API corretti
- [ ] Nessun errore JavaScript
- [ ] Permessi utente corretti
- [ ] Cache aggiornata

## 🛠️ Script di Debug Creati

Sono stati creati i seguenti script per aiutarti a diagnosticare e risolvere il problema:

1. **`debug-simple.php`** - Verifica base del sistema
2. **`check-data-sources-wp.php`** - Analisi dettagliata del problema
3. **`fix-data-issue.php`** - Guida completa di risoluzione
4. **`force-sync.php`** - Sincronizzazione forzata

## 💡 Suggerimenti Aggiuntivi

### Per prevenire il problema in futuro:
- **Sincronizzazione automatica:** Configura la sincronizzazione automatica
- **Monitoraggio:** Imposta alert per errori di sincronizzazione
- **Backup credenziali:** Mantieni un backup delle credenziali
- **Test regolari:** Esegui test di connessione regolarmente

### Strumenti di monitoraggio:
- Pagina di debug del plugin
- Log di sistema
- Dashboard di monitoraggio
- Alert email per errori

---

**Se il problema persiste dopo aver seguito tutti i passi, contatta il supporto tecnico con i dettagli della configurazione e i log degli errori.**
