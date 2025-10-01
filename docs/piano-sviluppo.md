# Piano di sviluppo FP Digital Marketing Suite

Questo documento riepiloga gli interventi suggeriti per evolvere il plugin, suddivisi per priorità e backlog futuro. Ogni intervento include uno stub operativo con i passi chiave da eseguire.

## Sintesi delle decisioni recenti

- **Distribuzione automatica dei report via email** – elevata a priorità per velocizzare l’invio dei PDF subito dopo la generazione (proposta 1).
- **Analisi statica nel workflow GitHub** – confermata come attività prioritaria per rafforzare la qualità del codice lato CI (proposta 5).
- **Dashboard di performance dei connettori** – inserita tra gli interventi prioritari per identificare rapidamente i colli di bottiglia (proposta 7).
- **Pianificazioni personalizzate dei job** – promossa a priorità per adeguare gli orari di esecuzione alle esigenze operative (proposta 9).
- **Commenti editoriali e approvazione dei report** – accolta come priorità per arricchire i deliverable con insight e firme (proposta 10).
- **Backup e ripristino delle configurazioni** – ritenuto superfluo nell’immediato, sarà rivalutato in caso di espansione dell’uso.
- **Health monitor della coda e degli scheduler** – giudicato non necessario allo stato attuale.
- **Dataset sintetici per QA interno** – esclusi per ora, l’utente preferisce testare su dati reali.
- **Versioning e anteprima in tempo reale per i template** – non richiesto nella fase corrente.
- **Ruoli dedicati per il marketing** – evitati perché il plugin sarà gestito da un’unica persona.

## Interventi prioritari

1. **Distribuzione automatica dei report via email** – Automatizza l’invio dei PDF appena generati verso liste di destinatari configurabili, con log dell’esito e opzioni di retry.

:::task-stub{title="Automatizzare la distribuzione email dei report"}
1. Crea `src/Services/Reports/EmailDispatcher.php` che prenda in input l’ID job, recuperi il PDF da `ReportsRepo` e spedisca via `wp_mail()` o `PHPMailer`, restituendo stato e dettagli errore.
2. Aggiungi impostazioni in `Infra/Options` (destinatari predefiniti, oggetto, corpo template, ritardi) e un toggle per attivare l’invio automatico post-`Queue::process()`.
3. Aggiorna `Queue::process()` per invocare il dispatcher dopo la generazione dei report; logga gli esiti su una nuova tabella `report_deliveries` gestita da `Domain\Repos\ReportsRepo`.
4. Estendi l’admin UI (es. `Admin/Pages/ReportsPage.php`) con cronologia invii e azione “Reinvia”; aggiungi test di integrazione in `tests/Services/Reports/EmailDispatcherTest.php`.
:::

2. **Validazione dei payload REST con JSON Schema** – Gli endpoint REST eseguono sanitizzazioni manuali e controlli ad hoc, ma non applicano una validazione strutturale coerente sui payload ricevuti.

:::task-stub{title="Aggiungere la validazione JSON Schema agli endpoint REST"}
1. Crea una cartella (es. `src/Http/Schemas/`) con i file schema JSON che descrivono i payload attesi per `/run`, `/anomalies/*`, `/qa/*` e gli altri endpoint in `src/Http/Routes.php`.
2. Aggiungi una dipendenza per la validazione (es. `justinrainbow/json-schema`) in `composer.json` e registra un helper (es. `SchemaValidator`) in `src/Http/` che carichi gli schemi e verifichi i payload.
3. In ogni handler di `Routes` e `OverviewRoutes` con corpo JSON, invoca il validatore prima di procedere con la logica attuale; restituisci `WP_Error` con messaggi chiari quando la validazione fallisce.
4. Estendi i test d’integrazione (cartella `tests/`) per coprire payload validi/invalidi e aggiorna la documentazione API in `docs/` spiegando i nuovi vincoli.
:::

3. **Unificare la gestione delle admin notice** – La pagina “Data Sources” mantiene un flusso PRG manuale con `set_transient`, invece di sfruttare l’helper centralizzato già presente per i template, i client e le schedule.

