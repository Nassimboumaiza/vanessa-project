#!/bin/bash
# =============================================================================
# Docker Helper Script for Vanessa Perfumes
# =============================================================================
# Provides convenient commands for managing the Docker environment
# Usage: ./scripts/docker.sh [command]
# =============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# =============================================================================
# Helper Functions
# =============================================================================

print_header() {
    echo -e "${BLUE}==========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}==========================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# =============================================================================
# Command Functions
# =============================================================================

cmd_start() {
    print_header "Starting Docker Environment"
    
    # Check if .env exists
    if [ ! -f .env ]; then
        print_warning ".env file not found. Copying from .env.docker.example..."
        cp .env.docker.example .env
        print_warning "Please edit .env with your configuration before continuing."
        exit 1
    fi
    
    docker compose up -d
    print_success "Services started successfully!"
    echo ""
    echo "Application: http://localhost"
    echo "API: http://localhost/api/v1"
    echo ""
    cmd_status
}

cmd_stop() {
    print_header "Stopping Docker Environment"
    docker compose down
    print_success "Services stopped!"
}

cmd_restart() {
    print_header "Restarting Docker Environment"
    docker compose restart
    print_success "Services restarted!"
}

cmd_build() {
    print_header "Building Docker Images"
    docker compose build --no-cache
    print_success "Images built successfully!"
}

cmd_rebuild() {
    print_header "Rebuilding Docker Environment"
    docker compose down
    docker compose build --no-cache
    docker compose up -d
    print_success "Environment rebuilt!"
}

cmd_status() {
    print_header "Service Status"
    docker compose ps
}

cmd_logs() {
    if [ -z "$2" ]; then
        print_header "Showing All Logs"
        docker compose logs -f --tail=100
    else
        print_header "Showing Logs for $2"
        docker compose logs -f --tail=100 "$2"
    fi
}

cmd_shell() {
    SERVICE=${2:-app}
    print_header "Opening shell in $SERVICE"
    docker compose exec "$SERVICE" bash
}

cmd_artisan() {
    print_header "Running Artisan Command"
    docker compose exec app php artisan "${@:2}"
}

cmd_composer() {
    print_header "Running Composer"
    docker compose exec app composer "${@:2}"
}

cmd_test() {
    print_header "Running Tests"
    docker compose exec app php artisan test
}

cmd_migrate() {
    print_header "Running Migrations"
    docker compose exec app php artisan migrate --force
}

cmd_fresh() {
    print_header "Fresh Migration with Seeds"
    docker compose exec app php artisan migrate:fresh --seed --force
}

cmd_cache() {
    print_header "Clearing All Caches"
    docker compose exec app php artisan cache:clear
    docker compose exec app php artisan config:clear
    docker compose exec app php artisan route:clear
    docker compose exec app php artisan view:clear
    print_success "All caches cleared!"
}

cmd_optimize() {
    print_header "Optimizing for Production"
    docker compose exec app php artisan config:cache
    docker compose exec app php artisan route:cache
    docker compose exec app php artisan view:cache
    docker compose exec app php artisan event:cache
    print_success "Optimization complete!"
}

cmd_db() {
    print_header "Opening Database Shell"
    docker compose exec db mysql -u vanessa -p vanessa
}

cmd_redis() {
    print_header "Opening Redis CLI"
    docker compose exec redis redis-cli -a "${REDIS_PASSWORD:-redis_secret}"
}

cmd_backup() {
    BACKUP_DIR="backups"
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_FILE="$BACKUP_DIR/vanessa_backup_$TIMESTAMP.sql"
    
    print_header "Creating Database Backup"
    mkdir -p "$BACKUP_DIR"
    
    docker compose exec db mysqldump -u root -p"${DB_ROOT_PASSWORD:-root_secret}" vanessa > "$BACKUP_FILE"
    
    print_success "Backup created: $BACKUP_FILE"
}

cmd_restore() {
    if [ -z "$2" ]; then
        print_error "Please provide backup file path"
        echo "Usage: ./scripts/docker.sh restore <backup_file.sql>"
        exit 1
    fi
    
    print_header "Restoring Database from Backup"
    docker compose exec -T db mysql -u root -p"${DB_ROOT_PASSWORD:-root_secret}" vanessa < "$2"
    print_success "Database restored!"
}

cmd_clean() {
    print_header "Cleaning Docker System"
    print_warning "This will remove unused images, containers, and volumes!"
    read -p "Are you sure? (y/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        docker system prune -f
        docker volume prune -f
        print_success "Docker system cleaned!"
    else
        print_warning "Cancelled"
    fi
}

cmd_update() {
    print_header "Updating Dependencies"
    docker compose exec app composer update
    print_success "Dependencies updated!"
}

cmd_install() {
    print_header "Installing Dependencies"
    docker compose exec app composer install
    print_success "Dependencies installed!"
}

cmd_frontend() {
    print_header "Building Frontend Assets"
    cd luxury-perfume-frontend && npm install && npm run build
    print_success "Frontend built!"
}

cmd_help() {
    cat << EOF
Vanessa Perfumes - Docker Management Script

USAGE:
    ./scripts/docker.sh [COMMAND] [OPTIONS]

COMMANDS:
    start           Start all services
    stop            Stop all services
    restart         Restart all services
    build           Build Docker images
    rebuild         Rebuild and restart all services
    status          Show service status
    logs [service]  Show logs (optionally for specific service)
    shell [service] Open shell in container (default: app)
    artisan [cmd]   Run Artisan command
    composer [cmd]  Run Composer command
    test            Run PHPUnit tests
    migrate         Run database migrations
    fresh           Fresh migration with seeds
    cache           Clear all caches
    optimize        Optimize for production
    db              Open database shell
    redis           Open Redis CLI
    backup          Create database backup
    restore [file]  Restore database from backup
    clean           Clean Docker system (prune)
    update          Update Composer dependencies
    install         Install Composer dependencies
    frontend        Build frontend assets
    help            Show this help message

EXAMPLES:
    ./scripts/docker.sh start
    ./scripts/docker.sh logs app
    ./scripts/docker.sh artisan migrate
    ./scripts/docker.sh shell db
    ./scripts/docker.sh backup

EOF
}

# =============================================================================
# Main Script
# =============================================================================

COMMAND=${1:-help}

# Load environment variables
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
fi

case $COMMAND in
    start)
        cmd_start
        ;;
    stop)
        cmd_stop
        ;;
    restart)
        cmd_restart
        ;;
    build)
        cmd_build
        ;;
    rebuild)
        cmd_rebuild
        ;;
    status)
        cmd_status
        ;;
    logs)
        cmd_logs "$@"
        ;;
    shell)
        cmd_shell "$@"
        ;;
    artisan)
        cmd_artisan "$@"
        ;;
    composer)
        cmd_composer "$@"
        ;;
    test)
        cmd_test
        ;;
    migrate)
        cmd_migrate
        ;;
    fresh)
        cmd_fresh
        ;;
    cache)
        cmd_cache
        ;;
    optimize)
        cmd_optimize
        ;;
    db)
        cmd_db
        ;;
    redis)
        cmd_redis
        ;;
    backup)
        cmd_backup
        ;;
    restore)
        cmd_restore "$@"
        ;;
    clean)
        cmd_clean
        ;;
    update)
        cmd_update
        ;;
    install)
        cmd_install
        ;;
    frontend)
        cmd_frontend
        ;;
    help|--help|-h)
        cmd_help
        ;;
    *)
        print_error "Unknown command: $COMMAND"
        cmd_help
        exit 1
        ;;
esac
