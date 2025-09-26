# IA & Menu Plan

Questo piano definisce la nuova architettura informativa del backend "FP Digital Marketing Suite", mantenendo la retrocompatibilità con gli slug e le capability esistenti quando possibile. Gli obiettivi principali sono:

- Ridurre la profondità cognitiva raggruppando le pagine per flusso di lavoro (overview → analisi → ottimizzazione → configurazione).
- Uniformare le etichette in lingua italiana con terminologia marketing coerente e immediatamente comprensibile.
- Consolidare gli slug stabili (`page=`) e predisporre reindirizzamenti/alias per i vecchi riferimenti.
- Verificare capability e target screen per prevenire regressioni di accesso.

## Principi guida

1. **Coerenza terminologica** – Dashboard e analisi condividono il prefisso "Performance" per indicare visualizzazioni dei dati; le funzioni operative usano verbi di azione ("Gestisci", "Configura").
2. **Ordine funzionale** – Le voci seguono il percorso tipico dell'utente: monitorare → analizzare → intervenire → configurare.
3. **Retrocompatibilità** – Gli slug esistenti rimangono invariati ove non strettamente necessario; quando si introduce un nuovo slug viene previsto un alias con redirect 301 lato admin (`wp_safe_redirect`) o wrapper `add_submenu_page` nascosto.
4. **Capability chiare** – Si utilizza la matrice di capability fornita in `FP\DigitalMarketing\Helpers\Capabilities`; per le schermate informative (Report) si abbassa il requisito a `fp_dms_view_dashboard` quando possibile per consentire la visualizzazione a ruoli analitici.

## Mappa prima/dopo

| Stato attuale |  |  | Proposta IA |  |  | Back-compat |
| --- | --- | --- | --- | --- | --- | --- |
| **Label menu** | **Slug** | **Capability** | **Nuova label** | **Slug target** | **Capability proposta** | **Azione compatibilità** |
| FP Digital Marketing Suite (toplevel) | `fp-digital-marketing-dashboard` | `fp_dms_view_dashboard` | FP Marketing Suite | `fp-digital-marketing-dashboard` (invariato) | `fp_dms_view_dashboard` | Mantiene slug; aggiorna label e icona vector 20×20. |
| Dashboard | `fp-digital-marketing-dashboard` | `fp_dms_view_dashboard` | Panoramica performance | `fp-digital-marketing-dashboard` | `fp_dms_view_dashboard` | Nessuna azione necessaria. |
| Reports & Analytics | `fp-digital-marketing-reports` | `fp_dms_export_reports` | Report performance | `fp-digital-marketing-reports` | `fp_dms_view_dashboard` | Aggiungere controllo secondario per download che richiede ancora `fp_dms_export_reports`. |
| Eventi Conversione | `fp-conversion-events` | `fp_dms_manage_conversions` | Gestisci conversioni | `fp-conversion-events` | `fp_dms_manage_conversions` | Solo cambio label; slug invariato. |
| Campagne UTM | `fp-utm-campaign-manager` | `fp_dms_manage_campaigns` | Generatore campagne UTM | `fp-utm-campaign-manager` | `fp_dms_manage_campaigns` | Nessuna azione necessaria. |
| Funnel Analysis | `fp-digital-marketing-funnel-analysis` | `fp_dms_funnel_analysis` | Analisi funnel | `fp-digital-marketing-funnel-analysis` | `fp_dms_funnel_analysis` | Nessuna azione necessaria. |
| Segmentazione Audience | `fp-audience-segments` | `fp_dms_manage_segments` | Segmenti audience | `fp-audience-segments` | `fp_dms_manage_segments` | Nessuna azione necessaria. |
| Alert e Notifiche | `fp-digital-marketing-alerts` | `fp_dms_manage_alerts` | Monitoraggio alert | `fp-digital-marketing-alerts` | `fp_dms_manage_alerts` | Nessuna azione necessaria. |
| Rilevazione Anomalie | `fp-digital-marketing-anomalies` | `fp_dms_manage_alerts` | Anomalie e regole | `fp-digital-marketing-anomalies` | `fp_dms_manage_alerts` | Nessuna azione necessaria. |
| Cache Performance | `fp-digital-marketing-cache-performance` | `fp_dms_manage_settings` | Ottimizzazione prestazioni | `fp-digital-marketing-cache-performance` | `fp_dms_manage_settings` | Nessuna azione necessaria. |
| Security Settings | `fp-digital-marketing-security` | `fp_dms_manage_settings` | Sicurezza dati | `fp-digital-marketing-security` | `fp_dms_manage_settings` | Nessuna azione necessaria. |
| Connessioni Piattaforme | `fp-platform-connections` | `fp_dms_manage_settings` | Connessioni piattaforme | `fp-platform-connections` | `fp_dms_manage_settings` | Nessuna azione necessaria. |
| FP Digital Marketing Settings | `fp-digital-marketing-settings` | `fp_dms_manage_settings` | Impostazioni generali | `fp-digital-marketing-settings` | `fp_dms_manage_settings` | Nessuna azione necessaria. |
| Setup Wizard (cond.) | `fp-digital-marketing-onboarding` | `fp_dms_manage_settings` | Configurazione guidata | `fp-digital-marketing-onboarding` | `fp_dms_manage_settings` | Mantiene condizione di visibilità post-setup; nuova entry ghost per vecchi utenti. |

