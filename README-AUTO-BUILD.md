# 🔧 Sistema di Build Automatico

Il plugin include un sistema completo per mantenere lo ZIP di build sempre aggiornato, sia in locale che su GitHub.

## 🚀 Quick Start

### Setup Locale (Una tantum)

```bash
# Installa gli hook Git
./setup-hooks.sh
```

Questo configurerà:
- ✅ Build automatica dopo ogni commit
- ✅ Validazione opzionale prima del push
- ✅ Configurazione personalizzabile

### Build Manuale

```bash
# Build semplice
./build.sh

# Build con bump di versione
./build.sh --bump=patch    # 1.0.0 -> 1.0.1
./build.sh --bump=minor    # 1.0.0 -> 1.1.0
./build.sh --bump=major    # 1.0.0 -> 2.0.0

# Build con versione specifica
./build.sh --set-version=1.2.3

# Build con nome ZIP personalizzato
./build.sh --zip-name=custom-name.zip
```

## 🎯 Funzionalità

### 1. Build Automatica Locale (Git Hooks)

Dopo aver eseguito `./setup-hooks.sh`, ogni commit attiverà automaticamente:

```
git commit -m "Fix bug"
🔨 Auto-building plugin ZIP...
✅ Plugin ZIP built successfully
📦 Created: build/fp-digital-marketing-suite-20241006.zip
```

**Gestione:**
```bash
# Disabilita auto-build
git config hooks.autobuild false

# Riabilita auto-build
git config hooks.autobuild true

# Controlla stato
git config hooks.autobuild
```

### 2. Build Automatica su GitHub (Actions)

Tre workflow automatici:

#### a) Auto-Build (su ogni branch)
- **Trigger:** Push su qualsiasi branch, PR, manuale
- **Output:** Artifact scaricabile per 14 giorni
- **File:** `.github/workflows/auto-build.yml`

```yaml
# Si attiva automaticamente su:
- push (tutti i branch)
- pull_request
- workflow_dispatch (manuale)
```

#### b) Build ZIP Standard
- **Trigger:** Tag `v*.*.*`
- **Output:** ZIP ottimizzato per WordPress
- **File:** `.github/workflows/build-plugin-zip.yml`

#### c) Build Release Completo
- **Trigger:** Push su main, Tag `v*.*.*`
- **Output:** Source + Release ZIP con checksums
- **File:** `.github/workflows/build-zip.yml`

### 3. Validazione Pre-Push (Opzionale)

```bash
# Abilita validazione build prima di push
git config hooks.validatebuild true

# Ora ad ogni push verifica che build/ sia aggiornato
git push
🔍 Checking if build is up-to-date...
✅ Build directory looks good
```

## 📦 Tipi di ZIP Generati

### Build Locale
```
build/
  └── fp-digital-marketing-suite-YYYYMMDDHHMI.zip
```

### GitHub Actions (Auto-Build)
```
fp-digital-marketing-suite-{version}-{branch}.zip
```
**Esempio:** `fp-digital-marketing-suite-0.1.1-cursor-troubleshoot.zip`

### GitHub Actions (Release)
```
fp-digital-marketing-suite-source-v{version}.zip
fp-digital-marketing-suite-release-v{version}.zip
```

## 🔧 Configurazione

### Git Config Options

| Opzione | Descrizione | Default |
|---------|-------------|---------|
| `hooks.autobuild` | Build automatica post-commit | `true` |
| `hooks.validatebuild` | Validazione pre-push | `false` |

### Workflow Options

Tutti i workflow possono essere eseguiti manualmente:

1. Vai su **Actions** nel repository GitHub
2. Seleziona il workflow desiderato
3. Clicca "Run workflow"

## 📋 File Esclusi dalla Build

La build esclude automaticamente:

```
.git/
.github/
tests/
docs/
node_modules/
*.md (file markdown)
.idea/
.vscode/
build/
.gitattributes
.gitignore
composer.lock (in alcune build)
tools/
```

## 🐛 Troubleshooting

### Hook non si attiva

```bash
# Verifica configurazione
git config core.hooksPath

# Dovrebbe mostrare: .githooks

# Re-installa se necessario
./setup-hooks.sh
```

### Hook è troppo lento

```bash
# Disabilita temporaneamente
git config hooks.autobuild false

# Oppure fai commit senza hook
git commit --no-verify -m "Quick fix"
```

### Build fallisce

```bash
# Controlla log
cat /tmp/plugin-build.log

# Verifica dipendenze
which php
which composer
which rsync
which zip
```

### GitHub Actions fallisce

1. Vai su **Actions** nel repository
2. Clicca sul workflow fallito
3. Espandi gli step per vedere l'errore
4. Verifica:
   - Sintassi PHP valida
   - composer.json valido
   - Permessi corretti

## 🎨 Personalizzazione

### Modifica Script di Build

Edita `build.sh` per:
- Cambiare file esclusi
- Modificare nome ZIP
- Aggiungere step custom

### Modifica Git Hooks

Edita `.githooks/post-commit` per:
- Cambiare comportamento build
- Aggiungere notifiche
- Integrare con altri tool

### Modifica GitHub Actions

Edita `.github/workflows/auto-build.yml` per:
- Cambiare trigger
- Modificare retention days
- Aggiungere step di deploy

## 📚 Esempi d'Uso

### Scenario 1: Sviluppo Locale
```bash
# Setup iniziale
./setup-hooks.sh

# Lavora normalmente
git add .
git commit -m "Add feature"
# ✅ ZIP creato automaticamente in build/

# Test
unzip -l build/*.zip
```

### Scenario 2: Feature Branch
```bash
# Crea branch
git checkout -b feature/new-connector

# Committa modifiche
git commit -m "Add new connector"

# Push (attiva GitHub Actions)
git push origin feature/new-connector

# Scarica ZIP da GitHub Actions
# GitHub -> Actions -> Auto-Build -> Download artifact
```

### Scenario 3: Release
```bash
# Bump versione e build
./build.sh --bump=minor

# Committa versione
git add fp-digital-marketing-suite.php
git commit -m "Bump version to 0.2.0"

# Crea tag
git tag v0.2.0
git push origin v0.2.0

# ✅ GitHub Actions crea release automatica
```

## 🔐 Sicurezza

Gli hook e gli script:
- ✅ Non modificano il codice sorgente
- ✅ Non inviano dati esterni
- ✅ Sono eseguiti in locale nel tuo ambiente
- ✅ Possono essere disabilitati in qualsiasi momento
- ✅ Sono open source e ispezionabili

## 💡 Best Practices

1. **Esegui setup-hooks.sh dopo il clone**
   ```bash
   git clone <repo>
   cd fp-digital-marketing-suite
   ./setup-hooks.sh
   ```

2. **Mantieni build/ in .gitignore**
   - ✅ Non committare gli ZIP
   - ✅ Sono generati automaticamente
   - ✅ Risparmi spazio nel repo

3. **Usa versioning semantico**
   ```bash
   ./build.sh --bump=patch  # Bug fix
   ./build.sh --bump=minor  # Nuove feature
   ./build.sh --bump=major  # Breaking changes
   ```

4. **Test prima del push**
   ```bash
   # Build locale
   ./build.sh
   
   # Testa ZIP
   unzip -t build/*.zip
   
   # Push
   git push
   ```

## 🆘 Supporto

Per problemi o domande:
1. Controlla questa documentazione
2. Verifica i log (`/tmp/plugin-build.log`)
3. Controlla GitHub Actions logs
4. Apri una issue nel repository

---

**Ultimo aggiornamento:** 2025-10-06
**Versione documentazione:** 1.0.0
