# Build & Release Guide

> 💡 **Novità:** Il plugin ora include un **sistema di build automatico**! 
> 
> - ✅ Build automatica dopo ogni commit
> - ✅ Build su GitHub Actions per ogni branch  
> - ✅ Setup con un comando: `./setup-hooks.sh`
> 
> Vedi **[README-AUTO-BUILD.md](README-AUTO-BUILD.md)** per la guida completa.

---

## Prerequisites
- PHP 8.2 with the `zip` extension
- Composer 2.x available in `$PATH`
- `zip` and `unzip` CLI tools
- Bash shell (tested on macOS/Linux)

## Local build workflow
1. Update or bump the plugin version and build an installable ZIP:
   ```bash
   bash build.sh --bump=patch
   ```
2. Alternatively set an explicit semantic version before packaging:
   ```bash
   bash build.sh --set-version=1.2.3
   ```
3. The script installs production dependencies, copies runtime files to `build/fp-digital-marketing-suite/`, and produces a timestamped ZIP such as `build/fp-digital-marketing-suite-202409141030.zip`.

## GitHub Actions release
1. Commit and push your changes.
2. Create and push a tag that starts with `v`, for example:
   ```bash
   git tag v1.2.3
   git push origin v1.2.3
   ```
3. The `build-plugin-zip` workflow runs automatically, builds the optimized package, and uploads the ZIP artifact.

## Notes
- The generated ZIP excludes development assets (`tests`, `docs`, `.github`, `*.md`, etc.).
- Composer autoloader optimization (`--classmap-authoritative`) is part of both the local and CI builds.
