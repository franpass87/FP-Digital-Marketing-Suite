# 🧪 Istruzioni Complete per Test Plugin

## 📋 Test Dashboard Automatica

Ho creato una **dashboard di test interattiva** accessibile dal browser:

### 🌐 URL Test Dashboard

```
http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/test-dashboard.php
```

**Questa dashboard mostra:**
- ✅ Status plugin (attivo/non attivo)
- ✅ Tutte le tabelle database
- ✅ Campi review nella tabella reports
- ✅ Statistiche database (clienti, report, template)
- ✅ Classi PHP caricate
- ✅ File assets presenti
- ✅ Riepilogo finale con azioni

---

## ✅ File Verificati e Presenti

### Implementazione Review & Editor

| File | Path | Status |
|------|------|--------|
| ReportsPage.php | `src/Admin/Pages/` | ✅ PRESENTE |
| ReportReviewHandler.php | `src/Admin/Ajax/` | ✅ PRESENTE |
| reports-review.css | `assets/css/` | ✅ PRESENTE |
| reports-review.js | `assets/js/` | ✅ PRESENTE |
| DB.php (con migrazioni) | `src/Infra/` | ✅ PRESENTE |
| ReportBuilder.php (con HTML save) | `src/Services/Reports/` | ✅ PRESENTE |
| ReportJob.php (con campi review) | `src/Domain/Entities/` | ✅ PRESENTE |
| ReportsRepo.php (con filtri review) | `src/Domain/Repos/` | ✅ PRESENTE |

### Documentazione Creata

| File | Descrizione |
|------|-------------|
| FEATURE_REPORT_REVIEW.md | Documentazione review system completa |
| FEATURE_REPORT_EDITOR.md | Documentazione editor system completa |
| TEST_INSTRUCTIONS.md | Questo file |
| test-dashboard.php | Dashboard test interattiva |

---

## 🚀 Procedura di Test Completa

### STEP 1: Verifica Installazione Base

1. **Apri WordPress Admin**
   ```
   http://fp-development.local/wp-admin
   ```

2. **Verifica Plugin Attivo**
   - Vai in: `Plugin → Plugin installati`
   - Cerca: "FP Digital Marketing Suite"
   - Status deve essere: **ATTIVO**
   - Se NON attivo: **Attivalo ora**

3. **Verifica Voce Menu**
   - Nel menu laterale cerca: **FP Suite**
   - Sottomenu deve includere: **Reports**
   - Se manca: disattiva e riattiva il plugin

---

### STEP 2: Test Dashboard Automatica

1. **Apri Test Dashboard**
   ```
   http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/test-dashboard.php
   ```

2. **Verifica Sezioni**
   - ✅ Status Plugin → DEVE essere "ATTIVO"
   - ✅ Tabelle Database → TUTTE "PRESENTE"
   - ✅ Campi Review → TUTTI "PRESENTE"
   - ✅ Statistiche Database → Numeri visibili
   - ✅ Classi PHP → TUTTE "CARICATA"
   - ✅ File Assets → TUTTI trovati
   - ✅ Riepilogo Finale → ✅ TUTTO FUNZIONANTE

3. **Se Campi Review Mancanti**
   - Vai in: `Plugin → Plugin installati`
   - **Disattiva** "FP Digital Marketing Suite"
   - **Riattiva** "FP Digital Marketing Suite"
   - Ricarica test dashboard
   - Verifica campi ora presenti

---

### STEP 3: Test Sistema Review Report

1. **Apri Pagina Reports**
   ```
   FP Suite → Reports
   ```

2. **Verifica Interfaccia**
   - [ ] Vedi cards statistiche (Totale, Da Rivedere, Approvati, Rigettati)
   - [ ] Vedi filtri (Cliente, Stato, Review)
   - [ ] Vedi tabella report (se ci sono report)

3. **Test Filtri**
   - [ ] Clicca dropdown "Stato Review"
   - [ ] Vedi opzioni: Tutti, Da rivedere, In revisione, Approvato, Rigettato
   - [ ] Seleziona un filtro → Clicca "Filtra"
   - [ ] Tabella si aggiorna

