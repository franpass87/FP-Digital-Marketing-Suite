# Admin UI Inventory

Questa mappa sintetizza tutte le schermate amministrative esposte dal plugin, con riferimento alle attuali voci di menu, agli hook di caricamento e alle dipendenze di script/stili osservate nel codice. I dati riflettono la registrazione centralizzata curata da `MenuRegistry` e gli alias legacy gestiti dalla fase [8] del playbook.

## Menu principale e sottomenu

| Gruppo IA (attuale) | Voce di menu | Titolo pagina | Slug (`page=`) | Hook screen stimato | Capability richiesta | Callback registrato | Asset / dipendenze caricati |
| --- | --- | --- | --- | --- | --- | --- | --- |
| overview | Panoramica performance | FP Marketing Suite | fp-digital-marketing-dashboard | `toplevel_page_fp-digital-marketing-dashboard` | `Capabilities::VIEW_DASHBOARD` (`fp_dms_view_dashboard`) | `Dashboard::render_dashboard_page` | `assets/css/admin-menu.css`, `assets/css/dashboard.css`, `assets/js/dashboard.js`, Chart.js 4.4.0 da CDN |
| analysis | Report performance | Reports & Analytics | fp-digital-marketing-reports | `fp-digital-marketing-dashboard_page_fp-digital-marketing-reports` | `Capabilities::VIEW_DASHBOARD` (controlli aggiuntivi per export richiedono `Capabilities::EXPORT_REPORTS`) | `Reports::render_reports_page` | Nessun enqueue dedicato (markup puro; logica AJAX+download in `admin_init`) |
| analysis | Analisi funnel | Funnel Analysis | fp-digital-marketing-funnel-analysis | `fp-digital-marketing-dashboard_page_fp-digital-marketing-funnel-analysis` | `Capabilities::FUNNEL_ANALYSIS` | `FunnelAnalysisAdmin::render_admin_page` | `assets/css/funnel-analysis.css`, `assets/js/funnel-analysis.js`, Chart.js 4.4.0 da CDN |
| analysis | Segmenti audience | Segmentazione Audience | fp-audience-segments | `fp-digital-marketing-dashboard_page_fp-audience-segments` | `Capabilities::MANAGE_SEGMENTS` | `SegmentationAdmin::render_segmentation_page` | `assets/css/segmentation-admin.css`, `assets/js/segmentation-admin.js` |
| activation | Generatore campagne UTM | Gestione Campagne UTM | fp-utm-campaign-manager | `fp-digital-marketing-dashboard_page_fp-utm-campaign-manager` | `Capabilities::MANAGE_CAMPAIGNS` | `UTMCampaignManager::render_page` | Inline CSS su `wp-admin`, inline JS su `jquery`, list table helper `UTMCampaignsListTable`, Screen Option per pagina, help tab contestuale, AJAX namespace `fpUtmAjax` |
| activation | Gestisci conversioni | Eventi Conversione | fp-conversion-events | `fp-digital-marketing-dashboard_page_fp-conversion-events` | `Capabilities::MANAGE_CONVERSIONS` | `ConversionEventsAdmin::render_admin_page` | Inline CSS su handle `wp-admin`, inline JS su `jquery`, localizzazione `fpConversionAjax` |
| monitoring | Monitoraggio alert | Alert e Notifiche | fp-digital-marketing-alerts | `fp-digital-marketing-dashboard_page_fp-digital-marketing-alerts` | `Capabilities::MANAGE_ALERTS` | `AlertingAdmin::display_admin_page` | `assets/js/alerts-admin.js`, inline toggle CSS su `wp-admin` |
| monitoring | Anomalie e regole | Rilevazione Anomalie | fp-digital-marketing-anomalies | `fp-digital-marketing-dashboard_page_fp-digital-marketing-anomalies` | `Capabilities::MANAGE_ALERTS` | `AnomalyDetectionAdmin::display_admin_page` | `assets/js/anomaly-admin.js` (no CSS dedicato) |
| optimization | Ottimizzazione prestazioni | Cache Performance | fp-digital-marketing-cache-performance | `fp-digital-marketing-dashboard_page_fp-digital-marketing-cache-performance` | `Capabilities::MANAGE_SETTINGS` | `CachePerformance::render_performance_page` | Chart.js 3.9.1 da CDN, inline CSS su `wp-admin` |
| configuration | Connessioni piattaforme | Connessioni Piattaforme | fp-platform-connections | `fp-digital-marketing-dashboard_page_fp-platform-connections` | `Capabilities::MANAGE_SETTINGS` | `PlatformConnections::render_connections_page` | `assets/admin/platform-connections.css`, `assets/admin/platform-connections.js` |
| configuration | Sicurezza dati | Security Settings | fp-digital-marketing-security | `fp-digital-marketing-dashboard_page_fp-digital-marketing-security` | `Capabilities::MANAGE_SETTINGS` | `SecurityAdmin::render_security_page` | CSS inline in handle data URI `fp-dms-security-admin` (registrato via `wp_enqueue_style`), nessun JS dedicato |
| configuration | Impostazioni generali | FP Digital Marketing Settings | fp-digital-marketing-settings | `fp-digital-marketing-dashboard_page_fp-digital-marketing-settings` | `Capabilities::MANAGE_SETTINGS` | `Settings::render_settings_page` | `assets/css/settings-tabs.css`, `assets/js/settings-tabs.js` |
| support (cond.) | Configurazione guidata | Setup Wizard | fp-digital-marketing-onboarding | `fp-digital-marketing-dashboard_page_fp-digital-marketing-onboarding` (o `toplevel_page_*` se separato) | `Capabilities::MANAGE_SETTINGS` | `OnboardingWizard::render_wizard_page` | Inline CSS/JS su `wp-admin`/`jquery` (caricati solo su schermata wizard) |

