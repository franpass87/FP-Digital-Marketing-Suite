# QA Manuale Admin UI

Questa checklist guida le verifiche manuali da eseguire dopo il restyling dell'interfaccia amministrativa del plugin FP Digital Marketing Suite. Gli scenari coprono menu, schermate principali, flussi CRUD e interazioni AJAX, assicurando che l'aspetto rinnovato non introduca regressioni funzionali.

## Prerequisiti
- Installazione WordPress >= 6.4 con plugin FP Digital Marketing Suite attivo.
- Ruolo amministratore o utente con capability equivalenti a `Capabilities::MANAGE_SETTINGS`.
- Dati di esempio per campagne, segmenti audience, eventi di conversione e alert per evitare stati vuoti.
- WP_DEBUG e WP_DEBUG_LOG attivi per intercettare notice/warning.

## Checklist trasversale
- [ ] Effettua il login, verifica che il menu "FP Marketing Suite" compaia con le stesse voci pianificate nell'IA.
- [ ] Apri ogni schermata dal menu e conferma che titoli, breadcrumb, tab e azioni corrispondano alla documentazione IA.
- [ ] Controlla che gli Screen Options/Help Tabs siano presenti dove previsti e che le impostazioni salvate persistano dopo refresh.
- [ ] Naviga tutto il plugin con tastiera (Tab/Shift+Tab) verificando focus visibile e ordine logico.
- [ ] Ridimensiona la finestra a 1024px e verifica che layout e componenti rimangano utilizzabili senza overflow.
- [ ] Osserva la console del browser e il log PHP per assenza di errori JS/PHP durante le interazioni.

## Panoramica performance
- [ ] Carica grafici e KPI principali; verifica aggiornamento selettore intervallo date.
- [ ] Usa i link rapidi verso report/alert e conferma i redirect corretti.
- [ ] Controlla il widget "Stato sincronizzazione" e il relativo messaggio di esito.

## Reports & Analytics
- [ ] Applica filtri per intervallo date e segmenti; conferma il refresh dati.
- [ ] Esegui export CSV/PDF (se abilitato) e verifica che i file generati siano consistenti.

## Funnel Analysis
- [ ] Cambia funnel dal selettore; verifica il rendering dei passaggi e dei tassi di conversione.
- [ ] Triggera l'aggiornamento AJAX (refresh manuale) e controlla eventuali notice.

## Segmentazione Audience
- [ ] Crea un nuovo segmento, definisci criteri multipli, salva e verifica presenza nella lista.
- [ ] Modifica segmento esistente e controlla che le condizioni precedenti vengano precompilate.
- [ ] Elimina un segmento e conferma messaggi di conferma/undo.

## Gestione Campagne UTM
- [ ] Dal list table, testa view "Tutte/Attive/Pausa/Completate" e filtri rapidi.
- [ ] Usa il campo di ricerca per filtrare campagne esistenti.
- [ ] Seleziona più righe ed esegui azione bulk "Imposta stato" verificando nonce e messaggi di successo.
- [ ] Apri l'azione rapida "Duplica" e assicurati che i dati vengano popolati correttamente nel form modale.

## Eventi Conversione
- [ ] Aggiungi un nuovo evento specificando trigger, target e notifica.
- [ ] Metti in pausa/riattiva eventi esistenti verificando le etichette di stato.
- [ ] Esegui azioni bulk per cancellare/più eventi e conferma che i contatori vengano aggiornati.

## Alert & Anomalie
- [ ] Verifica la lista alert con filtri stato/gravità, confermando i badge di conteggio.
- [ ] Apri un alert e segna come risolto, assicurandoti che sparisca dalle viste attive.
- [ ] Nella schermata anomalie, testa il silenziamento di una regola e la riattivazione.

## Performance & Ottimizzazione
- [ ] Nella pagina "Cache Performance" lancia un test di cache e verifica grafici storici.
- [ ] Controlla che i tooltip dei grafici e gli indicatori di stato utilizzino i nuovi token di stile.

## Configurazione
- [ ] In "Connessioni Piattaforme" testa la connessione/disconnessione per Google Ads e GA4.
- [ ] Verifica che i messaggi di successo/errore siano mostrati tramite componenti `Notice`.
- [ ] In "Sicurezza Dati" modifica le opzioni di rotazione API Key e salva, controllando messaggi di conferma.
- [ ] Nelle "Impostazioni generali" cambia valori nelle varie tab, salva e verifica persistenza.
- [ ] Esegui il reset manuale della cache dalle impostazioni e conferma gli hook invocati.

## Onboarding Wizard
- [ ] Percorri tutti gli step del wizard, verificando progress indicator, pulsanti Primario/Secondario e validazione.
- [ ] Interrompi il wizard a metà e riprendi dal menu per confermare la ripresa dello stato.

## Integrazione WP
- [ ] Controlla il widget "Prestazioni FP DMS" nella dashboard WP nativa.
- [ ] Apri l'editor dei post e verifica che il metabox SEO funzioni (analisi contenuto, tab, salvataggio).
- [ ] Apri un CPT `cliente` e verifica il metabox informazioni cliente con salvataggio corretto.

## Post-verifica
- [ ] Svuota cache/transienti del plugin e ripeti login per accertare che permessi/menu restino coerenti.
- [ ] Disattiva e riattiva il plugin controllando che le impostazioni persistano.
- [ ] Documenta gli esiti in `docs/admin-ui/qa-results.md` includendo eventuali regressioni e fix.
