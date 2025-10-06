# ✅ Checklist Sistema di Build Automatico

## Pre-requisiti

### Locale
- [ ] Node.js 18+ installato (`node --version`)
- [ ] npm installato (`npm --version`)
- [ ] PHP 8.2+ installato (`php --version`)
- [ ] Composer installato (`composer --version`)
- [ ] Git installato (`git --version`)
- [ ] rsync installato (`which rsync`)
- [ ] zip installato (`which zip`)

### Repository
- [ ] Repository git inizializzato
- [ ] Branch main esiste
- [ ] File `.gitignore` presente
- [ ] `node_modules/` in `.gitignore`
- [ ] `build/` in `.gitignore`

## Setup Iniziale (una tantum)

### 1. Dipendenze NPM
```bash
# Installa dipendenze
npm install

# Verifica installazione
ls -la node_modules/
```
- [ ] Eseguito `npm install`
- [ ] Cartella `node_modules/` creata
- [ ] File `package-lock.json` creato (opzionale)

### 2. Script Build NPM
```bash
# Test build
npm run build

# Verifica output
npm run build:js
npm run build:css
```
- [ ] `npm run build` funziona
- [ ] Nessun errore durante build
- [ ] Output mostra "✓ JavaScript files are ready"
- [ ] Output mostra "✓ CSS files are ready"

### 3. Asset Files
```bash
# Verifica asset esistono
ls -la assets/js/
ls -la assets/css/
```
- [ ] `assets/js/connection-validator.js` esiste
- [ ] `assets/js/connection-wizard.js` esiste
- [ ] `assets/js/overview.js` esiste
- [ ] `assets/css/connection-validator.css` esiste
- [ ] `assets/css/dashboard.css` esiste
- [ ] `assets/css/overview.css` esiste

### 4. Git Hooks
```bash
# Installa hooks
./setup-hooks.sh

# Verifica installazione
git config core.hooksPath
git config hooks.autobuild
```
- [ ] Eseguito `./setup-hooks.sh`
- [ ] `core.hooksPath` = `.githooks`
- [ ] `hooks.autobuild` = `true` o `false`
- [ ] File `.githooks/post-commit` eseguibile
- [ ] File `.githooks/pre-push` eseguibile

### 5. Build Script
```bash
# Test build locale
./build.sh

# Verifica output
ls -la build/
```
- [ ] Script `build.sh` eseguibile
- [ ] Build completa senza errori
- [ ] Cartella `build/` creata
- [ ] File ZIP creato in `build/`
- [ ] ZIP contiene asset JavaScript
- [ ] ZIP contiene asset CSS

## GitHub Actions

### 1. Workflow Files
```bash
# Verifica workflows esistono
ls -la .github/workflows/
```
- [ ] `.github/workflows/npm-build-check.yml` esiste
- [ ] `.github/workflows/main-merge-build.yml` esiste
- [ ] `.github/workflows/auto-build.yml` esiste
- [ ] `.github/workflows/build-plugin-zip.yml` esiste
- [ ] `.github/workflows/build-zip.yml` esiste

### 2. Workflow Syntax
```bash
# Verifica sintassi YAML (se hai yamllint)
yamllint .github/workflows/*.yml
```
- [ ] Tutti i workflow hanno sintassi YAML valida
- [ ] Nessun errore di indentazione
- [ ] Tutti i workflow hanno `on:` trigger

### 3. NPM Build Integration
```bash
# Verifica npm build nei workflows
grep -l "npm.*build" .github/workflows/*.yml
```
- [ ] Almeno 4 workflow contengono "npm build"
- [ ] `main-merge-build.yml` ha `npm run build`
- [ ] `auto-build.yml` ha `npm run build`
- [ ] `build-plugin-zip.yml` ha `npm run build`
- [ ] `build-zip.yml` ha `npm run build`

## Testing

### Test 1: Build NPM Locale
```bash
npm run build
```
**Risultato atteso:**
```
🔨 Building assets...
✓ JavaScript files are ready (no compilation needed)
✓ Connection wizard JS ready
✓ Overview JS ready
✓ CSS files are ready
✅ Build complete!
```
- [ ] Test superato

### Test 2: Build Script Completo
```bash
./build.sh
```
**Risultato atteso:**
```
Building assets with npm...
✅ Assets built successfully
Loading composer repositories...
...
Version: 0.1.1
ZIP: /path/to/build/fp-digital-marketing-suite-TIMESTAMP.zip
```
- [ ] Test superato
- [ ] ZIP creato
- [ ] ZIP contiene asset

### Test 3: Git Hook Post-Commit
```bash
# Fai un commit di test
git add .
git commit -m "Test auto-build"

# Attendi alcuni secondi
sleep 5

# Verifica build in background
cat /tmp/plugin-build.log
ls -la build/
```
**Risultato atteso:**
```
Build log mostra successo
ZIP file creato in build/
```
- [ ] Test superato
- [ ] Hook eseguito automaticamente
- [ ] Build in background completata

