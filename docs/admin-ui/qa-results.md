# Risultati QA Manuale

Questa tabella riporta lo stato dei test manuali eseguiti (o pianificati) per le schermate admin del plugin. Aggiornare il file dopo ogni sessione QA annotando anomalie e fix collegati.

| Data | Ambiente | Scenario | Esito | Note | Follow-up |
| --- | --- | --- | --- | --- | --- |
| 2024-05-07 | WP 6.4 (locale) | Smoke test navigazione menu + schermate principali | ⬜️ Non eseguito | Ambiente WordPress non disponibile nel container; eseguire su istanza locale o staging. | Pianificare sessione con dati demo. |
| 2024-05-07 | WP 6.4 (locale) | UTM Campaigns list table (filtri, bulk, azioni riga) | ⬜️ Non eseguito | Richiede seed di campagne; verificare nonce/action dopo deploy su staging. | QA team marketing. |
| 2024-05-07 | WP 6.4 (locale) | Settings & Validazione (salvataggio, notice) | ⬜️ Non eseguito | Necessario controllo con account dotato di capability `MANAGE_SETTINGS`. | Bloccare release finché non completato. |
| 2024-05-07 | WP 6.4 (locale) | Alert/Anomalie (silenzia/risolvi) | ⬜️ Non eseguito | Dipende da job schedulati; simulare alert tramite CLI helper. | Coordinare con team SRE. |
| 2024-05-07 | WP 6.4 (locale) | Onboarding wizard (flusso completo + ripresa) | ⬜️ Non eseguito | Wizard richiede credenziali API; predisporre sandbox. | QA funzionale. |

## Regressioni note
- Nessuna regressione registrata in questa sessione (test non ancora eseguiti).

## Azioni consigliate
1. Preparare un ambiente di staging con dati sintetici per completare la checklist.
2. Registrare in questa sezione gli esiti (✅/⚠️/❌) e linkare eventuali issue aperte.
3. Ripetere i test principali dopo ogni modifica sostanziale al menu, componenti o logica di salvataggio.
