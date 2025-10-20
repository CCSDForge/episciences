#!/bin/bash
set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to log messages
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Validate input parameter
if [ -z "$1" ]; then
    log_error "Branch or tag name is required as the first argument"
    echo "Usage: $0 <branch-or-tag-name>"
    exit 1
fi

BRANCH="$1"
log "Starting deployment of: $BRANCH"

# Detect PHP binary
if test -f "/usr/bin/php8.1"; then
    PHP_BIN="/usr/bin/php8.1"
    log "Using PHP 8.1: $PHP_BIN"
elif test -f "/usr/bin/php"; then
    PHP_BIN="/usr/bin/php"
    log "Using system PHP: $PHP_BIN"
else
    log_error "No PHP binary found"
    exit 1
fi

# Check if we're in a git repository
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    log_error "Not in a git repository"
    exit 1
fi

# Check if it's a tag or branch
IS_TAG=false
IS_BRANCH=false

if git ls-remote --exit-code --tags origin "refs/tags/$BRANCH" > /dev/null 2>&1; then
    IS_TAG=true
    log "Detected '$BRANCH' as a tag"
elif git ls-remote --exit-code --heads origin "$BRANCH" > /dev/null 2>&1; then
    IS_BRANCH=true
    log "Detected '$BRANCH' as a branch"
else
    log_error "Neither branch nor tag '$BRANCH' exists on remote"
    exit 1
fi

deployDate=$(date "+%Y-%m-%d %X %z")

log "Fetching latest changes..."
if ! git fetch --all; then
    log_error "Failed to fetch from remote"
    exit 1
fi

if ! git fetch --tags --force; then
    log_warning "Failed to fetch some tags (this may be normal if tags were moved)"
fi

if [ "$IS_TAG" = true ]; then
    log "Checking out tag: $BRANCH"
    if ! git checkout "tags/$BRANCH"; then
        log_error "Failed to checkout tag: $BRANCH"
        exit 1
    fi
    log "Tag '$BRANCH' checked out successfully (detached HEAD state)"
else
    log "Checking out branch: $BRANCH"
    if ! git checkout "$BRANCH"; then
        log_error "Failed to checkout branch: $BRANCH"
        exit 1
    fi
    
    log "Checking for local modifications..."
    if ! git diff-index --quiet HEAD --; then
        log_warning "Local modifications detected. Stashing changes before pull..."
        
        # Stash local changes
        if ! git stash push -m "Auto-stash by deployment script $(date)"; then
            log_error "Failed to stash local changes"
            exit 1
        fi
        
        STASHED_CHANGES=true
        log "Local changes stashed successfully"
    else
        STASHED_CHANGES=false
    fi
    
    log "Pulling latest changes for branch: $BRANCH"
    if ! git pull; then
        log_error "Failed to pull latest changes"
        exit 1
    fi
    
    # For production deployments, we keep stashed changes but don't restore them
    if [ "$STASHED_CHANGES" = true ]; then
        log "Local changes have been stashed for deployment cleanliness"
        log "Stashed changes are preserved in git stash - use 'git stash list' to view them"
        log "Use 'git stash pop' if you need to restore them later"
    fi
fi

gitHashCommit=$(git rev-parse --short HEAD)
if [ -z "$gitHashCommit" ]; then
    log_error "Failed to get git hash for current HEAD"
    exit 1
fi

log "Creating version.php file (commit: $gitHashCommit)"
cat > version.php << EOF
<?php
\$gitHash='$gitHashCommit';
\$gitBranch='$BRANCH';
\$deployDate='$deployDate';
EOF

if [ ! -f "version.php" ]; then
    log_error "Failed to create version.php"
    exit 1
fi

# Check if composer.phar exists
if [ ! -f "composer.phar" ]; then
    log_error "composer.phar not found"
    exit 1
fi

log "Installing Composer dependencies..."
COMPOSER_OUTPUT=$($PHP_BIN composer.phar install -o --no-dev --no-interaction 2>&1)
COMPOSER_EXIT_CODE=$?

if [ $COMPOSER_EXIT_CODE -ne 0 ]; then
    log_error "Composer install failed"
    echo "$COMPOSER_OUTPUT"
    exit 1
fi

# Check for lock file warning
if echo "$COMPOSER_OUTPUT" | grep -q "lock file is not up to date"; then
    log_warning "Composer lock file is outdated - consider running 'composer update' in development"
fi

# Check if package.json exists
if [ ! -f "package.json" ]; then
    log_warning "package.json not found, skipping yarn operations"
else
    # Check for yarn.lock modifications before install
    YARN_LOCK_BEFORE=""
    if [ -f "yarn.lock" ]; then
        YARN_LOCK_BEFORE=$(md5sum yarn.lock 2>/dev/null || echo "")
    fi

    log "Installing Node.js dependencies..."
    # Use --immutable for production deployments to avoid modifications
    if ! yarn install --immutable; then
        log_warning "Immutable install failed, trying normal install..."
        if ! yarn install; then
            log_error "yarn install failed"
            exit 1
        fi
        
        # Check if yarn.lock was modified
        if [ -f "yarn.lock" ] && [ -n "$YARN_LOCK_BEFORE" ]; then
            YARN_LOCK_AFTER=$(md5sum yarn.lock 2>/dev/null || echo "")
            if [ "$YARN_LOCK_BEFORE" != "$YARN_LOCK_AFTER" ]; then
                log_warning "yarn.lock was modified during install"
                log "Consider updating yarn.lock in your source branch"
                
                # Show what changed
                if git diff --name-only | grep -q "yarn.lock"; then
                    log "Changes in yarn.lock:"
                    git diff --stat yarn.lock
                fi
            fi
        fi
    fi

    log "Building production assets..."
    if ! yarn encore production; then
        log_error "yarn encore production failed"
        exit 1
    fi
fi

log_success "Deployment completed successfully!"
if [ "$IS_TAG" = true ]; then
    log "Tag: $BRANCH"
else
    log "Branch: $BRANCH"
fi
log "Commit: $gitHashCommit"
log "Deploy time: $deployDate"

