# =============================================================================
# Docker Helper Script for Vanessa Perfumes (PowerShell)
# =============================================================================
# Windows-compatible version of docker.sh
# Usage: .\scripts\docker.ps1 [command]
# =============================================================================

param(
    [Parameter(Position=0)]
    [string]$Command = "help",
    
    [Parameter(Position=1, ValueFromRemainingArguments=$true)]
    [string[]]$Arguments
)

# =============================================================================
# Helper Functions
# =============================================================================

function Write-Header($Text) {
    Write-Host "==========================================" -ForegroundColor Cyan
    Write-Host $Text -ForegroundColor Cyan
    Write-Host "==========================================" -ForegroundColor Cyan
}

function Write-Success($Text) {
    Write-Host "✓ $Text" -ForegroundColor Green
}

function Write-Warning($Text) {
    Write-Host "⚠ $Text" -ForegroundColor Yellow
}

function Write-Error($Text) {
    Write-Host "✗ $Text" -ForegroundColor Red
}

# =============================================================================
# Command Functions
# =============================================================================

function Start-Environment {
    Write-Header "Starting Docker Environment"
    
    if (-not (Test-Path .env)) {
        Write-Warning ".env file not found. Copying from .env.docker.example..."
        Copy-Item .env.docker.example .env
        Write-Warning "Please edit .env with your configuration before continuing."
        exit 1
    }
    
    docker compose up -d
    Write-Success "Services started successfully!"
    Write-Host ""
    Write-Host "Application: http://localhost"
    Write-Host "API: http://localhost/api/v1"
    Write-Host ""
    Get-Status
}

function Stop-Environment {
    Write-Header "Stopping Docker Environment"
    docker compose down
    Write-Success "Services stopped!"
}

function Restart-Environment {
    Write-Header "Restarting Docker Environment"
    docker compose restart
    Write-Success "Services restarted!"
}

function Build-Images {
    Write-Header "Building Docker Images"
    docker compose build --no-cache
    Write-Success "Images built successfully!"
}

function Rebuild-Environment {
    Write-Header "Rebuilding Docker Environment"
    docker compose down
    docker compose build --no-cache
    docker compose up -d
    Write-Success "Environment rebuilt!"
}

function Get-Status {
    Write-Header "Service Status"
    docker compose ps
}

function Get-Logs {
    param([string]$Service = "")
    
    if ($Service) {
        Write-Header "Showing Logs for $Service"
        docker compose logs -f --tail=100 $Service
    } else {
        Write-Header "Showing All Logs"
        docker compose logs -f --tail=100
    }
}

function Open-Shell {
    param([string]$Service = "app")
    Write-Header "Opening shell in $Service"
    docker compose exec $Service bash
}

function Invoke-Artisan {
    Write-Header "Running Artisan Command"
    $cmd = $Arguments -join " "
    docker compose exec app php artisan $cmd
}

function Invoke-Composer {
    Write-Header "Running Composer"
    $cmd = $Arguments -join " "
    docker compose exec app composer $cmd
}

function Invoke-Tests {
    Write-Header "Running Tests"
    docker compose exec app php artisan test
}

function Invoke-Migrate {
    Write-Header "Running Migrations"
    docker compose exec app php artisan migrate --force
}

function Invoke-Fresh {
    Write-Header "Fresh Migration with Seeds"
    docker compose exec app php artisan migrate:fresh --seed --force
}

function Clear-Cache {
    Write-Header "Clearing All Caches"
    docker compose exec app php artisan cache:clear
    docker compose exec app php artisan config:clear
    docker compose exec app php artisan route:clear
    docker compose exec app php artisan view:clear
    Write-Success "All caches cleared!"
}

function Optimize-Production {
    Write-Header "Optimizing for Production"
    docker compose exec app php artisan config:cache
    docker compose exec app php artisan route:cache
    docker compose exec app php artisan view:cache
    docker compose exec app php artisan event:cache
    Write-Success "Optimization complete!"
}

function Open-Database {
    Write-Header "Opening Database Shell"
    docker compose exec db mysql -u vanessa -p vanessa
}

function Open-Redis {
    Write-Header "Opening Redis CLI"
    docker compose exec redis redis-cli
}

function Backup-Database {
    Write-Header "Creating Database Backup"
    
    $BackupDir = "backups"
    $Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $BackupFile = "$BackupDir\vanessa_backup_$Timestamp.sql"
    
    if (-not (Test-Path $BackupDir)) {
        New-Item -ItemType Directory -Path $BackupDir | Out-Null
    }
    
    docker compose exec db mysqldump -u root -p vanessa > $BackupFile
    
    Write-Success "Backup created: $BackupFile"
}

