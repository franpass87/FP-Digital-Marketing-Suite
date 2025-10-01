#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="fp-digital-marketing-suite"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_FILE="${PROJECT_ROOT}/fp-digital-marketing-suite.php"

BUMP_MODE=""
BUMP_REQUESTED=false
SET_VERSION=""
ZIP_NAME=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --set-version=*)
      SET_VERSION="${1#*=}"
      shift
      ;;
    --set-version)
      SET_VERSION="$2"
      shift 2
      ;;
    --bump=*)
      BUMP_MODE="${1#*=}"
      BUMP_REQUESTED=true
      shift
      ;;
    --bump)
      BUMP_MODE="$2"
      BUMP_REQUESTED=true
      shift 2
      ;;
    --zip-name=*)
      ZIP_NAME="${1#*=}"
      shift
      ;;
    --zip-name)
      ZIP_NAME="$2"
      shift 2
      ;;
    --help|-h)
      cat <<'USAGE'
Usage: bash build.sh [options]
  --set-version=X.Y.Z      Set an explicit version before building.
  --bump=patch|minor|major Bump the version (default when provided: patch).
  --zip-name=name.zip      Override the generated zip file name.
USAGE
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      exit 1
      ;;
  esac
done

if [[ -n "$SET_VERSION" ]]; then
  php "$PROJECT_ROOT/tools/bump-version.php" --set="$SET_VERSION"
elif [[ "$BUMP_REQUESTED" == true ]]; then
  MODE="${BUMP_MODE:-patch}"
  case "$MODE" in
    major|minor|patch)
      php "$PROJECT_ROOT/tools/bump-version.php" --"$MODE"
      ;;
    *)
      echo "Invalid bump mode: $MODE" >&2
      exit 1
      ;;
  esac
fi

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer dump-autoload -o --classmap-authoritative

BUILD_DIR="${PROJECT_ROOT}/build/${PLUGIN_SLUG}"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

RSYNC_EXCLUDES=(
  "--exclude" ".git"
  "--exclude" ".github"
  "--exclude" "tests"
  "--exclude" "docs"
  "--exclude" "node_modules"
  "--exclude" "*.md"
  "--exclude" ".idea"
  "--exclude" ".vscode"
  "--exclude" "build"
  "--exclude" ".gitattributes"
  "--exclude" ".gitignore"
)

rsync -a --delete "${RSYNC_EXCLUDES[@]}" "$PROJECT_ROOT/" "$BUILD_DIR/"

TIMESTAMP="$(date +%Y%m%d%H%M)"
if [[ -z "$ZIP_NAME" ]]; then
  ZIP_NAME="${PLUGIN_SLUG}-${TIMESTAMP}.zip"
fi

ZIP_PATH="${PROJECT_ROOT}/build/${ZIP_NAME}"
rm -f "$ZIP_PATH"
(
  cd "$PROJECT_ROOT/build"
  zip -rq "${ZIP_NAME}" "${PLUGIN_SLUG}"
)

CURRENT_VERSION=$(php -r '
$file = $argv[1] ?? null;
if ($file === null) {
    fwrite(STDERR, "Missing plugin file path\n");
    exit(1);
}
$contents = file_get_contents($file);
if ($contents === false) {
    fwrite(STDERR, "Unable to read version\n");
    exit(1);
}
if (preg_match("/^\\s*\\*\\s*Version:\\s*(.+)$/m", $contents, $matches)) {
    echo trim($matches[1]);
    exit(0);
}
fwrite(STDERR, "Unable to read version\n");
exit(1);
' "$PLUGIN_FILE")

echo "Version: ${CURRENT_VERSION}"
echo "ZIP: ${ZIP_PATH}"

echo "Contents:"
unzip -l "$ZIP_PATH" | awk 'NR>3 && $4 != "" {split($4, parts, "/"); print parts[1]}' | sort -u