4. **Test Review (se hai report)**
   - [ ] Clicca pulsante matita (Review) su un report
   - [ ] Si apre form review sotto la riga
   - [ ] Vedi textarea "Note di revisione"
   - [ ] Vedi pulsanti: Approva, Rigetta, Ripristina, Elimina
   - [ ] Scrivi una nota di test
   - [ ] Clicca "Approva"
   - [ ] Badge cambia a "Approvato" 🟢
   - [ ] Ricarica pagina → modifiche salvate

---

### STEP 4: Test Editor Report

**⚠️ IMPORTANTE:** Solo report generati DA OGGI hanno HTML editabile!

1. **Genera Nuovo Report** (se necessario)
   ```
   FP Suite → Clients
   ```
   - Crea un cliente di test
   - Vai in Schedules
   - Crea uno schedule
   - Esegui generazione report

2. **Apri Reports Page**
   ```
   FP Suite → Reports
   ```

3. **Verifica Pulsante Edit**
   - [ ] Su report NUOVI vedi icona `</>` (Modifica Contenuto)
   - [ ] Se NON vedi: il report è vecchio, rigeneralo

4. **Test Editor**
   - [ ] Clicca icona `</>` 
   - [ ] Si apre modal full-screen
   - [ ] Vedi 3 tab: **Editor Visuale**, **HTML**, **Anteprima**

5. **Test Tab Editor Visuale**
   - [ ] Tab "Editor Visuale" attivo di default
   - [ ] Vedi toolbar TinyMCE (grassetto, corsivo, colori, etc.)
   - [ ] Vedi contenuto HTML del report
   - [ ] Modifica un testo qualsiasi
   - [ ] Testo si modifica nel WYSIWYG

6. **Test Tab HTML**
   - [ ] Clicca tab "HTML"
   - [ ] Vedi codice HTML completo
   - [ ] Editor monospaziato
   - [ ] Modifica un tag HTML
   - [ ] Codice si modifica

7. **Test Tab Anteprima**
   - [ ] Clicca tab "Anteprima"
   - [ ] Clicca "Aggiorna Anteprima"
   - [ ] Vedi rendering HTML del report
   - [ ] Styling applicato
   - [ ] Modifiche visibili

8. **Test Salvataggio**
   - [ ] Torna a tab "Editor Visuale"
   - [ ] Modifica un testo (es: "TEST MODIFICA")
   - [ ] Clicca "Salva e Rigenera PDF"
   - [ ] Vedi messaggio "Report aggiornato..."
   - [ ] Modal si chiude
   - [ ] Pagina ricarica

9. **Test PDF Rigenerato**
   - [ ] Clicca icona occhio (Visualizza PDF)
   - [ ] PDF si apre in nuova tab
   - [ ] Cerca "TEST MODIFICA" nel PDF
   - [ ] Modifica è PRESENTE nel PDF ✅

---

### STEP 5: Test AJAX Endpoints

**Apri Console Browser** (F12)

1. **Test Load HTML**
   ```javascript
   jQuery.post(ajaxurl, {
       action: 'fpdms_load_report_html',
       nonce: fpdmsReports.nonce,
       report_id: 1 // Sostituisci con ID report reale
   }, function(response) {
       console.log('Load Response:', response);
   });
   ```
   - [ ] Response.success = true
   - [ ] Response.data.html contiene HTML

2. **Test Save HTML**
   ```javascript
   jQuery.post(ajaxurl, {
       action: 'fpdms_save_report_html',
       nonce: fpdmsReports.nonce,
       report_id: 1, // Sostituisci con ID report reale
       html_content: '<h1>Test Save</h1>'
   }, function(response) {
       console.log('Save Response:', response);
   });
   ```
   - [ ] Response.success = true
   - [ ] Response.data.message conferma salvataggio

