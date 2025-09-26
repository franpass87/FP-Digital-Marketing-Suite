# Menu Registry & Back-Compat Shims

La fase [8] del playbook ha introdotto una registrazione centralizzata dei menu amministrativi tramite `FP\DigitalMarketing\Admin\MenuRegistry` e `FP\DigitalMarketing\Admin\MenuManager`. Questa nota riepiloga struttura, hook disponibili e strategie di retrocompatibilità.

## Struttura principale

- **Toplevel**: "FP Marketing Suite" (`fp-digital-marketing-dashboard`, capability `fp_dms_view_dashboard`).
- **Raggruppamenti**:
  - `overview`: Panoramica performance (dashboard principale).
  - `analysis`: Report performance, Analisi funnel, Segmenti audience.
  - `activation`: Generatore campagne UTM, Gestisci conversioni.
  - `monitoring`: Monitoraggio alert, Anomalie e regole.
  - `optimization`: Ottimizzazione prestazioni.
  - `configuration`: Connessioni piattaforme, Sicurezza dati, Impostazioni generali.
  - `support`: Configurazione guidata (mostrata solo quando il wizard è attivo).

`MenuRegistry::group_submenus()` restituisce le voci ordinate secondo l'elenco sopra, mantenendo eventuali gruppi aggiuntivi registrati via filtro.

## Alias legacy e redirect

`MenuRegistry::get_legacy_redirects()` espone una mappa normalizzata `legacy_slug => target_slug`. La mappa di default include i vecchi slug utilizzati prima della razionalizzazione (ad es. `fp-digital-marketing-utm-campaigns`, `fp-digital-marketing-cache`).

`MenuManager::handle_legacy_redirects()` intercetta `$_GET['page']` durante `admin_init`, preserva i parametri di query e applica `wp_safe_redirect()` (302 o 307 in caso di POST) verso lo slug canonico.

Per estendere o personalizzare la mappa è sufficiente agganciare il filtro `fp_dms_admin_menu_legacy_redirects`:

```php
add_filter( 'fp_dms_admin_menu_legacy_redirects', function ( array $map ): array {
    $map['fp-dms-legacy-screen'] = 'fp-dms-new-screen';

    return $map;
} );
```

L'azione `fp_dms_admin_menu_legacy_redirect` viene eseguita immediatamente prima del redirect con slug sorgente, destinazione e URL finale, utile per logging.

## Persistenza e screen option

Ogni volta che la struttura viene (ri)costruita, `MenuRegistry::persist_menu_slugs()` aggiorna l'opzione di stato menù tramite `SettingsManager::set_registered_menu_slugs()`. Questo consente agli altri moduli (es. wizard, ottimizzazioni) di conoscere in modo affidabile gli slug caricati.

Il toggle del wizard utilizza `MenuRegistry::enable_wizard_menu()` / `disable_wizard_menu()` e riflette immediatamente la presenza del sottomenu, rimuovendo la voce legacy dalle globali WP quando necessario.

## Debug e diagnostica

- `MenuManager::get_menu_structure()` restituisce un array con `main`, `submenus` e `legacy_redirects`, utile nei dump diagnostici.
- In caso di fallback, `MenuManager::render_admin_unavailable_page()` mostra azioni rapide aggiornate alle nuove etichette (Panoramica, Impostazioni, Configurazione guidata).
- `MenuManager::remove_legacy_menus()` viene eseguito con priorità 999 su `admin_menu` per eliminare eventuali voci duplicate registrate da plugin/estensioni più vecchie.

## Checklist per nuove schermate

1. Definire slug e capability nel `MenuRegistry` mantenendo naming consistente (`fp-` prefix).
2. Aggiornare `docs/admin-ui/map.md` con label, hook screen e dipendenze.
3. Se si sostituisce uno slug esistente, aggiungere l'alias alla mappa `LEGACY_SLUG_MAP` o, se dinamico, utilizzare il filtro dedicato.
4. Eseguire `MenuRegistry::persist_menu_slugs()` (automatico nel costruttore) per popolare l'opzione condivisa.

Queste linee guida assicurano che il menu resti coerente, accessibile e retrocompatibile durante future iterazioni.
