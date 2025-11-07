#!/bin/sh

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[DEPLOY]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }

# Check if .env exists, create from .env.example if not
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        log "Creating .env from .env.example..."
        cp .env.example .env
    else
        warn ".env.example not found. Please create .env manually."
        exit 1
    fi
fi

# Prompt for APP_URL if missing
if ! grep -q "^APP_URL=" .env || grep -q "^APP_URL=$" .env; then
    read -p "APP_URL [http://localhost:8000]: " app_url
    app_url=${app_url:-http://localhost:8000}
    if grep -q "^APP_URL=" .env; then
        sed -i.bak "s|^APP_URL=.*|APP_URL=$app_url|" .env
    else
        echo "APP_URL=$app_url" >> .env
    fi
    log "APP_URL set to: $app_url"
fi

# Prompt for BACKUP_VOLUME if missing
if ! grep -q "^BACKUP_VOLUME=" .env || grep -q "^BACKUP_VOLUME=$" .env; then
    read -p "BACKUP_VOLUME [./backups]: " backup_volume
    backup_volume=${backup_volume:-./backups}
    if grep -q "^BACKUP_VOLUME=" .env; then
        sed -i.bak "s|^BACKUP_VOLUME=.*|BACKUP_VOLUME=$backup_volume|" .env
    else
        echo "BACKUP_VOLUME=$backup_volume" >> .env
    fi
    log "BACKUP_VOLUME set to: $backup_volume"
fi

# Clean up backup files created by sed
rm -f .env.bak

# Git pull if updating
if [ -d .git ]; then
    log "Pulling latest changes..."
    git pull || warn "Git pull failed or not a git repository"
fi

# Install dependencies
log "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

log "Installing NPM dependencies..."
npm ci

# Build assets
log "Building assets..."
npm run build

# Generate key if missing
if ! grep -q "^APP_KEY=base64:" .env; then
    log "Generating application key..."
    php artisan key:generate --force
fi

# Create SQLite database if needed
if [ ! -f database/database.sqlite ]; then
    log "Creating SQLite database..."
    touch database/database.sqlite
fi

# Run migrations
log "Running migrations..."
php artisan migrate --force

# Clear and optimize
log "Clearing caches..."
php artisan optimize:clear

log "Optimizing application..."
php artisan optimize
composer dump-autoload --optimize --no-interaction

log "Deployment completed successfully! 🚀"

