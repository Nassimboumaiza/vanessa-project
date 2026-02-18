#!/bin/sh
# =============================================================================
# Laravel Application Entrypoint Script
# =============================================================================
# Handles initialization tasks before starting PHP-FPM
# =============================================================================

set -e

echo "=========================================="
echo "Vanessa Perfumes - Laravel Application"
echo "Container Initialization"
echo "=========================================="
echo ""

# -----------------------------------------------------------------------------
# Wait for services to be ready
# -----------------------------------------------------------------------------
echo "[1/6] Waiting for database connection..."
until nc -z -v -w30 db 3306 2>/dev/null; do
    echo "Waiting for MySQL at db:3306..."
    sleep 2
done
echo "MySQL is ready!"

echo "[2/6] Waiting for Redis..."
until nc -z -v -w30 redis 6379 2>/dev/null; do
    echo "Waiting for Redis at redis:6379..."
    sleep 1
done
echo "Redis is ready!"

# -----------------------------------------------------------------------------
# Ensure proper permissions (as www-data user)
# -----------------------------------------------------------------------------
echo "[3/6] Setting up storage permissions..."
if [ "$USER" = "www-data" ]; then
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache
    chmod -R 755 storage bootstrap/cache 2>/dev/null || true
fi

# -----------------------------------------------------------------------------
# Environment setup
# -----------------------------------------------------------------------------
echo "[4/6] Checking environment configuration..."

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "APP_KEY not set. Generating new key..."
    php artisan key:generate --force --no-interaction 2>/dev/null || echo "Note: APP_KEY generation requires .env file"
fi

# -----------------------------------------------------------------------------
# Database migrations (optional, controlled by env var)
# -----------------------------------------------------------------------------
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "[5/6] Running database migrations..."
    php artisan migrate --force --no-interaction --verbose
else
    echo "[5/6] Skipping migrations (set RUN_MIGRATIONS=true to enable)"
fi

# -----------------------------------------------------------------------------
# Optimization commands (for production)
# -----------------------------------------------------------------------------
if [ "$APP_ENV" = "production" ]; then
    echo "[6/6] Running production optimizations..."
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
    php artisan event:cache --no-interaction
else
    echo "[6/6] Development mode - skipping optimizations"
    # Clear any cached configs in development
    php artisan config:clear --no-interaction 2>/dev/null || true
    php artisan route:clear --no-interaction 2>/dev/null || true
    php artisan view:clear --no-interaction 2>/dev/null || true
fi

echo ""
echo "=========================================="
echo "Initialization complete!"
echo "Starting application..."
echo "=========================================="
echo ""

# -----------------------------------------------------------------------------
# Execute the main command (php-fpm or custom command)
# -----------------------------------------------------------------------------
exec "$@"
