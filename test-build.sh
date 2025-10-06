#!/usr/bin/env bash
#
# Quick test script for build system
#

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "${BOLD}${BLUE}🧪 Testing Build System${NC}\n"

# Test 1: Check npm
echo -e "${YELLOW}Test 1: Checking npm...${NC}"
if command -v npm &> /dev/null; then
    NPM_VERSION=$(npm --version)
    echo -e "${GREEN}✓ npm found (version: $NPM_VERSION)${NC}"
else
    echo -e "${RED}✗ npm not found${NC}"
    exit 1
fi

# Test 2: Check package.json
echo -e "\n${YELLOW}Test 2: Checking package.json...${NC}"
if [[ -f package.json ]]; then
    echo -e "${GREEN}✓ package.json exists${NC}"
    
    # Check for build script
    if grep -q '"build"' package.json; then
        echo -e "${GREEN}✓ build script found${NC}"
    else
        echo -e "${RED}✗ build script missing${NC}"
        exit 1
    fi
else
    echo -e "${RED}✗ package.json not found${NC}"
    exit 1
fi

# Test 3: Install dependencies
echo -e "\n${YELLOW}Test 3: Installing npm dependencies...${NC}"
npm install &> /dev/null
echo -e "${GREEN}✓ Dependencies installed${NC}"

# Test 4: Run npm build
echo -e "\n${YELLOW}Test 4: Running npm build...${NC}"
if npm run build; then
    echo -e "${GREEN}✓ npm build successful${NC}"
else
    echo -e "${RED}✗ npm build failed${NC}"
    exit 1
fi

# Test 5: Check asset files
echo -e "\n${YELLOW}Test 5: Checking asset files...${NC}"
MISSING=0

check_file() {
    if [[ -f "$1" ]]; then
        echo -e "${GREEN}  ✓ $1${NC}"
    else
        echo -e "${RED}  ✗ $1 (missing)${NC}"
        MISSING=$((MISSING + 1))
    fi
}

echo "JavaScript files:"
check_file "assets/js/connection-validator.js"
check_file "assets/js/connection-wizard.js"
check_file "assets/js/overview.js"

echo "CSS files:"
check_file "assets/css/connection-validator.css"
check_file "assets/css/dashboard.css"
check_file "assets/css/overview.css"

if [[ $MISSING -gt 0 ]]; then
    echo -e "${RED}✗ $MISSING asset files missing${NC}"
    exit 1
fi
echo -e "${GREEN}✓ All asset files present${NC}"

# Test 6: Check PHP
echo -e "\n${YELLOW}Test 6: Checking PHP...${NC}"
if command -v php &> /dev/null; then
    PHP_VERSION=$(php --version | head -n1)
    echo -e "${GREEN}✓ PHP found ($PHP_VERSION)${NC}"
else
    echo -e "${RED}✗ PHP not found${NC}"
    exit 1
fi

# Test 7: Check Composer
echo -e "\n${YELLOW}Test 7: Checking Composer...${NC}"
if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer --version | head -n1)
    echo -e "${GREEN}✓ Composer found ($COMPOSER_VERSION)${NC}"
else
    echo -e "${RED}✗ Composer not found${NC}"
    exit 1
fi

# Test 8: Check build.sh
echo -e "\n${YELLOW}Test 8: Checking build.sh...${NC}"
if [[ -f build.sh ]]; then
    echo -e "${GREEN}✓ build.sh exists${NC}"
    
    if [[ -x build.sh ]]; then
        echo -e "${GREEN}✓ build.sh is executable${NC}"
    else
        echo -e "${YELLOW}⚠ build.sh not executable (run: chmod +x build.sh)${NC}"
    fi
    
    # Check if build.sh contains npm build
    if grep -q "npm.*build" build.sh; then
        echo -e "${GREEN}✓ build.sh includes npm build${NC}"
    else
        echo -e "${RED}✗ build.sh does not include npm build${NC}"
        exit 1
    fi
else
    echo -e "${RED}✗ build.sh not found${NC}"
    exit 1
fi

# Test 9: Check git hooks
echo -e "\n${YELLOW}Test 9: Checking git hooks...${NC}"
if [[ -d .githooks ]]; then
    echo -e "${GREEN}✓ .githooks directory exists${NC}"
    
    if [[ -f .githooks/post-commit ]]; then
        echo -e "${GREEN}✓ post-commit hook exists${NC}"
        
        if grep -q "npm.*build" .githooks/post-commit; then
            echo -e "${GREEN}✓ post-commit includes npm build${NC}"
        else
            echo -e "${YELLOW}⚠ post-commit does not include npm build${NC}"
        fi
    else
        echo -e "${YELLOW}⚠ post-commit hook not found${NC}"
    fi
else
    echo -e "${YELLOW}⚠ .githooks directory not found${NC}"
fi

# Test 10: Check GitHub Actions workflows
echo -e "\n${YELLOW}Test 10: Checking GitHub Actions workflows...${NC}"
WORKFLOWS=0
if [[ -d .github/workflows ]]; then
    WORKFLOW_COUNT=$(ls -1 .github/workflows/*.yml 2>/dev/null | wc -l)
    echo -e "${GREEN}✓ Found $WORKFLOW_COUNT workflow files${NC}"
    
    # Check for npm build in workflows
    NPM_WORKFLOWS=$(grep -l "npm.*build" .github/workflows/*.yml 2>/dev/null | wc -l)
    echo -e "${GREEN}✓ $NPM_WORKFLOWS workflows include npm build${NC}"
else
    echo -e "${YELLOW}⚠ .github/workflows directory not found${NC}"
fi

# Final summary
echo -e "\n${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${GREEN}✅ All tests passed!${NC}"
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

echo -e "${BOLD}Next steps:${NC}"
echo -e "  ${BLUE}•${NC} Run full build: ${YELLOW}./build.sh${NC}"
echo -e "  ${BLUE}•${NC} Setup git hooks: ${YELLOW}./setup-hooks.sh${NC}"
echo -e "  ${BLUE}•${NC} Make a commit to test auto-build"

echo -e "\n${GREEN}Build system is ready! 🚀${NC}\n"
