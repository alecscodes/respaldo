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

# Git: Always match remote repository exactly (discard all local changes)
if [ -d .git ]; then
    log "Resetting to match remote repository exactly..."

    # Get current branch name
    CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "main")

    # Abort any ongoing merge/rebase/cherry-pick operations
    log "Aborting any ongoing git operations..."
    git merge --abort 2>/dev/null || true
    git rebase --abort 2>/dev/null || true
    git cherry-pick --abort 2>/dev/null || true

    # Fetch latest from remote
    log "Fetching latest from origin..."
    git fetch origin || warn "Failed to fetch from origin"

    # Reset hard to remote branch (discards ALL local changes)
    log "Resetting to origin/$CURRENT_BRANCH (discarding all local changes)..."
    git reset --hard "origin/$CURRENT_BRANCH" || {
        warn "Failed to reset to origin/$CURRENT_BRANCH, trying origin/main..."
        git reset --hard origin/main || warn "Failed to reset to origin/main"
    }

    # Clean untracked files and directories (optional - uncomment if you want to remove untracked files too)
    # log "Cleaning untracked files..."
    # git clean -fd || true

    # Verify we're on the correct branch and up to date
    log "Verifying repository state..."
    git checkout "$CURRENT_BRANCH" 2>/dev/null || git checkout main 2>/dev/null || true
    git reset --hard "origin/$CURRENT_BRANCH" 2>/dev/null || git reset --hard origin/main 2>/dev/null || true

    log "Repository now matches remote exactly"
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