## Raggruppamento e ordine sottomenu

1. **Panoramica**
   - Panoramica performance (`fp-digital-marketing-dashboard`).
2. **Analisi**
   - Report performance (`fp-digital-marketing-reports`).
   - Analisi funnel (`fp-digital-marketing-funnel-analysis`).
   - Segmenti audience (`fp-audience-segments`).
3. **Attivazione**
   - Generatore campagne UTM (`fp-utm-campaign-manager`).
   - Gestisci conversioni (`fp-conversion-events`).
4. **Monitoraggio**
   - Monitoraggio alert (`fp-digital-marketing-alerts`).
   - Anomalie e regole (`fp-digital-marketing-anomalies`).
5. **Ottimizzazione**
   - Ottimizzazione prestazioni (`fp-digital-marketing-cache-performance`).
6. **Configurazione**
   - Connessioni piattaforme (`fp-platform-connections`).
   - Sicurezza dati (`fp-digital-marketing-security`).
   - Impostazioni generali (`fp-digital-marketing-settings`).
7. **Supporto**
   - Configurazione guidata (`fp-digital-marketing-onboarding`) – mostrata solo quando `Options::needs_onboarding()` restituisce `true`.

Gli header di sezione saranno resi con `add_submenu_page` placeholder (titolo vuoto, capability `read`) e classe `menu-section` per creare separatori accessibili.

## Slug legacy e redirect

- La tabella di mapping degli slug legacy è ora disponibile tramite `MenuRegistry::get_legacy_redirects()` e `MenuManager::handle_legacy_redirects()`, che intercettano `$_GET['page']` e applicano `wp_safe_redirect` preservando i parametri di query.
- Gli hook `load-{$hook_suffix}` rimarranno invariati perché manteniamo gli stessi slug; non sono necessarie modifiche ai nomi degli hook.
- Aggiornare la documentazione degli hook e dei link contestuali (`docs/admin-ui/map.md`) dopo l'implementazione fisica.

## Impatti su capability e ruoli

- Creare un profilo "Analyst" con sola capability `fp_dms_view_dashboard` garantirà accesso a Dashboard e Report senza permessi di modifica.
- Le pagine operative (campagne, conversioni, segmenti) continueranno a richiedere capability di gestione specifiche, evitando escalation accidentali.
- Le pagine di configurazione rimangono riservate a `fp_dms_manage_settings`.

## Note di implementazione

- Introdurre una classe `Admin_Menu_Sections` per registrare il toplevel e ordinare le voci; l'integrazione effettiva avverrà nella fase [8].
- Per i separatori, utilizzare `add_action( 'admin_head', ... )` per iniettare CSS minimal nel menu (in attesa dei tokens grafici della fase [3]).
- Aggiornare i test/documentazione correlata (`docs/admin-ui/map.md`) quando la nuova IA sarà implementata.

