# Frequently Asked Questions

## Do I need to expose the Overview dashboard publicly?
No. The plugin is designed for private WordPress installs. All dashboards are WordPress admin pages behind capability checks, and the REST namespace requires authenticated requests or shared QA keys.

## How do I configure the queue to run reliably?
Disable WP-Cron (`define('DISABLE_WP_CRON', true);`) and create a system cron that hits `wp-cron.php` or runs `wp cron event run --due-now` every five minutes. The plugin registers a custom `fpdms_5min` interval and processes jobs on the `fpdms_cron_tick` event.

## Where are reports and templates stored?
Templates live in the WordPress database while generated PDFs are written to the uploads directory by default. Retention policies configured in **FP Suite → Settings** control how long artifacts are kept before the `fpdms_retention_cleanup` event purges them.

## Can I add a custom data connector?
Yes. Implement `FP\\DMS\\Services\\Connectors\\DataSourceProviderInterface`, provide the necessary credential form fields, and register the provider via `ProviderFactory`. The admin UI will then expose it when creating or editing data sources.

## Posso evitare di incollare JSON delle credenziali GA4 o GSC?
Sì. Imposta costanti dedicate in `wp-config.php` (es. `define('FPDMS_GA4_SERVICE_ACCOUNT', '...');`) e selezionale dal menu a tendina "Origine credenziali" quando configuri la sorgente dati. Il plugin leggerà il JSON direttamente dalla costante senza salvarlo nel database.

## Come collego GA4 e Google Search Console senza confondermi con il JSON?
Per ogni connettore devi fornire due pezzi di informazione: **dove leggere le credenziali** e **quale proprietà collegare**. Puoi farlo caricando un file JSON oppure definendo una costante in `wp-config.php` che restituisce l'intero JSON (consigliato per evitare copie manuali).

### Passaggi per GA4
1. **Crea o riutilizza un service account** dal [Google Cloud Console](https://console.cloud.google.com/iam-admin/serviceaccounts). Scarica il file JSON e copiane il contenuto in una costante, ad esempio:
   ```php
   define('FPDMS_GA4_SERVICE_ACCOUNT', '...contenuto JSON...');
   ```
   In alternativa, conserva il file per caricarlo dall'interfaccia.
2. **Concedi accesso alla proprietà GA4** andando in *Amministratore → Accesso alla proprietà* e aggiungendo l'email del service account con ruolo `Editor` o superiore.
3. **Recupera l'ID della proprietà** da *Amministratore → Impostazioni proprietà*. È il numero richiesto dal campo "Property ID".
4. Nel plugin apri **FP Suite → Origini dati → Aggiungi origine → GA4** e imposta:
   - `Origine credenziali`: scegli "Costante" e inserisci `FPDMS_GA4_SERVICE_ACCOUNT`, oppure seleziona "Upload" e carica il file JSON.
   - `Property ID`: incolla il numero recuperato al passo precedente.
5. Salva e usa "Test connessione" per verificare che la proprietà risponda correttamente.

### Passaggi per Google Search Console
1. **Crea un service account** come per GA4 e definisci una costante (es. `FPDMS_GSC_SERVICE_ACCOUNT`) oppure prepara il file JSON da caricare.
2. **Aggiungi il service account come proprietario** nella Search Console: apri [search.google.com/search-console](https://search.google.com/search-console), scegli la proprietà e vai su *Impostazioni → Utenti e autorizzazioni → Aggiungi utente*, usando l'email del service account con ruolo `Proprietario completo`.
3. Nel plugin seleziona **FP Suite → Origini dati → Aggiungi origine → Google Search Console** e compila:
   - `Origine credenziali`: come sopra, specificando la costante `FPDMS_GSC_SERVICE_ACCOUNT` oppure caricando il JSON.
   - `Sito Search Console`: inserisci l'URL esatto o l'ID della proprietà (es. `sc-domain:example.com`).
4. Salva e premi "Test connessione" per assicurarti che il plugin riesca a leggere impression, clic e query.

### Posso usare altri metodi per fornire il JSON?
Oltre alle due opzioni principali (costante in `wp-config.php` o incolla/caricamento manuale), gli sviluppatori possono intercettare il JSON tramite i filtri WordPress ed estrarlo da vault, variabili d'ambiente o servizi esterni. I connettori espongono i seguenti hook:

```php
add_filter('fpdms/connector/ga4/service_account', function (string $json, array $auth, array $config): string {
    return $json !== '' ? $json : getenv('GA4_SERVICE_ACCOUNT_JSON');
});

add_filter('fpdms/connector/gsc/service_account', function (string $json, array $auth, array $config): string {
    return $json !== '' ? $json : Secrets\Store::fetch('gsc-service-account');
});
```

Il filtro riceve il valore salvato nell'interfaccia e può sostituirlo con qualsiasi stringa JSON. In questo modo puoi mantenere le chiavi fuori dal database (es. in AWS Secrets Manager) oppure ruotarle centralmente. Attenzione: al momento il plugin non offre un flusso OAuth interattivo, quindi l'autenticazione continua a basarsi su service account.

> Suggerimento: quando usi le costanti non serve mai incollare il JSON nell'interfaccia. Se devi aggiornare le credenziali basta sostituire il contenuto della costante in `wp-config.php` e tutte le origini dati continueranno a funzionare.

## Come funziona la libreria di preset per i template?
La pagina **Template** consente di scegliere un blueprint preconfigurato. Quando selezioni un preset, il modulo viene popolato automaticamente con nome, descrizione e markup HTML di partenza, così puoi personalizzare solo gli elementi necessari prima di salvare.

## How do anomaly notifications avoid alert fatigue?
Policies can define thresholds, mute windows, and cooldowns per channel. The notification router deduplicates alerts using transient locks and digest batching so clients only receive actionable updates.

## How can I trigger the QA automation from scripts?
Use the REST namespace with the `X-FPDMS-QA-KEY` header (or `qa_key` body parameter). For example: `POST /wp-json/fpdms/v1/qa/all` seeds data, runs reports, simulates anomalies, and returns status JSON in one call.

## Is localisation supported?
Yes. The text domain is `fp-dms` and translations are loaded from the `languages/` directory during `plugins_loaded`.

## What happens if email delivery fails?
Emails are dispatched through PHPMailer with retry and exponential backoff. Failures are logged and surfaced on the Logs page; you can configure SMTP credentials in **FP Suite → Settings** or hook `phpmailer_init` for custom mailers.
