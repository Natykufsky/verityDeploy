#!/bin/bash

# verityDeploy cPanel Deployment Setup Script
# This script automates the initial setup for cPanel hosting
# Run this on your cPanel server via SSH

set -e

# Color codes for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== verityDeploy cPanel Setup Script ===${NC}"
echo ""

# Configuration variables - MODIFY THESE
CPANEL_USERNAME="monaksof"
DEPLOYMENT_PATH="/home/${CPANEL_USERNAME}/public_html/verityDeploy"
REPO_PATH="/home/${CPANEL_USERNAME}/repositories/verityDeploy"
DB_NAME="veritydeploy_prod"
DB_USER="veritydeploy_user"
DOMAIN="yourdomain.com"

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
    echo -e "${YELLOW}Step 2: Cloning repository...${NC}"
    git clone https://github.com/Natykufsky/verityDeploy.git "$REPO_PATH"
    echo -e "${GREEN}✓ Repository cloned${NC}"
else
    echo -e "${YELLOW}Step 2: Repository already exists, pulling latest changes...${NC}"
    cd "$REPO_PATH"
    git pull origin main
    echo -e "${GREEN}✓ Repository updated${NC}"
fi
echo ""

# Step 3: Copy files to deployment path
echo -e "${YELLOW}Step 3: Copying files to deployment path...${NC}"
cp -R "$REPO_PATH"/* "$DEPLOYMENT_PATH"
cp -R "$REPO_PATH"/.* "$DEPLOYMENT_PATH" 2>/dev/null || true
echo -e "${GREEN}✓ Files copied${NC}"
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
echo -e "${YELLOW}Step 5: Setting up .env file...${NC}"
if [ ! -f "$DEPLOYMENT_PATH/.env" ]; then
    cp "$DEPLOYMENT_PATH/.env.example" "$DEPLOYMENT_PATH/.env"
    echo -e "${GREEN}✓ .env file created${NC}"
else
    echo -e "${YELLOW}! .env file already exists, skipping${NC}"
fi
chmod 600 "$DEPLOYMENT_PATH/.env"
echo ""

# Step 6: Install PHP dependencies
echo -e "${YELLOW}Step 6: Installing PHP dependencies...${NC}"
cd "$DEPLOYMENT_PATH"
/usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
echo -e "${GREEN}✓ PHP dependencies installed${NC}"
echo ""

# Step 7: Install Node.js dependencies and build
echo -e "${YELLOW}Step 7: Installing Node.js dependencies and building assets...${NC}"
if command -v npm &> /dev/null; then
    /bin/npm install --production
    /bin/npm run build
    echo -e "${GREEN}✓ Assets built${NC}"
else
    echo -e "${RED}! npm not found. Please ensure Node.js 18+ is installed via cPanel${NC}"
fi
echo ""

# Step 8: Generate application key
echo -e "${YELLOW}Step 8: Generating application key...${NC}"
if ! grep -q "^APP_KEY=base64:" "$DEPLOYMENT_PATH/.env"; then
    /usr/local/bin/php artisan key:generate --force
    echo -e "${GREEN}✓ Application key generated${NC}"
else
    echo -e "${YELLOW}! Application key already exists${NC}"
fi
echo ""

# Step 9: Copy .htaccess
echo -e "${YELLOW}Step 9: Setting up .htaccess...${NC}"
if [ -f "$REPO_PATH/PUBLIC_HTACCESS" ]; then
    cp "$REPO_PATH/PUBLIC_HTACCESS" "$DEPLOYMENT_PATH/public/.htaccess"
    echo -e "${GREEN}✓ .htaccess installed${NC}"
fi
echo ""

# Step 10: Display next steps
echo -e "${GREEN}=== Initial Setup Complete ===${NC}"
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
echo -e "${YELLOW}For more information, see CPANEL_DEPLOYMENT_GUIDE.md${NC}"