3. **Test Update Review**
   ```javascript
   jQuery.post(ajaxurl, {
       action: 'fpdms_update_report_review',
       nonce: fpdmsReports.nonce,
       report_id: 1,
       action: 'approve',
       notes: 'Test review via console'
   }, function(response) {
       console.log('Review Response:', response);
   });
   ```
   - [ ] Response.success = true
   - [ ] Badge aggiornato

---

### STEP 6: Test Database Diretto

**Apri phpMyAdmin** (o equivalente)

1. **Verifica Tabella Reports**
   ```sql
   SELECT * FROM wp_fpdms_reports LIMIT 5;
   ```
   - [ ] Vedi colonna `review_status`
   - [ ] Vedi colonna `review_notes`
   - [ ] Vedi colonna `reviewed_at`
   - [ ] Vedi colonna `reviewed_by`

2. **Verifica HTML Content**
   ```sql
   SELECT id, status, meta 
   FROM wp_fpdms_reports 
   WHERE meta LIKE '%html_content%' 
   LIMIT 1;
   ```
   - [ ] Meta contiene campo `html_content`
   - [ ] HTML è completo (> 1000 caratteri)

3. **Verifica Review Data**
   ```sql
   SELECT id, review_status, review_notes, reviewed_at, reviewed_by 
   FROM wp_fpdms_reports 
   WHERE review_status != 'pending' 
   LIMIT 5;
   ```
   - [ ] Vedi review salvate
   - [ ] reviewed_by ha user ID
   - [ ] reviewed_at ha timestamp

---

### STEP 7: Test Log Errori

1. **Apri Debug Log**
   ```
   wp-content/debug.log
   ```

2. **Cerca Errori FPDMS**
   - Cerca: "fpdms"
   - Cerca: "ReportReview"
   - Cerca: "Fatal error"
   - Cerca: "Warning"

3. **Se Trovi Errori**
   - Copia errore completo
   - Verifica file e linea
   - Controlla se è critico

---

### STEP 8: Test Performance

1. **Test Caricamento Pagina Reports**
   - Apri: FP Suite → Reports
   - Apri Dev Tools → Network
   - Ricarica pagina
   - [ ] Caricamento < 2 secondi
   - [ ] Nessun errore 404
   - [ ] CSS e JS caricati

2. **Test Apertura Editor**
   - Clicca "Modifica Contenuto"
   - Misura tempo apertura modal
   - [ ] Apertura < 1 secondo
   - [ ] TinyMCE inizializzato
   - [ ] Contenuto caricato

3. **Test Salvataggio Editor**
   - Modifica contenuto
   - Clicca "Salva e Rigenera PDF"
   - Misura tempo
   - [ ] Salvataggio < 3 secondi
   - [ ] PDF rigenerato
   - [ ] Nessun errore

---

## 🐛 Troubleshooting

### Problema: Plugin non si attiva

**Soluzione:**
1. Controlla log errori: `wp-content/debug.log`
2. Verifica PHP version >= 8.1
3. Verifica estensioni: mysqli, pdo, json, mbstring
4. Controlla permessi file

### Problema: Campi review mancanti

**Soluzione:**
1. Disattiva plugin
2. Riattiva plugin (esegue migrazione)
3. Verifica in phpMyAdmin
4. Se ancora mancanti: contatta supporto

### Problema: Pulsante "Modifica Contenuto" non appare

**Soluzione:**
1. Verifica che il report sia stato generato DOPO l'installazione della feature
2. Controlla in database se `meta` contiene `html_content`
3. Se manca: rigenera il report
4. Report vecchi NON hanno HTML salvato

### Problema: Editor non salva

**Soluzione:**
1. Apri Console Browser (F12)
2. Verifica errori JavaScript
3. Controlla che nonce sia valido
4. Verifica permessi utente (deve essere admin)
5. Controlla che PDF path sia scrivibile

### Problema: PDF non si rigenera

**Soluzione:**
1. Verifica permessi cartella uploads
2. Controlla log PHP per errori mPDF
3. Verifica memoria PHP sufficiente
4. Prova con HTML più piccolo

---

## ✅ Checklist Completa Test

