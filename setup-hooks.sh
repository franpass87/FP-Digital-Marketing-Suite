#!/usr/bin/env bash
#
# Setup script for git hooks
# This configures automatic plugin ZIP building
#

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m' # No Color

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GITHOOKS_DIR="${PROJECT_ROOT}/.githooks"
GIT_HOOKS_DIR="${PROJECT_ROOT}/.git/hooks"

echo -e "${BOLD}${BLUE}⚙️  FP Digital Marketing Suite - Git Hooks Setup${NC}\n"

# Check if we're in a git repository
if [[ ! -d "${PROJECT_ROOT}/.git" ]]; then
    echo -e "${RED}❌ Not a git repository${NC}"
    exit 1
fi

# Check if .githooks directory exists
if [[ ! -d "$GITHOOKS_DIR" ]]; then
    echo -e "${RED}❌ .githooks directory not found${NC}"
    exit 1
fi

echo -e "${YELLOW}This script will set up the following features:${NC}"
echo -e "  ${GREEN}✓${NC} Auto-build plugin ZIP after each commit"
echo -e "  ${GREEN}✓${NC} Validate build before push (optional)"
echo -e "  ${GREEN}✓${NC} Easy enable/disable via git config"
echo ""

# Ask for confirmation
read -p "Do you want to continue? [Y/n] " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]] && [[ ! -z $REPLY ]]; then
    echo -e "${YELLOW}Setup cancelled${NC}"
    exit 0
fi

echo -e "\n${BLUE}📋 Installing git hooks...${NC}"

# Configure git to use .githooks directory
git config core.hooksPath "$GITHOOKS_DIR"
echo -e "  ${GREEN}✓${NC} Set hooks path to .githooks"

# Make hooks executable
chmod +x "$GITHOOKS_DIR"/* 2>/dev/null || true
echo -e "  ${GREEN}✓${NC} Made hooks executable"

# Configure auto-build (enabled by default)
echo ""
read -p "Enable auto-build after each commit? [Y/n] " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]] || [[ -z $REPLY ]]; then
    git config hooks.autobuild true
    echo -e "  ${GREEN}✓${NC} Auto-build enabled"
else
    git config hooks.autobuild false
    echo -e "  ${YELLOW}⊘${NC} Auto-build disabled"
fi

# Configure build validation before push (disabled by default)
echo ""
read -p "Enable build validation before push? [y/N] " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    git config hooks.validatebuild true
    echo -e "  ${GREEN}✓${NC} Build validation enabled"
else
    git config hooks.validatebuild false
    echo -e "  ${YELLOW}⊘${NC} Build validation disabled"
fi

echo -e "\n${GREEN}${BOLD}✅ Git hooks installed successfully!${NC}\n"

echo -e "${BOLD}Quick reference:${NC}"
echo -e "  ${BLUE}•${NC} Build will run automatically after each commit (if enabled)"
echo -e "  ${BLUE}•${NC} Manual build: ${YELLOW}./build.sh${NC}"
echo -e "  ${BLUE}•${NC} Enable auto-build: ${YELLOW}git config hooks.autobuild true${NC}"
echo -e "  ${BLUE}•${NC} Disable auto-build: ${YELLOW}git config hooks.autobuild false${NC}"
echo -e "  ${BLUE}•${NC} Enable pre-push validation: ${YELLOW}git config hooks.validatebuild true${NC}"
echo -e "  ${BLUE}•${NC} Disable pre-push validation: ${YELLOW}git config hooks.validatebuild false${NC}"

echo -e "\n${GREEN}Happy coding! 🚀${NC}\n"