function Restore-Database {
    param([string]$File)
    
    if (-not $File) {
        Write-Error "Please provide backup file path"
        Write-Host "Usage: .\scripts\docker.ps1 restore [backup_file.sql]"
        exit 1
    }
    
    Write-Header "Restoring Database from Backup"
    Get-Content $File | docker compose exec -T db mysql -u root -p vanessa
    Write-Success "Database restored!"
}

function Clean-Docker {
    Write-Header "Cleaning Docker System"
    Write-Warning "This will remove unused images, containers, and volumes!"
    
    $Response = Read-Host "Are you sure? (y/N)"
    if ($Response -match '^[Yy]$') {
        docker system prune -f
        docker volume prune -f
        Write-Success "Docker system cleaned!"
    } else {
        Write-Warning "Cancelled"
    }
}

function Update-Dependencies {
    Write-Header "Updating Dependencies"
    docker compose exec app composer update
    Write-Success "Dependencies updated!"
}

function Install-Dependencies {
    Write-Header "Installing Dependencies"
    docker compose exec app composer install
    Write-Success "Dependencies installed!"
}

function Build-Frontend {
    Write-Header "Building Frontend Assets"
    Set-Location luxury-perfume-frontend
    npm install
    npm run build
    Set-Location ..
    Write-Success "Frontend built!"
}

function Show-Help {
    Write-Host ""
    Write-Host "Vanessa Perfumes - Docker Management Script"
    Write-Host ""
    Write-Host "USAGE:"
    Write-Host "    .\scripts\docker.ps1 [COMMAND] [OPTIONS]"
    Write-Host ""
    Write-Host "COMMANDS:"
    Write-Host "    start           Start all services"
    Write-Host "    stop            Stop all services"
    Write-Host "    restart         Restart all services"
    Write-Host "    build           Build Docker images"
    Write-Host "    rebuild         Rebuild and restart all services"
    Write-Host "    status          Show service status"
    Write-Host "    logs [service]  Show logs (optionally for specific service)"
    Write-Host "    shell [service] Open shell in container (default: app)"
    Write-Host "    artisan [cmd]   Run Artisan command"
    Write-Host "    composer [cmd]  Run Composer command"
    Write-Host "    test            Run PHPUnit tests"
    Write-Host "    migrate         Run database migrations"
    Write-Host "    fresh           Fresh migration with seeds"
    Write-Host "    cache           Clear all caches"
    Write-Host "    optimize        Optimize for production"
    Write-Host "    db              Open database shell"
    Write-Host "    redis           Open Redis CLI"
    Write-Host "    backup          Create database backup"
    Write-Host "    restore [file]  Restore database from backup"
    Write-Host "    clean           Clean Docker system (prune)"
    Write-Host "    update          Update Composer dependencies"
    Write-Host "    install         Install Composer dependencies"
    Write-Host "    frontend        Build frontend assets"
    Write-Host "    help            Show this help message"
    Write-Host ""
    Write-Host "EXAMPLES:"
    Write-Host "    .\scripts\docker.ps1 start"
    Write-Host "    .\scripts\docker.ps1 logs app"
    Write-Host "    .\scripts\docker.ps1 artisan migrate"
    Write-Host "    .\scripts\docker.ps1 shell db"
    Write-Host "    .\scripts\docker.ps1 backup"
    Write-Host ""
}

# =============================================================================
# Main Script
# =============================================================================

switch ($Command) {
    "start" { Start-Environment }
    "stop" { Stop-Environment }
    "restart" { Restart-Environment }
    "build" { Build-Images }
    "rebuild" { Rebuild-Environment }
    "status" { Get-Status }
    "logs" { Get-Logs -Service $Arguments[0] }
    "shell" { Open-Shell -Service ($Arguments[0] -or "app") }
    "artisan" { Invoke-Artisan }
    "composer" { Invoke-Composer }
    "test" { Invoke-Tests }
    "migrate" { Invoke-Migrate }
    "fresh" { Invoke-Fresh }
    "cache" { Clear-Cache }
    "optimize" { Optimize-Production }
    "db" { Open-Database }
    "redis" { Open-Redis }
    "backup" { Backup-Database }
    "restore" { Restore-Database -File $Arguments[0] }
    "clean" { Clean-Docker }
    "update" { Update-Dependencies }
    "install" { Install-Dependencies }
    "frontend" { Build-Frontend }
    "help" { Show-Help }
    "--help" { Show-Help }
    "-h" { Show-Help }
    default {
        Write-Error "Unknown command: $Command"
        Show-Help
        exit 1
    }
}
