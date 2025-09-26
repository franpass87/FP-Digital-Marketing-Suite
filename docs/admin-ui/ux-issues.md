# Admin UX Issues Inventory

## Navigazione & Information Architecture

- **Emoji nei titoli di menu e mix lingua EN/IT.** Tutte le voci del registro menù incorporano emoji (`🏠`, `📊`, `🚀`, ecc.) e alternano inglese/italiano, creando problemi di leggibilità per screen reader e incoerenza con le WP Admin Guidelines. Fonte: `src/Admin/MenuRegistry.php`.
- **Sottomenu ridondanti e collegamenti alternativi.** Alcune pagine (es. Anomaly Detection) registrano il submenu sotto il menu principale ma poi reindirizzano verso `edit.php?post_type=cliente`, generando URL poco chiari e breadcrumb non coerenti (`src/Admin/AnomalyDetectionAdmin.php`, `src/Admin/AnomalyRadar.php`).
- **Assenza di Screen Options/Help Tabs.** Nessuna pagina ispezionata invoca `add_screen_option` o `get_current_screen()->add_help_tab`, limitando la scoperta di funzionalità avanzate e la personalizzazione delle liste.

## Layout, componenti e coerenza visiva

- **CSS inline non modularizzato.** Molte schermate iniettano blocchi CSS tramite `wp_add_inline_style('wp-admin', ...)`, impedendo riuso e ottimizzazione: conversion events (`src/Admin/ConversionEventsAdmin.php`), UTM manager (`src/Admin/UTMCampaignManager.php`), cache performance (`src/Admin/CachePerformance.php`), wizard (`src/Admin/OnboardingWizard.php`).
- **JS inline agganciato a `jquery`.** Diversi moduli aggiungono script complessi con `wp_add_inline_script('jquery', ...)`, ostacolando il controllo di dipendenze e la localizzazione (stessi file dei punti precedenti, più `src/Admin/SeoMeta.php`).
- **Tipologie di layout incoerenti.** Alcune pagine usano `<table class="form-table">` (meta box) mentre altre definiscono card custom senza classi WP (cache/security), rendendo l'aspetto non uniforme rispetto al core.
- **Iconografia e badge custom.** Le pagine mostrano badge/icone personalizzate (es. classi `.fp-security-score`, `.fp-status-*`) senza rispettare i colori WP standard (`src/Admin/SecurityAdmin.php`, `src/Admin/AnomalyDetectionAdmin.php`).

## Accessibilità

- **Contrasto e stati focus non definiti.** Gli stili inline per card/toggle non includono focus visibile o criteri di contrasto; es. toggle switch negli alert (`src/Admin/AlertingAdmin.php`) e card security (`src/Admin/SecurityAdmin.php`).
- **Label/tab custom senza ruoli ARIA.** Le tab artigianali (Conversion Events, SEO meta) non impostano `role="tablist"/"tab"` né gestione ARIA, riducendo la navigazione da tastiera.
- **Wizard onboarding inline.** Il wizard applica script/css inline senza annunci per screen reader e con progress indicator solo visivo (`src/Admin/OnboardingWizard.php`).

## Feedback, form e validazione

- **Notifiche personalizzate non standardizzate.** Molte pagine generano `<div class="notice ...">` manualmente ma senza usare `settings_errors()` o API WP, rischiando inconsistenze (es. `src/Admin/CachePerformance.php`, `src/Admin/UTMCampaignManager.php`).
- **Gestione errori AJAX eterogenea.** Le risposte JSON usano messaggi generici e non sempre traducono le stringhe (es. `wp_send_json_error('Invalid nonce')` in `FunnelAnalysisAdmin::ajax_get_funnel_data`).
- **Campi obbligatori senza indicazioni visive.** Le form per campagne/eventi richiedono campi obbligatori ma non segnalano `required` o help text strutturati (`src/Admin/UTMCampaignManager.php`, `src/Admin/ConversionEventsAdmin.php`).

## Contenuti e localizzazione

- **Lingua mista.** Copy italiano e inglese convivono nella stessa pagina (`Reports`, `SecurityAdmin`, `Dashboard`), creando attriti per utenti non bilingue.
- **Terminologia duplicata.** Alcune sezioni usano sinonimi diversi per la stessa azione (es. "Silenzia" vs "Riattiva" nelle anomalie senza descrizioni contestuali).

## Performance & Asset Management

- **Versioni multiple di Chart.js.** Dashboard e funnel analysis caricano Chart.js 4.4.0, mentre cache performance usa 3.9.1: doppio download e potenziali conflitti (`src/Admin/Dashboard.php`, `src/Admin/FunnelAnalysisAdmin.php`, `src/Admin/CachePerformance.php`).
- **Mancanza di build pipeline dichiarata.** Gli asset sono serviti da `assets/css/*.css` e `assets/js/*.js` senza processi di build/minificazione documentati.
- **Inline base64 CSS.** La pagina security inietta uno stylesheet codificato base64 (`src/Admin/SecurityAdmin.php`), difficile da mantenere/versionare.

## QA e governance

- **Avvisi razionalizzazione menu sempre attivi.** `MenuManager::show_rationalization_notice` aggiunge un notice info di onboarding che può risultare ripetitivo (`src/Admin/MenuManager.php`).
- **Dipendenza forte da hook `admin_init`.** Molte operazioni critiche (download report, sanitizzazione) avvengono in `admin_init`, aumentando il rischio di conflitti con altre integrazioni.
- **Assenza di checklist QA documentata.** Non esistono (ancora) script o checklist per verificare le funzionalità dopo modifiche UI.