:::task-stub{title="Riutilizzare NoticeStore in DataSourcesPage"}
1. Importa `FP\DMS\Admin\Support\NoticeStore` in `src/Admin/Pages/DataSourcesPage.php` e sostituisci le chiamate dirette a `add_settings_error` con `NoticeStore::enqueue(...)`.
2. Rimuovi la logica custom di `storeAndRedirect`/`bootNotices` basata su transient e richiama `NoticeStore::flash('fpdms_datasources')` all’inizio del `render()`.
3. Verifica che gli stati di successo/errore siano preservati dopo i redirect (crea/aggiorna, test connessione, cancellazione) e aggiorna eventuali riferimenti CSS/HTML se necessario.
4. Aggiorna la documentazione interna (es. `docs/nuove-migliorie.md`) per segnalare che tutte le pagine admin condividono ora la stessa utility di notice.
:::

4. **Cache dei PDF ricorrenti** – Ogni generazione di report crea sempre un nuovo PDF anche quando i dati e il template non sono cambiati, aumentando il carico della coda.

:::task-stub{title="Implementare una cache per i PDF invariati"}
1. Introduci un servizio (es. `src/Services/Reports/ReportCache.php`) che calcoli un hash dei dati aggregati e del template usati da `ReportBuilder::generate()`.
2. Prima di renderizzare il PDF, confronta l’hash con l’ultimo job riuscito per lo stesso client/periodo (via `ReportsRepo`) e, se identico, riutilizza il file precedente popolando `storage_path` e metadati senza invocare `PdfRenderer`.
3. Aggiorna `Queue::process()` per invalidare la cache quando cambiano i data source rilevanti (nuovo upload CSV, provider che fallisce) o quando viene richiesto un override esplicito.
4. Aggiungi logica di housekeeping per eliminare versioni obsolete e documenta il comportamento (es. in README) specificando come forzare una rigenerazione completa.
:::

5. **Analisi statica nel workflow GitHub** – Le pipeline CI eseguono solo il linting sintattico (`php -l`), senza controlli di tipo/funzionamento tipici di PHPStan o Psalm.

:::task-stub{title="Integrare PHPStan/Psalm nella pipeline"}
1. Aggiungi una dipendenza di sviluppo (`phpstan/phpstan` o `vimeo/psalm`) in `composer.json` e crea la relativa configurazione (es. `phpstan.neon` sotto `tools/`).
2. Definisci uno script composer (`composer phpstan`) che lanci l’analisi sulla cartella `src/` e, se serve, sulla directory `tests/`.
3. Aggiorna `.github/workflows/build-zip.yml` (e `build-plugin-zip.yml` se usato) inserendo uno step dedicato dopo il setup di PHP che esegua lo script di analisi statica.
4. Documenta nei contributi (README o `docs/`) come installare/rieseguire localmente l’analisi e risolvere eventuali warning.
:::

6. **Avvisi di scadenza per le policy delle anomalie** – Notifiche programmate che ricordano di rivedere soglie e strategie ogni N settimane.

:::task-stub{title="Programmando avvisi di revisione anomalie"}
1. Aggiungi opzioni in `Infra/Options` per configurare frequenza e destinatari degli avvisi.
2. Pianifica un evento cron (via `wp_schedule_event`) che esegua un nuovo servizio `AnomaliesReviewScheduler` e invii email/notifiche tramite `NotificationRouter`.
3. Inserisci un pannello riepilogativo in `Admin/Pages/AnomaliesPage.php` con l’ultima revisione effettuata.
4. Copri il comportamento con test unitari per garantire che gli avvisi non vengano inviati se una revisione è stata eseguita di recente.
:::

7. **Dashboard di performance dei connettori** – Un cruscotto minimale con tempi medi di import e volumi elaborati per individuare colli di bottiglia.

:::task-stub{title="Tracciare performance dei connettori"}
1. Estendi `Services\Connectors\QueueJobs` (o componente equivalente) per loggare durata e numero di record per job.
2. Persiste le metriche in una tabella `connector_performance` gestita da `Infra\DB` e fornisci metodi di query in `Domain\Repos`.
3. Crea una sezione in `Admin/Pages/OverviewPage.php` con grafici a barre/linea usando la libreria JS già caricata nel backend.
4. Prevedi esportazione CSV delle metriche e test di integrazione che validino il calcolo delle medie.
:::

