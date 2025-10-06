#!/bin/bash

# FP Digital Marketing Suite - Standalone Application
# Installation Script

set -e

echo "=================================="
echo "FP Digital Marketing Suite"
echo "Standalone Application Installer"
echo "=================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check PHP version
echo "Checking PHP version..."
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')

if [ "$PHP_MAJOR" -lt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 1 ]); then
    echo -e "${RED}Error: PHP 8.1 or higher is required. You have PHP $PHP_VERSION${NC}"
    exit 1
fi
echo -e "${GREEN}✓ PHP $PHP_VERSION${NC}"

# Check required extensions
echo ""
echo "Checking required PHP extensions..."
EXTENSIONS=("pdo" "pdo_mysql" "json" "mbstring")
MISSING=()

for ext in "${EXTENSIONS[@]}"; do
    if ! php -m | grep -q "^${ext}$"; then
        MISSING+=("$ext")
        echo -e "${RED}✗ $ext${NC}"
    else
        echo -e "${GREEN}✓ $ext${NC}"
    fi
done

if [ ${#MISSING[@]} -gt 0 ]; then
    echo -e "${RED}Error: Missing required extensions: ${MISSING[*]}${NC}"
    echo "Install them with: sudo apt-get install php8.2-${MISSING[*]}"
    exit 1
fi

# Check Composer
echo ""
echo "Checking for Composer..."
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Error: Composer is not installed${NC}"
    echo "Install it from: https://getcomposer.org/download/"
    exit 1
fi
echo -e "${GREEN}✓ Composer installed${NC}"

# Install dependencies
echo ""
echo "Installing Composer dependencies..."
composer install --no-interaction --optimize-autoloader

# Create .env file
echo ""
if [ -f .env ]; then
    echo -e "${YELLOW}⚠ .env file already exists, skipping...${NC}"
else
    echo "Creating .env file from template..."
    cp .env.example .env
    echo -e "${GREEN}✓ .env created${NC}"
    echo -e "${YELLOW}⚠ Please edit .env with your configuration${NC}"
fi

# Create storage directories
echo ""
echo "Creating storage directories..."
mkdir -p storage/logs storage/pdfs storage/uploads storage/cache
touch storage/logs/.gitkeep storage/pdfs/.gitkeep storage/uploads/.gitkeep storage/cache/.gitkeep
chmod -R 755 storage
chmod -R 777 storage/logs storage/pdfs storage/uploads storage/cache
echo -e "${GREEN}✓ Storage directories created${NC}"

# Generate app key
echo ""
echo "Generating application key..."
APP_KEY=$(php -r 'echo bin2hex(random_bytes(32));')
if grep -q "^APP_KEY=$" .env; then
    sed -i "s/^APP_KEY=$/APP_KEY=$APP_KEY/" .env
    echo -e "${GREEN}✓ Application key generated${NC}"
else
    echo -e "${YELLOW}⚠ APP_KEY already set in .env${NC}"
fi

# Generate encryption key
echo "Generating encryption key..."
ENCRYPTION_KEY=$(php -r 'echo bin2hex(random_bytes(32));')
if grep -q "^ENCRYPTION_KEY=$" .env; then
    sed -i "s/^ENCRYPTION_KEY=$/ENCRYPTION_KEY=$ENCRYPTION_KEY/" .env
    echo -e "${GREEN}✓ Encryption key generated${NC}"
else
    echo -e "${YELLOW}⚠ ENCRYPTION_KEY already set in .env${NC}"
fi

# Generate session secret
echo "Generating session secret..."
SESSION_SECRET=$(php -r 'echo bin2hex(random_bytes(32));')
if grep -q "^SESSION_SECRET=$" .env; then
    sed -i "s/^SESSION_SECRET=$/SESSION_SECRET=$SESSION_SECRET/" .env
    echo -e "${GREEN}✓ Session secret generated${NC}"
else
    echo -e "${YELLOW}⚠ SESSION_SECRET already set in .env${NC}"
fi

# Summary
echo ""
echo "=================================="
echo -e "${GREEN}Installation Complete!${NC}"
echo "=================================="
echo ""
echo "Next steps:"
echo ""
echo "1. Configure database in .env:"
echo -e "   ${YELLOW}nano .env${NC}"
echo ""
echo "2. Create database:"
echo -e "   ${YELLOW}mysql -u root -p -e 'CREATE DATABASE fpdms'${NC}"
echo ""
echo "3. Run database migrations:"
echo -e "   ${YELLOW}php cli.php db:migrate${NC}"
echo ""
echo "4. Start development server:"
echo -e "   ${YELLOW}composer serve${NC}"
echo "   or"
echo -e "   ${YELLOW}php -S localhost:8080 -t public${NC}"
echo ""
echo "5. Access application:"
echo -e "   ${GREEN}http://localhost:8080${NC}"
echo ""
echo "For production setup, see:"
echo "   - STANDALONE_README.md"
echo "   - CONVERSION_SUMMARY.md"
echo ""
echo "Need help? info@francescopasseri.com"
echo ""
