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

# Prompt for APP_URL only if it's the same as example (http://localhost)
current_app_url=$(grep "^APP_URL=" .env | cut -d '=' -f2- 2>/dev/null || echo "")
if [ "$current_app_url" = "http://localhost" ]; then
    read -p "APP_URL [http://localhost:8000]: " app_url
    app_url=${app_url:-http://localhost:8000}
    sed -i.bak "s|^APP_URL=.*|APP_URL=$app_url|" .env
    log "APP_URL set to: $app_url"

    # Prompt for BACKUP_VOLUME only if it's empty or default value
    current_backup_volume=$(grep "^BACKUP_VOLUME=" .env | cut -d '=' -f2- 2>/dev/null || echo "")

    if [ -z "$current_backup_volume" ] || [ "$current_backup_volume" = "./backups" ]; then
        read -p "BACKUP_VOLUME [./backups]: " backup_volume
        backup_volume=${backup_volume:-./backups}
        sed -i.bak "s|^BACKUP_VOLUME=.*|BACKUP_VOLUME=$backup_volume|" .env
        log "BACKUP_VOLUME set to: $backup_volume"
    fi
fi

# Clean up backup files created by sed
rm -f .env.bak

# Git pull if updating (stash local changes if needed)
if [ -d .git ]; then
    log "Pulling latest changes..."
    STASHED=false

    # Try to pull first (use merge strategy for divergent branches)
    PULL_OUTPUT=$(git pull --no-rebase 2>&1)
    PULL_EXIT=$?

    # Check if pull failed due to local changes
    if [ $PULL_EXIT -ne 0 ] && echo "$PULL_OUTPUT" | grep -q "would be overwritten by merge"; then
        # Pull failed due to local changes - stash and retry
        warn "Local changes detected, stashing them..."
        git stash push -m "Deploy script auto-stash $(date +%Y-%m-%d_%H:%M:%S)" || true
        STASHED=true

        # Pull again with merge strategy
        if git pull --no-rebase; then
            log "Successfully pulled latest changes"
        else
            warn "Git pull failed after stashing"
        fi

        # Discard stashed changes (deployment should use remote version)
        if [ "$STASHED" = "true" ]; then
            warn "Discarding local changes in favor of remote version..."
            git stash drop || true
        fi
    # Check if pull failed due to divergent branches
    elif [ $PULL_EXIT -ne 0 ] && (echo "$PULL_OUTPUT" | grep -q "divergent branches" || echo "$PULL_OUTPUT" | grep -q "Need to specify how to reconcile"); then
        warn "Divergent branches detected, fetching and merging remote changes..."
        # Fetch first to get remote changes
        git fetch origin || true
        CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
        # Try to merge with remote (prefer remote in case of conflicts for deployment)
        if git merge -X theirs "origin/$CURRENT_BRANCH" 2>/dev/null; then
            log "Successfully merged remote changes (preferred remote version)"
        elif git merge "origin/$CURRENT_BRANCH" 2>/dev/null; then
            log "Successfully merged remote changes"
        else
            warn "Merge failed - resetting to remote version for deployment..."
            # For deployment, prefer remote version - reset to remote
            git reset --hard "origin/$CURRENT_BRANCH" || warn "Reset failed"
        fi
    elif [ $PULL_EXIT -eq 0 ]; then
        log "Successfully pulled latest changes"
    else
        warn "Git pull failed: $PULL_OUTPUT"
    fi
fi

# Install dependencies
log "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

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

# Clear config cache
log "Clearing configuration cache..."
php artisan config:clear || true

# Run migrations
log "Running migrations..."
php artisan migrate --force

# Install NPM dependencies
log "Installing NPM dependencies..."
npm ci

# Build assets
log "Building assets..."
npm run build

# Clear and optimize
log "Clearing caches..."
php artisan optimize:clear

log "Optimizing application..."
php artisan optimize
composer dump-autoload --optimize --no-interaction

log "Deployment completed successfully! 🚀"