8. **Automazione del ricalcolo per periodi retrospettivi** – Workflow per rilanciare report e anomalie su intervalli storici dopo correzioni ai dati.

:::task-stub{title="Workflow di backfill automatizzato"}
1. Implementa in `Queue` un tipo di job `backfill` che accetti range di date e clienti selezionati.
2. Aggiungi una UI dedicata in `Admin/Pages/ReportsPage.php` per configurare il backfill, con progress tracking tramite AJAX.
3. Riutilizza `ReportBuilder` e `Anomalies\Detector` per rieseguire le elaborazioni in modalità batch, salvando un audit in tabella `backfill_runs`.
4. Introduci test funzionali che simulino un backfill su un database ridotto e verifichino la rigenerazione dei PDF e delle notifiche.
:::

9. **Pianificazioni personalizzate dei job** – Permette di scegliere giorno, orario e più finestre giornaliere per lanciare import e generazione report invece di affidarsi ai run a mezzanotte.

:::task-stub{title="Consentire pianificazioni personalizzate"}
1. Espandi il form in `src/Admin/Pages/SchedulesPage.php` per includere selettori di giorno della settimana, time picker e la possibilità di definire più slot per schedule.
2. Aggiorna `Domain\Repos\SchedulesRepo` e `Infra\Queue` affinché memorizzino i nuovi campi (giorno/orario) e calcolino il prossimo run rispettando la timezone scelta.
3. Aggiungi una migrazione o modifica dello schema per persistire gli attributi extra e mostra il dettaglio dell’orario nella tabella "Upcoming schedules".
4. Copri il comportamento con test che verificano la generazione del prossimo run in combinazioni diverse di timezone e slot multipli.
:::

10. **Commenti editoriali e approvazione dei report** – Consente di allegare note, insight e approvazioni manuali alle consegne periodiche da parte del marketing.

:::task-stub{title="Aggiungere note editoriali per i report"}
1. Estendi `ReportJob`/`ReportsRepo` con metadati `editor_notes` e lo stato di approvazione, includendo campi nella UI (es. `DashboardPage` o nuova pagina admin) per redigere e confermare le note.
2. Modifica `Services\Reports\ReportBuilder` per incorporare le note approvate nel contesto e assicurare che vengano salvate insieme al PDF generato.
3. Aggiorna `Services\Reports\HtmlRenderer` e i template per includere una sezione opzionale "Commenti del marketing" che mostri testo formattato e la firma dell’autore.
4. Integra indicatori di approvazione nella cronologia dei report e testa il flusso assicurando che l’assenza di note non alteri il comportamento attuale.
:::

## Backlog futuro

1. **Metriche e KPI personalizzabili per sorgente** – La generazione report usa liste di metriche hard-coded, limitando la possibilità di aggiungere KPI specifici per canale o conversioni calcolate.

:::task-stub{title="Rendere dinamici i KPI aggregati"}
1. Estendi le definizioni dei provider (`src/Services/Connectors/ProviderFactory.php`) per dichiarare le metriche disponibili e i rispettivi alias/etichette, memorizzandole nella configurazione del data source.
2. Aggiorna `Normalizer::ensureKeys()` e `ReportBuilder::aggregateRows()/computeDelta()` per iterare sulle metriche dichiarate anziché sull’elenco statico, includendo nuove colonne nel template HTML/PDF.
3. Espandi `Assembler::KPI_MAP` e la UI di `DataSourcesPage` per permettere all’utente di selezionare le metriche da includere e l’ordine di priorità.
4. Copri il nuovo flusso con test d’integrazione (report e overview) assicurandoti che metriche custom compaiano nelle viste e nei calcoli dei delta.
:::

2. **Monitor staleness dei connettori** – Sebbene `DataSource` tracci `updated_at`, la UI mostra solo una sintesi e non segnala connessioni rimaste senza upload o sync recenti.

