# Final Sweep – Schermate da refittare

Elenco ordinato delle schermate amministrative che non hanno ancora ricevuto il restyling completo basato su design token e componenti riutilizzabili introdotti nelle fasi precedenti. I dati derivano da `docs/admin-ui/map.md`, dal commit history e dai risultati QA disponibili al 2024-05-07.

| Priorità | Slug / Screen ID | Tipo | Capability | Dipendenze CSS/JS attuali | Note stato attuale |
| --- | --- | --- | --- | --- | --- |
| 1 | `fp-digital-marketing-reports` | custom-dashboard | `Capabilities::VIEW_DASHBOARD` | Layout legacy inline, AJAX grafici tramite `reports.js` (senza componenti) | UI ancora basata su markup personalizzato, nessun uso dei componenti condivisi. |
| 2 | `fp-digital-marketing-funnel-analysis` | custom-dashboard | `Capabilities::FUNNEL_ANALYSIS` | `assets/css/funnel-analysis.css`, `assets/js/funnel-analysis.js`, Chart.js 4.4.0 CDN | Spaziature e tab senza token, grafici non armonizzati con palette aggiornata. |
| 3 | `fp-audience-segments` | list-table | `Capabilities::MANAGE_SEGMENTS` | `assets/css/segmentation-admin.css`, `assets/js/segmentation-admin.js` | List table custom senza Screen Options/Help Tabs componentizzati. |
| 4 | `fp-conversion-events` | list-table | `Capabilities::MANAGE_CONVERSIONS` | Inline CSS/JS su `jquery`, localizzazione `fpConversionAjax` | Azioni bulk e notice non standardizzate, manca refit componenti. |
| 5 | `fp-digital-marketing-alerts` | list-table | `Capabilities::MANAGE_ALERTS` | `assets/js/alerts-admin.js`, inline CSS toggle | Views/filters personalizzati, messaggistica legacy. |
| 6 | `fp-digital-marketing-anomalies` | list-table | `Capabilities::MANAGE_ALERTS` | `assets/js/anomaly-admin.js` | Tab e filtri senza token, manca gestione focus/focus ring. |
| 7 | `fp-digital-marketing-cache-performance` | custom-dashboard | `Capabilities::MANAGE_SETTINGS` | Chart.js 3.9.1 CDN, inline CSS | Layout tabellare rigido, notice personalizzati, nessun componente. |
| 8 | `fp-platform-connections` | settings | `Capabilities::MANAGE_SETTINGS` | `assets/admin/platform-connections.css`, `assets/admin/platform-connections.js` | Form legacy, card e tabs custom, messaggi status non standardizzati. |
| 9 | `fp-digital-marketing-security` | settings | `Capabilities::MANAGE_SETTINGS` | `fp-dms-security-admin` inline CSS | Tabelle form-table senza componenti, manca sanitizzazione centralizzata. |
| 10 | `fp-digital-marketing-onboarding` | wizard | `Capabilities::MANAGE_SETTINGS` | Inline CSS/JS su `wp-admin`/`jquery` | Stepper wizard custom, manca riassegnazione token/a11y. |
| 11 | `cliente_page_fp-anomaly-radar` | CPT UI | `Capabilities::MANAGE_ALERTS` | Inline layout HTML, AJAX per anomalie | Vista CPT senza componenti, tab personalizzate. |
| 12 | `ClienteMeta` metabox | metabox | `edit_cliente` | Nessun asset dedicato | Markup `<table class="form-table">` legacy, manca help text coerente. |
| 13 | `SeoMeta` metabox | metabox | `edit_post` | Inline CSS/JS, AJAX `fpSeoAnalysis` | Tab JS personalizzate, manca focus management. |

