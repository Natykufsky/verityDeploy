#!/bin/bash

# deploy.monaksoft.com.ng cPanel Deployment Setup Script
# This script automates the initial setup for the deploy.monaksoft.com.ng cPanel site
# Run this on your cPanel server via SSH

set -euo pipefail

# Resolve common tools from the current server environment.
COMPOSER_BIN="$(command -v composer || true)"
PHP_BIN="$(command -v php || true)"
NPM_BIN="$(command -v npm || true)"
COMPOSER_PHAR="$HOME/bin/composer"

if [ -z "$COMPOSER_BIN" ]; then
    if [ -x /opt/cpanel/composer/bin/composer ]; then
        COMPOSER_BIN="/opt/cpanel/composer/bin/composer"
    elif [ -x /usr/local/bin/composer ]; then
        COMPOSER_BIN="/usr/local/bin/composer"
    fi
fi

if [ -z "$COMPOSER_BIN" ]; then
    echo -e "${YELLOW}Composer was not found. Bootstrapping a local Composer binary...${NC}"
    mkdir -p "$HOME/bin"
    if [ ! -f "$COMPOSER_PHAR" ]; then
        if command -v curl >/dev/null 2>&1; then
            curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
        elif command -v wget >/dev/null 2>&1; then
            wget -qO /tmp/composer-setup.php https://getcomposer.org/installer
        else
            echo -e "${RED}Neither curl nor wget is available to download Composer.${NC}"
            exit 1
        fi

        "$PHP_BIN" /tmp/composer-setup.php --install-dir="$HOME/bin" --filename=composer
        rm -f /tmp/composer-setup.php
    fi
    COMPOSER_BIN="$COMPOSER_PHAR"
fi

if [ -z "$PHP_BIN" ] && [ -x /usr/local/bin/php ]; then
    PHP_BIN="/usr/local/bin/php"
fi

if [ -z "$PHP_BIN" ]; then
    echo -e "${RED}PHP CLI was not found. Install PHP or make sure it is available in PATH.${NC}"
    exit 1
fi

# Color codes for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== deploy.monaksoft.com.ng cPanel Setup Script ===${NC}"
echo ""

# Configuration variables - MODIFY THESE for deploy.monaksoft.com.ng
CPANEL_USERNAME="thenew12"
PROJECT_SLUG="verityDeploy"
DEPLOYMENT_PATH="/home/${CPANEL_USERNAME}/public_html/deploy.monaksoft.com.ng"
REPO_PATH="/home/${CPANEL_USERNAME}/repositories/${PROJECT_SLUG}"
DB_NAME="veritydeploy_prod"
DB_USER="veritydeploy_user"
DOMAIN="deploy.monaksoft.com.ng"

echo -e "${YELLOW}Configuration:${NC}"
echo "  cPanel Username: $CPANEL_USERNAME"
echo "  Deployment Path: $DEPLOYMENT_PATH"
echo "  Repository Path: $REPO_PATH"
echo "  Database Name: $DB_NAME"
echo "  Database User: $DB_USER"
echo "  Domain: $DOMAIN"
echo ""

# Step 1: Create directories
echo -e "${YELLOW}Step 1: Creating directories...${NC}"
mkdir -p "$REPO_PATH"
mkdir -p "$DEPLOYMENT_PATH"
mkdir -p "/home/${CPANEL_USERNAME}/public_html"
echo -e "${GREEN}✓ Directories created${NC}"
echo ""

# Step 2: Clone repository (if not already cloned)
if [ ! -d "$REPO_PATH/.git" ]; then
echo -e "${YELLOW}Step 2: Cloning repository for deploy.monaksoft.com.ng...${NC}"
    git clone https://github.com/Natykufsky/verityDeploy.git "$REPO_PATH"
    echo -e "${GREEN}✓ Repository cloned${NC}"
else
    echo -e "${YELLOW}Step 2: Repository already exists, pulling latest changes for deploy.monaksoft.com.ng...${NC}"
    cd "$REPO_PATH"
    git reset --hard HEAD
    git clean -fdx
    git pull --ff-only origin main
    echo -e "${GREEN}✓ Repository updated${NC}"
fi
echo ""