:::task-stub{title="Segnalare i data source obsoleti"}
1. Aggiungi un campo `last_refresh_at` (derivato da `updated_at` o dal timestamp dei dati caricati) nel config del data source e popola il valore ogni volta che `DataSourcesRepo::update()` salva nuove metriche.
2. Implementa un servizio (es. `src/Services/DataSources/StalenessMonitor.php`) che calcoli lo stato (ok/attenzione/critico) in base all’età dei dati e soglie configurabili a livello globale (`Options::getGlobalSettings()`).
3. Estendi `Overview\Assembler::buildStatusPayload()` per includere stato `stale`/`critical` con messaggi contestuali e visualizza badge di avviso nella pagina admin e nell’Overview.
4. Opzionale: invia email/notifiche quando un connettore supera la soglia critica e documenta come personalizzare le soglie nel backend.
:::

3. **Registro e audit delle notifiche di anomalia** – Il router di notifica decide i canali ma non registra gli invii riusciti/falliti, rendendo difficile l’audit e la diagnosi.

:::task-stub{title="Persistenza e UI per le notifiche di anomalia"}
1. Crea una tabella/entità (es. `notification_logs`) tramite `Infra/DB` per salvare client, canale, payload sintetico, esito e timestamp ogni volta che `NotificationRouter::route()` tenta l’invio.
2. Aggiorna `NotificationRouter` per scrivere nel log i risultati dei vari notifiers, includendo errori restituiti da Slack/Teams/Telegram/Webhook.
3. Aggiungi una pagina admin (es. `src/Admin/Pages/NotificationsPage.php`) che elenchi gli eventi con filtri per cliente, canale e intervallo temporale, riutilizzando la UI standard WP.
4. Espandi le API REST (es. nuovo endpoint `/notifications/logs`) o `OverviewRoutes::status` per esporre il log e consentire integrazioni/monitoraggio esterno.
:::

4. **Sandbox per la calibrazione delle anomalie** – La pagina anomalie elenca solo righe tabellari e checkbox, senza grafici o strumenti per simulare policy alternative su dati storici.

:::task-stub{title="Introdurre una sandbox grafica per le anomalie"}
1. Prepara un endpoint (es. `/anomalies/simulate`) che accetti parametri di baseline/policy e restituisca serie storiche con marcatori di anomalie, riutilizzando `Services\Anomalies\Detector`.
2. Integra nella pagina `AnomaliesPage` un widget JS (chart.js o simile) che mostri le serie temporali e consenta di modificare in tempo reale soglie, EWMA, CUSUM e vedere il risultato prima di salvare la policy.
3. Aggiungi controlli UI per confrontare scenari (es. policy attuale vs proposta) e per esportare le impostazioni come preset.
4. Documenta la sandbox nel manuale utenti (`docs/`) e aggiungi test end-to-end (Playwright/Cypress) per verificare il flusso di simulazione.
:::

## Nuove iniziative in valutazione

1. **Attribuzione multi-touch tra canali** – Consente di distribuire le conversioni sui diversi touchpoint (first/last/linear) per misurare meglio l’impatto dei canali lungo il funnel.

:::task-stub{title="Implementare attribuzione multi-touch"}
1. Crea `src/Services/Analytics/AttributionModel.php` con metodi per calcolare modelli first/last/linear a partire dalle righe raccolte in `ReportBuilder::collectData()`.
2. Espandi `ReportBuilder` affinché, dopo `collectData()`, invii le sequenze ordinate al nuovo servizio e aggiunga le quote di attribuzione nel `$meta['kpi']` e in un blocco dedicato del contesto HTML.
3. Aggiorna `src/Services/Reports/HtmlRenderer.php` e il template base per rendere tabelle/diagrammi delle attribuzioni, con fallback quando i touchpoint mancano.
4. Copri il modello con test unitari in `tests/Services/Analytics/AttributionModelTest.php` usando dataset multi-canale.
:::

2. **Forecast e pacing rispetto ai budget** – Stima se i KPI raggiungeranno i target dichiarati e segnala quando il ritmo di spesa o conversione è fuori soglia.