### Test 4: Verifica Asset in ZIP
```bash
# Crea build
./build.sh

# Verifica contenuto ZIP
unzip -l build/*.zip | grep "assets/js"
unzip -l build/*.zip | grep "assets/css"
```
**Risultato atteso:**
```
Lista file mostra:
  assets/js/connection-validator.js
  assets/js/connection-wizard.js
  assets/js/overview.js
  assets/css/connection-validator.css
  assets/css/dashboard.css
  assets/css/overview.css
```
- [ ] Test superato
- [ ] Tutti gli asset JS presenti
- [ ] Tutti gli asset CSS presenti

### Test 5: Sistema Completo
```bash
# Esegui test automatico
./test-build.sh
```
**Risultato atteso:**
```
🧪 Testing Build System

Test 1: Checking npm... ✓
Test 2: Checking package.json... ✓
Test 3: Installing npm dependencies... ✓
Test 4: Running npm build... ✓
Test 5: Checking asset files... ✓
Test 6: Checking PHP... ✓
Test 7: Checking Composer... ✓
Test 8: Checking build.sh... ✓
Test 9: Checking git hooks... ✓
Test 10: Checking GitHub Actions workflows... ✓

✅ All tests passed!
```
- [ ] Tutti i 10 test superati

## GitHub Actions Testing

### Test 1: Push su Feature Branch
```bash
# Crea feature branch
git checkout -b test/auto-build

# Fai modifiche
echo "// test" >> assets/js/connection-wizard.js

# Commit e push
git add .
git commit -m "Test: auto-build workflow"
git push origin test/auto-build
```
**Risultato atteso:**
- [ ] Workflow `auto-build.yml` si attiva
- [ ] Build npm eseguita con successo
- [ ] ZIP artifact creato
- [ ] Artifact scaricabile da GitHub Actions

### Test 2: Merge su Main
```bash
# Merge su main
git checkout main
git merge test/auto-build
git push origin main
```
**Risultato atteso:**
- [ ] Workflow `main-merge-build.yml` si attiva
- [ ] Build npm eseguita
- [ ] Asset verificati
- [ ] ZIP creato
- [ ] Artifact "main-latest" disponibile
- [ ] Retention 30 giorni

### Test 3: Release con Tag
```bash
# Crea tag
git tag v0.1.2
git push origin v0.1.2
```
**Risultato atteso:**
- [ ] Workflow `build-plugin-zip.yml` si attiva
- [ ] Workflow `build-zip.yml` si attiva
- [ ] Build npm eseguita
- [ ] Release ZIP creato
- [ ] GitHub Release creata (se configurata)

### Test 4: Modifica Asset
```bash
# Modifica file asset
echo "/* test */" >> assets/css/dashboard.css

# Commit e push
git add assets/css/dashboard.css
git commit -m "Update dashboard CSS"
git push
```
**Risultato atteso:**
- [ ] Workflow `npm-build-check.yml` si attiva
- [ ] Build npm eseguita
- [ ] Check asset completato
- [ ] Workflow termina con successo

## Verifica Finale

### Checklist Generale
- [ ] ✅ NPM build funziona locale
- [ ] ✅ Build script include npm
- [ ] ✅ Git hooks installati e funzionanti
- [ ] ✅ 5 workflow GitHub Actions presenti
- [ ] ✅ Tutti i workflow includono npm build
- [ ] ✅ Asset sempre presenti nel ZIP
- [ ] ✅ Verifiche automatiche funzionanti
- [ ] ✅ Documentazione completa

### Checklist GitHub Actions
- [ ] ✅ Auto-build su ogni push
- [ ] ✅ Main-build su merge main
- [ ] ✅ NPM check su modifiche asset
- [ ] ✅ Release build su tag
- [ ] ✅ Artifact disponibili per download

### Checklist Qualità
- [ ] ✅ Zero errori build
- [ ] ✅ Asset sempre aggiornati
- [ ] ✅ ZIP sempre validi
- [ ] ✅ Checksum generati
- [ ] ✅ Logs accessibili
- [ ] ✅ Sistema robusto

## Troubleshooting

### Problema: npm build fallisce
**Soluzione:**
```bash
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Problema: Git hook non si attiva
**Soluzione:**
```bash
./setup-hooks.sh
git config core.hooksPath
# Dovrebbe mostrare: .githooks
```

### Problema: Asset mancanti in ZIP
**Soluzione:**
```bash
# Re-build con npm
npm run build

# Verifica asset
ls -la assets/

# Re-build ZIP
./build.sh

# Verifica ZIP
unzip -l build/*.zip | grep assets
```

### Problema: GitHub Actions fallisce
**Soluzione:**
1. Vai su Actions tab nel repository
2. Clicca sul workflow fallito
3. Espandi i log per vedere l'errore
4. Verifica:
   - package.json committato
   - Script "build" presente
   - File assets/ committati

## Status Report

Data: _____________________

### Sistema Locale
- [ ] Funzionante
- [ ] Problemi: _____________________

### GitHub Actions
- [ ] Funzionante
- [ ] Problemi: _____________________

### Asset Build
- [ ] Sempre aggiornati
- [ ] Problemi: _____________________

### Note Aggiuntive
_____________________________________
_____________________________________
_____________________________________

---

**Firma:** ___________________
**Data:** ____________________
