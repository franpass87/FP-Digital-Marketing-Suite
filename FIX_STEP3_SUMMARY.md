# Fix: Step 3 del Wizard non Avanza

## Problema Identificato

Lo **step 3 del Connection Wizard** (GA4PropertyStep) riceveva una risposta 200 OK ma il wizard non procedeva allo step successivo.

### Causa Root

Il problema era nei **nomi dei campi HTML** in tutti gli step del wizard. I campi venivano creati con nomi semplici (es: `name="property_id"`) ma il codice PHP si aspettava una struttura dati annidata (es: `$data['config']['property_id']`).

Quando il JavaScript raccoglieva i dati dal form con `collectStepData()`, salvava i valori al livello root invece che nella struttura annidata corretta, causando la perdita dei dati quando si passava allo step successivo.

## Modifiche Apportate

### 1. Correzione Campi nei Step PHP

Corretto il nome dei campi in tutti gli step per usare la notazione array corretta:

#### `ServiceAccountStep.php`
- ❌ Prima: `name="service_account"`
- ✅ Dopo: `name="auth[service_account]"`

#### `GA4PropertyStep.php`
- ❌ Prima: `name="property_id"`
- ✅ Dopo: `name="config[property_id]"`

#### `GSCSiteStep.php`
- ❌ Prima: `name="site_url"`
- ✅ Dopo: `name="config[site_url]"`

#### `GoogleAdsCustomerStep.php`
- ❌ Prima: `name="customer_id"`
- ✅ Dopo: `name="config[customer_id]"`

#### `MetaAdsAuthStep.php`
- ❌ Prima: `name="access_token"`
- ✅ Dopo: `name="auth[access_token]"`
- ❌ Prima: `name="account_id"`
- ✅ Dopo: `name="config[account_id]"`

#### `ClarityProjectStep.php`
- ❌ Prima: `name="api_key"`
- ✅ Dopo: `name="auth[api_key]"`
- ❌ Prima: `name="project_id"`
- ✅ Dopo: `name="config[project_id]"`

#### `CSVConfigStep.php`
- ❌ Prima: `name="csv_path"`, `name="delimiter"`, ecc.
- ✅ Dopo: `name="config[csv_path]"`, `name="config[delimiter]"`, ecc.

### 2. Aggiunto Logging per Debug

Aggiunto logging condizionale (solo se `window.fpdmsDebug` è true) in:

- **`core.js`**:
  - Log dei dati raccolti in `nextStep()`
  - Log del risultato della validazione
  - Log dei dati aggiornati del wizard
  - Log durante il caricamento degli step

- **`steps.js`**:
  - Log dei parametri della richiesta AJAX
  - Log della risposta del server
  - Log degli errori

## Struttura Dati Corretta

Ora i dati del wizard vengono correttamente organizzati in questa struttura:

```javascript
{
  auth: {
    service_account: "...",  // Per provider Google
    access_token: "...",     // Per Meta Ads
    api_key: "..."           // Per Clarity
  },
  config: {
    property_id: "...",      // Per GA4
    site_url: "...",         // Per GSC
    customer_id: "...",      // Per Google Ads
    account_id: "...",       // Per Meta Ads
    project_id: "...",       // Per Clarity
    csv_path: "...",         // Per CSV
    delimiter: "...",        // Per CSV
    // ... altri campi config
  }
}
```

## Come Testare

1. Abilita il debug mode aggiungendo al tuo `wp-config.php`:
   ```php
   define('FPDMS_DEBUG', true);
   ```
   
2. Apri la console del browser (F12)

3. Avvia il wizard di connessione per GA4

4. Compila lo step 3 (GA4 Property ID)

5. Clicca "Continua"

6. Nella console dovresti vedere:
   ```
   Next step - collected data: {config: {property_id: "123456789"}}
   Validation result: {valid: true, errors: {}}
   Updated wizard data: {auth: {...}, config: {property_id: "123456789"}}
   Loading step: 4
   AJAX response: {success: true, data: {html: "...", step: 4}}
   ```

7. Il wizard dovrebbe ora avanzare correttamente allo step 4 (Test Connection)

## File Modificati

1. `src/Admin/ConnectionWizard/Steps/ServiceAccountStep.php`
2. `src/Admin/ConnectionWizard/Steps/GA4PropertyStep.php`
3. `src/Admin/ConnectionWizard/Steps/GSCSiteStep.php`
4. `src/Admin/ConnectionWizard/Steps/GoogleAdsCustomerStep.php`
5. `src/Admin/ConnectionWizard/Steps/MetaAdsAuthStep.php`
6. `src/Admin/ConnectionWizard/Steps/ClarityProjectStep.php`
7. `src/Admin/ConnectionWizard/Steps/CSVConfigStep.php`
8. `assets/js/modules/wizard/core.js`
9. `assets/js/modules/wizard/steps.js`

## Impatto

✅ **Benefici:**
- Il wizard ora avanza correttamente tra tutti gli step
- I dati vengono correttamente salvati e passati tra gli step
- Migliore debugging grazie ai log condizionali
- Nessun breaking change per l'utente finale

⚠️ **Note:**
- I dati salvati in precedenza con la vecchia struttura potrebbero non essere compatibili
- Se necessario, aggiungere una migrazione dati per sessioni wizard esistenti

## Prossimi Passi

1. ✅ Testare il wizard con tutti i provider (GA4, GSC, Google Ads, Meta Ads, Clarity, CSV)
2. ⏳ Verificare che il salvataggio finale funzioni correttamente
3. ⏳ Aggiungere test automatici per la struttura dati
4. ⏳ Documentare la struttura dati nel README