# Step 3: Sync repository contents to deployment path
echo -e "${YELLOW}Step 3: Syncing repository to deploy.monaksoft.com.ng...${NC}"
if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete --exclude='.git' "$REPO_PATH"/ "$DEPLOYMENT_PATH"/
else
    rm -rf "$DEPLOYMENT_PATH"/*
    rm -rf "$DEPLOYMENT_PATH"/.[!.]* "$DEPLOYMENT_PATH"/..?* 2>/dev/null || true
    cp -R "$REPO_PATH"/. "$DEPLOYMENT_PATH"
    rm -rf "$DEPLOYMENT_PATH/.git"
fi
echo -e "${GREEN}✓ Files synced${NC}"
echo ""

# Step 4: Set permissions
echo -e "${YELLOW}Step 4: Setting permissions...${NC}"
chmod -R 755 "$DEPLOYMENT_PATH"
chmod -R 755 "$DEPLOYMENT_PATH/storage"
chmod -R 755 "$DEPLOYMENT_PATH/bootstrap/cache"
chown -R "$CPANEL_USERNAME:$CPANEL_USERNAME" "$DEPLOYMENT_PATH"
echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

# Step 5: Setup .env file
echo -e "${YELLOW}Step 5: Setting up .env file for deploy.monaksoft.com.ng...${NC}"
if [ ! -f "$DEPLOYMENT_PATH/.env" ]; then
    cp "$DEPLOYMENT_PATH/.env.example" "$DEPLOYMENT_PATH/.env"
    echo -e "${GREEN}✓ .env file created${NC}"
else
    echo -e "${YELLOW}! .env file already exists, skipping${NC}"
fi
chmod 600 "$DEPLOYMENT_PATH/.env"
echo ""

# Step 6: Install PHP dependencies
echo -e "${YELLOW}Step 6: Installing PHP dependencies for deploy.monaksoft.com.ng...${NC}"
cd "$DEPLOYMENT_PATH"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
echo -e "${GREEN}✓ PHP dependencies installed${NC}"
echo ""

# Step 7: Install Node.js dependencies and build
echo -e "${YELLOW}Step 7: Installing Node.js dependencies and building assets for deploy.monaksoft.com.ng...${NC}"
if [ -n "$NPM_BIN" ]; then
    "$NPM_BIN" ci --omit=dev
    "$NPM_BIN" run build
    echo -e "${GREEN}✓ Assets built${NC}"
else
    echo -e "${RED}! npm not found. Please ensure Node.js 18+ is installed via cPanel${NC}"
fi
echo ""

# Step 8: Generate application key
echo -e "${YELLOW}Step 8: Generating application key for deploy.monaksoft.com.ng...${NC}"
if ! grep -q "^APP_KEY=base64:" "$DEPLOYMENT_PATH/.env"; then
    "$PHP_BIN" artisan key:generate --force
    echo -e "${GREEN}✓ Application key generated${NC}"
else
    echo -e "${YELLOW}! Application key already exists${NC}"
fi
echo ""

# Step 9: Copy .htaccess
echo -e "${YELLOW}Step 9: Setting up .htaccess for deploy.monaksoft.com.ng...${NC}"
if [ -f "$REPO_PATH/PUBLIC_HTACCESS" ]; then
    cp "$REPO_PATH/PUBLIC_HTACCESS" "$DEPLOYMENT_PATH/public/.htaccess"
    echo -e "${GREEN}✓ .htaccess installed${NC}"
fi
echo ""

# Step 10: Display next steps
echo -e "${GREEN}=== deploy.monaksoft.com.ng Setup Complete ===${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Configure database in cPanel MySQL Databases"
echo "   Database: $DB_NAME"
echo "   User: $DB_USER"
echo ""
echo "2. Update .env file with production settings:"
echo "   nano $DEPLOYMENT_PATH/.env"
echo ""
echo "3. Update these variables in .env:"
echo "   - DB_HOST, DB_PASSWORD (your MySQL credentials)"
echo "   - APP_URL=https://$DOMAIN"
echo "   - APP_KEY (will be auto-generated)"
echo "   - MAIL settings"
echo "   - GITHUB_* (if using GitHub webhooks)"
echo ""
echo "4. Run database migrations:"
echo "   cd $DEPLOYMENT_PATH && php artisan migrate --force"
echo ""
echo "5. Configure Document Root in cPanel:"
echo "   Set to: $DEPLOYMENT_PATH/public"
echo ""
echo "6. Setup SSL Certificate (Let's Encrypt)"
echo ""
echo "7. Setup cron job for Laravel scheduler:"
echo "   * * * * * cd $DEPLOYMENT_PATH && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1"
echo ""
echo -e "${YELLOW}For more information, see CPANEL_DEPLOYMENT_GUIDE.md for deploy.monaksoft.com.ng${NC}"