### Installazione
- [ ] Plugin attivo
- [ ] Tutte le tabelle database presenti
- [ ] Campi review presenti nella tabella reports
- [ ] Tutte le classi PHP caricate
- [ ] Tutti i file assets presenti

### Funzionalità Review
- [ ] Pagina Reports accessibile
- [ ] Cards statistiche visibili
- [ ] Filtri funzionanti
- [ ] Form review si apre
- [ ] Approvazione funziona
- [ ] Rigetto funziona
- [ ] Note salvate
- [ ] Badge aggiornati

### Funzionalità Editor
- [ ] Pulsante "Modifica Contenuto" visibile (su report nuovi)
- [ ] Modal editor si apre
- [ ] Tab Editor Visuale funziona
- [ ] Tab HTML funziona
- [ ] Tab Anteprima funziona
- [ ] Sync tra editor funziona
- [ ] Salvataggio funziona
- [ ] PDF si rigenera
- [ ] Modifiche visibili nel PDF

### AJAX
- [ ] Load HTML funziona
- [ ] Save HTML funziona
- [ ] Update Review funziona
- [ ] Nessun errore in console

### Database
- [ ] Campi review presenti
- [ ] html_content salvato
- [ ] Review data salvata
- [ ] Tracking funziona (who/when)

### Performance
- [ ] Pagina Reports carica veloce (< 2s)
- [ ] Editor apre veloce (< 1s)
- [ ] Salvataggio veloce (< 3s)
- [ ] Nessun memory leak
- [ ] Nessun errore JavaScript

---

## 📊 Report Test

Compila questo report dopo aver eseguito tutti i test:

```
DATA TEST: [___________]
TESTER: [___________]

=== RISULTATI ===

✅ / ❌  Plugin attivo
✅ / ❌  Database OK
✅ / ❌  Review system funzionante
✅ / ❌  Editor funzionante
✅ / ❌  AJAX OK
✅ / ❌  PDF regeneration OK
✅ / ❌  Performance OK

=== PROBLEMI RISCONTRATI ===

[Descrivi eventuali problemi]

=== NOTE ===

[Aggiungi note]
```

---

## 🎯 Test Scenario Completo

### Scenario: Workflow Completo Review + Edit

1. **Setup**
   - Crea cliente "Test Cliente"
   - Genera report per questo cliente

2. **Review Iniziale**
   - Vai in Reports
   - Report ha status "Da rivedere"
   - Apri review
   - Scrivi nota: "Da controllare sezione analytics"
   - Segna come "In revisione"

3. **Edit Contenuto**
   - Clicca "Modifica Contenuto"
   - Editor Visuale: correggi typo
   - Tab HTML: aggiungi nota
   - Tab Anteprima: verifica
   - Salva

4. **Review Finale**
   - Verifica PDF rigenerato
   - Apri review
   - Scrivi nota: "Corretto e verificato"
   - Approva

5. **Verifica**
   - Badge = "Approvato" 🟢
   - PDF contiene modifiche
   - Database aggiornato

**Tempo atteso:** 5-10 minuti

**Risultato atteso:** ✅ Workflow completo funzionante

---

## 📞 Supporto

Se riscontri problemi durante i test:

1. **Raccogli Informazioni**
   - Screenshot dell'errore
   - Console browser (F12)
   - Log PHP (`wp-content/debug.log`)
   - Versione WordPress e PHP

2. **Controlla Documentazione**
   - `FEATURE_REPORT_REVIEW.md`
   - `FEATURE_REPORT_EDITOR.md`

3. **Contatta**
   - Email: info@francescopasseri.com
   - Con: screenshot + log + descrizione problema

---

## 🎉 Test Completati?

Se tutti i test sono ✅:

**CONGRATULAZIONI! 🎉**

Il plugin è:
- ✅ Correttamente installato
- ✅ Completamente funzionante
- ✅ Pronto per produzione

**Prossimi passi:**
1. Documenta il flusso di lavoro per il team
2. Addestra gli utenti
3. Inizia a usare in produzione

---

**Versione Test Instructions**: 1.0.0  
**Data**: 25 Ottobre 2025  
**Autore**: Francesco Passeri