## Schermate aggiuntive e metabox

| Ambito | Entry point | Hook screen | Capability | Componenti principali | Asset |
| --- | --- | --- | --- | --- | --- |
| Custom Post Type `cliente` | Sottomenu "📡 Anomaly Radar" (slug `fp-anomaly-radar`) sotto `edit.php?post_type=cliente` | `cliente_page_fp-anomaly-radar` | `Capabilities::MANAGE_ALERTS` | Vista per-client con tab (overview/history/rules), richiama AJAX per anomalie | Inline layout via HTML, nessun enqueue dedicato (usa helpers interni) |
| Meta box cliente | `ClienteMeta` su post type `cliente` | `add_meta_boxes` / `save_post` | `edit_cliente` | Meta box "Informazioni Cliente" con campi settore/budget/contatti | Basato su markup `<table class="form-table">`, nessun asset dedicato |
| Meta box SEO globale | `SeoMeta` su tutti i post pubblici | `post.php` / `post-new.php` | `edit_post` (controllo classico) | Meta box "SEO e Social Media" con tab JS, analisi contenuto AJAX | Inline CSS su `wp-admin`, inline JS su `jquery`, AJAX `fpSeoAnalysis` |
| Dashboard WP | Widget performance registrato da `Dashboard::add_performance_dashboard_widget` | `wp_dashboard_setup` | `Capabilities::VIEW_DASHBOARD` (implicito) | Widget "Prestazioni FP DMS" con stati sync | Usa asset già caricati dalla dashboard principale |

## AJAX endpoint principali

- `wp_ajax_fp_dms_get_dashboard_data`, `wp_ajax_fp_dms_get_chart_data`, `wp_ajax_fp_dms_get_core_web_vitals`, `wp_ajax_fp_dms_record_client_vital` (dashboard).
- `wp_ajax_fp_utm_generate_url`, `wp_ajax_fp_utm_load_preset`, `wp_ajax_fp_utm_delete_campaign` (UTM manager).
- `wp_ajax_fp_conversion_event_action`, `wp_ajax_fp_dms_download_export` (conversion events).
- `wp_ajax_fp_dms_funnel_ajax` (funnel analysis data).
- `wp_ajax_fpSegmentation` handlers via `SegmentationAdmin` (creazione/valutazione segmenti).
- `wp_ajax_fp_dms_dismiss_alert`, `wp_ajax_fp_dms_refresh_alerts` (Alerting).
- `wp_ajax_acknowledge_anomaly`, `wp_ajax_silence_anomaly_rule`, `wp_ajax_dismiss_anomaly_notice` (anomaly detection).
- `wp_ajax_fp_platform_connections` azioni `fp_test_connection` e `fp_refresh_connections`.
- `wp_ajax_fp_analyze_content_seo` (analisi SEO nei metabox).
- Wizard onboarding: `wp_ajax_fp_dms_onboarding_*` (vari step) più handler di attivazione/disattivazione menu nel `SettingsManager`.

## Dipendenze condivise e osservazioni tecniche

- Molte schermate fanno affidamento su inline CSS/JS agganciati agli handle core (`wp-admin`, `jquery`), senza namespace dedicati.
- Chart.js viene caricato da CDN in più pagine (versioni 3.9.1 e 4.4.0) senza deduplicazione.
- I file presenti in `assets/css/*.css` e `assets/js/*.js` sono distribuiti direttamente; non esiste un sistema di build documentato per trasformarli.
- Le capability utilizzate sono quelle definite in `FP\DigitalMarketing\Helpers\Capabilities` e vengono assegnate ai ruoli durante l'`init`.
