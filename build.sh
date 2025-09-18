#!/bin/bash

# FP Digital Marketing Suite - Build Script
# This script creates a distributable ZIP package for WordPress installation

set -e

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${ROOT}"

# Configuration
PLUGIN_NAME="fp-digital-marketing-suite"
VERSION="${PLUGIN_VERSION:-$(grep "Version:" "${ROOT}/fp-digital-marketing-suite.php" | head -1 | awk -F': ' '{print $2}' | tr -d '\r')}"
BUILD_DIR="build"
DIST_DIR="dist"

echo "🚀 Building FP Digital Marketing Suite v${VERSION}"

# Clean previous builds
rm -rf "${BUILD_DIR}" "${DIST_DIR}"
mkdir -p "${BUILD_DIR}" "${DIST_DIR}"

echo "📦 Copying plugin files..."

# Copy plugin files (exclude development files)
rsync -av \
    --exclude='.git*' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='build/' \
    --exclude='dist/' \
    --exclude='tests/' \
    --exclude='demo/' \
    --exclude='.php*' \
    --exclude='composer.lock' \
    --exclude='phpunit.*' \
    --exclude='*.md' \
    --exclude='verify-deployment.php' \
    --exclude='build.sh' \
    . "${BUILD_DIR}/${PLUGIN_NAME}/"

# Copy essential documentation
cp README.md "${BUILD_DIR}/${PLUGIN_NAME}/"
cp CHANGELOG.md "${BUILD_DIR}/${PLUGIN_NAME}/"
cp DEPLOYMENT_GUIDE.md "${BUILD_DIR}/${PLUGIN_NAME}/"

echo "📚 Installing production dependencies..."

# Install only production dependencies
cd "${BUILD_DIR}/${PLUGIN_NAME}"
if [ -f composer.json ]; then
    composer install --no-dev --optimize-autoloader --no-interaction
fi
cd - > /dev/null

echo "🧹 Cleaning up build files..."

# Remove any remaining development files
find "${BUILD_DIR}/${PLUGIN_NAME}" -name ".DS_Store" -delete 2>/dev/null || true
find "${BUILD_DIR}/${PLUGIN_NAME}" -name "Thumbs.db" -delete 2>/dev/null || true
find "${BUILD_DIR}/${PLUGIN_NAME}" -name "*.log" -delete 2>/dev/null || true

echo "🗜️ Creating ZIP package..."

# Create ZIP package
cd "${BUILD_DIR}"
zip -r "../${DIST_DIR}/${PLUGIN_NAME}-${VERSION}.zip" "${PLUGIN_NAME}/"
cd - > /dev/null

echo "📊 Build summary:"
echo "  - Package: ${DIST_DIR}/${PLUGIN_NAME}-${VERSION}.zip"
echo "  - Size: $(du -h ${DIST_DIR}/${PLUGIN_NAME}-${VERSION}.zip | cut -f1)"
echo "  - Files: $(find ${BUILD_DIR}/${PLUGIN_NAME} -type f | wc -l)"

echo "✅ Build completed successfully!"
echo ""
echo "🚀 Ready for deployment:"
echo "  - Upload ${DIST_DIR}/${PLUGIN_NAME}-${VERSION}.zip to WordPress"
echo "  - Or extract to wp-content/plugins/ directory"
echo ""
echo "📝 Next steps:"
echo "  1. Test the package in a staging environment"
echo "  2. Run verify-deployment.php after installation"
echo "  3. Configure API keys and settings"
echo "  4. Activate the plugin"

# Create checksum
cd "${DIST_DIR}"
sha256sum "${PLUGIN_NAME}-${VERSION}.zip" > "${PLUGIN_NAME}-${VERSION}.zip.sha256"
echo "🔐 Checksum created: ${PLUGIN_NAME}-${VERSION}.zip.sha256"
cd - > /dev/null

echo ""
echo "🎉 Build process complete!"