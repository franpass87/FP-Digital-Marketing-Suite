# 🔨 Sistema di Build Automatico

## 🎯 Cosa Fa

Il sistema di build automatico garantisce che:

✅ **Ogni push** → Build automatico con npm  
✅ **Ogni merge su main** → Build automatico con npm + ZIP artifact  
✅ **I file JavaScript compilati** sono sempre aggiornati nel ZIP finale

## 🚀 Quick Start

### 1. Setup (una tantum)
```bash
# Installa git hooks
./setup-hooks.sh

# Installa dipendenze
npm install
```

### 2. Sviluppo
```bash
# Build asset
npm run build

# Build ZIP completo
./build.sh

# Commit (auto-build in background)
git add .
git commit -m "My changes"
```

### 3. Deploy
```bash
# Push attiva auto-build su GitHub
git push origin my-branch
```

## 📚 Documentazione Completa

Per informazioni dettagliate, consulta:

- **[README-AUTO-BUILD.md](README-AUTO-BUILD.md)** - Guida completa utente
- **[BUILD-SYSTEM-COMPLETE.md](BUILD-SYSTEM-COMPLETE.md)** - Implementazione tecnica
- **[SUMMARY-AUTO-BUILD.md](SUMMARY-AUTO-BUILD.md)** - Riepilogo dettagliato
- **[CHECKLIST-SISTEMA-BUILD.md](CHECKLIST-SISTEMA-BUILD.md)** - Checklist verifica

## 🧪 Test

```bash
# Test veloce sistema
./test-build.sh

# Test build npm
npm run build

# Test build completo
./build.sh
```

## 📦 Workflow GitHub Actions

### Su Ogni Push
- `auto-build.yml` - Build automatica + artifact (14 giorni)

### Su Merge Main
- `main-merge-build.yml` - Build + ZIP ottimizzato (30 giorni)

### Su Modifiche Asset
- `npm-build-check.yml` - Verifica build asset

### Su Tag Release
- `build-plugin-zip.yml` - Release ZIP
- `build-zip.yml` - Source + Release ZIP

## 🆘 Problemi?

```bash
# Reinstalla dipendenze
rm -rf node_modules
npm install

# Re-installa hooks
./setup-hooks.sh

# Test completo
./test-build.sh
```

## ✨ Feature

- ✅ Build NPM automatica
- ✅ Asset sempre compilati
- ✅ ZIP sempre aggiornato
- ✅ Verifiche automatiche
- ✅ Git hooks configurabili
- ✅ Multiple workflow GitHub Actions

---

**Status:** ✅ Production Ready  
**Versione:** 2.0.0  
**Data:** 2025-10-06