:::task-stub{title="Aggiungere forecast e pacing di budget"}
1. Introduci `src/Services/Analytics/BudgetForecaster.php` che riceva KPI storici e target (nuovo campo in `Options::getGlobalSettings()['budgets']`).
2. In `ReportBuilder::buildContext()` calcola per ciascun KPI il forecast a fine periodo e l’indice di pacing, salvando i risultati in `$context['pacing']`.
3. Estendi l’Overview (`assets/js/overview.js` e `OverviewPage::renderSummarySection()`) per visualizzare barre di completamento e alert quando il pacing esce dalle soglie.
4. Scrivi test unitari sul forecaster e aggiorna la documentazione su come configurare i budget client-specific.
:::

3. **Webhooks per ingestione conversioni in tempo reale** – Aggiunge endpoint REST autenticati per ricevere nuovi eventi di conversione e inserirli immediatamente nel data lake del plugin.

:::task-stub{title="Aggiungere endpoint webhook per conversioni"}
1. Registra in `src/Http/Routes.php` un nuovo percorso `fpdms/v1/webhooks/conversion` (POST) protetto da firma HMAC.
2. Implementa un handler `handleWebhookConversion()` che validi il payload (nuovo schema JSON) e lo invii a `DataSourcesRepo::appendEvent()` o analogo metodo da creare.
3. Aggiungi una tabella `conversion_events` tramite migrazione in `tools/migrations` e un servizio `src/Services/Connectors/WebhookIngestor.php` per normalizzare i campi.
4. Prevedi test d’integrazione REST (`tests/Http/WebhookConversionTest.php`) e documenta i passaggi di configurazione.
:::

4. **Comandi Slash Slack/Teams per KPI on demand** – Permette a team chat di interrogare i KPI o scaricare report direttamente dai canali Slack/Teams.

:::task-stub{title="Gestire comandi interattivi Slack/Teams"}
1. Crea controller REST (`src/Http/ChatCommandsRoutes.php`) che registri `/chat/slack` e `/chat/teams`, validando token e payload firmati.
2. Implementa `Services/Chat/CommandHandler.php` per rispondere a comandi come `/fpdms report <client>` interrogando `Overview\Assembler` e `ReportsRepo`.
3. Aggiorna `NotificationRouter` per includere metadata sugli hook di risposta (URL) nelle impostazioni (`Options::getGlobalSettings()['chat_commands']`).
4. Documenta l’installazione nelle guide e aggiungi test su firma/token usando payload di esempio Slack.
:::

5. **Retry e osservabilità avanzata dei job di coda** – Migliora l’affidabilità dei processi schedulati aggiungendo tentativi automatici e log strutturati delle eccezioni.

:::task-stub{title="Aggiungere retry e tracciamento errori alla coda"}
1. Estrai la logica di `Queue::process()` in una classe `Queue\JobRunner` che intercetti eccezioni e aumenti un contatore `retry_attempts` nel meta job.
2. Introduci soglie configurabili (`Options::getGlobalSettings()['queue']['max_attempts']`) e requeue automatica via `ReportsRepo::update()` finché non si supera il limite.
3. Registra cause e stack trace in una tabella `report_failures` collegata al job, esponendo i dati nella pagina admin di monitoraggio.
4. Copri con test l’incremento dei retry e il fallback definitivo dopo il superamento della soglia.
:::

## Interventi esclusi o rinviati

1. **Backup e ripristino delle configurazioni** – Nessuna esigenza immediata, riprendere il tema se aumentano gli ambienti.
2. **Health monitor della coda e degli scheduler** – Complessità non giustificata dal carico corrente.
3. **Dataset sintetici per QA interno** – I test manuali sui dati reali sono ritenuti sufficienti.
4. **Versioning e anteprima in tempo reale dei template** – Rimandato fino a quando l’iterazione sui layout diventerà frequente.
5. **Ruoli/capability dedicati per il marketing** – Esclusi perché il plugin rimarrà ad uso singolo.
6. **Connettori API OAuth per piattaforme adv** – Non necessari finché i CSV/manual import restano gestibili.
7. **Portale front-end self-service** – Rimandato poiché non ci sono clienti terzi da servire direttamente.

