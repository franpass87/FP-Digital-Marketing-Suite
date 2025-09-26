#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

MAIN_FILE="$(grep -Rl --include="*.php" -m1 "^\\s*\\*\\s*Plugin Name:" "$ROOT_DIR" || true)"
if [[ -z "$MAIN_FILE" ]]; then
  echo "Errore: file principale del plugin non trovato." >&2
  exit 1
fi

PLUGIN_DIR="$(dirname "$MAIN_FILE")"
PLUGIN_BASENAME="$(basename "$PLUGIN_DIR")"

VERSION="$(grep -E "^[[:space:]]*\\*+[[:space:]]*Version:" "$MAIN_FILE" | head -1 | sed -E 's/.*Version:[[:space:]]*//')"
if [[ -z "$VERSION" ]]; then
  VERSION="0.0.0"
fi

SLUG="$(echo "$PLUGIN_BASENAME" | tr '[:upper:]' '[:lower:]')"

DIST_DIR="$ROOT_DIR/dist"
mkdir -p "$DIST_DIR"

ZIP_NAME="${SLUG}-v${VERSION}.zip"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"

STAGING_DIR="$(mktemp -d)"
trap 'rm -rf "$STAGING_DIR"' EXIT

TARGET_DIR="$STAGING_DIR/$PLUGIN_BASENAME"
mkdir -p "$TARGET_DIR"

rsync -a \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='tests' \
  --exclude='docs' \
  --exclude='dist' \
  --exclude='.vscode' \
  --exclude='.idea' \
  --exclude='package-lock.json' \
  --exclude='yarn.lock' \
  --exclude='composer.lock' \
  "$PLUGIN_DIR"/ "$TARGET_DIR"/

rm -f "$ZIP_PATH"

( cd "$STAGING_DIR" && zip -r "$ZIP_PATH" "$PLUGIN_BASENAME" >/dev/null )

echo "OK: creato $ZIP_PATH"
