#!/bin/bash
# Build Assets Script for FP Digital Marketing Suite
# Compiles SCSS and prepares assets for production

set -e  # Exit on error

echo "🔨 Building FP Digital Marketing Suite Assets..."
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo -e "${RED}❌ npm not found. Please install Node.js and npm.${NC}"
    exit 1
fi

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo -e "${BLUE}📦 Installing dependencies...${NC}"
    npm install
    echo -e "${GREEN}✅ Dependencies installed${NC}"
    echo ""
fi

# Build CSS from SCSS
echo -e "${BLUE}🎨 Compiling SCSS to CSS...${NC}"
npm run build:css

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ SCSS compiled successfully${NC}"
else
    echo -e "${RED}❌ SCSS compilation failed${NC}"
    exit 1
fi

# Check output
if [ -f "assets/css/main.css" ]; then
    SIZE=$(du -h assets/css/main.css | cut -f1)
    echo -e "${GREEN}✅ main.css created (${SIZE})${NC}"
else
    echo -e "${RED}❌ main.css not found${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}🎉 Build completed successfully!${NC}"
echo ""
echo "Files generated:"
echo "  - assets/css/main.css"
echo ""
echo "Next steps:"
echo "  1. Test the styles in your WordPress admin"
echo "  2. Run 'npm run watch:css' for development"
echo "  3. Check browser console for any CSS issues"
echo ""