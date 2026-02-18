# =============================================================================
# Docker Containerization Guide
# =============================================================================
# Production-grade Docker setup for Vanessa Perfumes
# =============================================================================

## Table of Contents

1. [Quick Start](#quick-start)
2. [Architecture Overview](#architecture-overview)
3. [Services](#services)
4. [Configuration](#configuration)
5. [Development Workflow](#development-workflow)
6. [Production Deployment](#production-deployment)
7. [Troubleshooting](#troubleshooting)
8. [Best Practices](#best-practices)

---

## Quick Start

### Prerequisites

- [Docker Engine](https://docs.docker.com/engine/install/) 24.0+
- [Docker Compose](https://docs.docker.com/compose/install/) 2.20+
- 4GB+ available RAM
- 10GB+ available disk space

### Initial Setup

```bash
# 1. Copy environment file
cp .env.docker.example .env

# 2. Edit .env with your settings (especially APP_KEY, DB_PASSWORD)
nano .env

# 3. Start all services
docker compose up -d

# 4. Generate APP_KEY and run migrations
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force

# 5. Visit http://localhost
```

Or use the helper script:

```bash
# Linux/macOS
./scripts/docker.sh start

# Windows PowerShell
.\scripts\docker.ps1 start
```

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                     Docker Network                        │
│              (vanessa-network: 172.25.0.0/16)            │
├─────────────────────────────────────────────────────────┤
│                                                          │
│   ┌──────────────┐       ┌──────────────┐              │
│   │     Web      │──────▶│     App      │              │
│   │   (Nginx)    │:80    │  (PHP-FPM)   │:9000         │
│   └──────────────┘       └──────┬───────┘              │
│                                 │                        │
│                        ┌────────┴────────┐              │
│                        │                 │              │
│                   ┌────▼────┐      ┌──────▼────┐        │
│                   │   DB    │      │   Redis   │        │
│                   │ (MySQL) │      │  (Cache)  │        │
│                   │ :3306   │      │  :6379    │        │
│                   └─────────┘      └──────────┘        │
│                                                         │
│   ┌─────────────────────────────────────────────────┐  │
│   │               Queue Worker                      │  │
│   │        (Processes background jobs)              │  │
│   └─────────────────────────────────────────────────┘  │
│                                                         │
│   ┌─────────────────────────────────────────────────┐  │
│   │              Scheduler (Cron)                   │  │
│   │        (Runs scheduled commands)                │  │
│   └─────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## Services

### 1. Application (PHP-FPM)

| Attribute | Value |
|-----------|-------|
| Image | `php:8.2-fpm-alpine3.19` |
| Container | `vanessa-app` |
| Port | `9000` (internal) |
| User | `www-data` (non-root) |
| Stage | Multi-stage (production/development) |

**Features:**
- Multi-stage build for optimized images
- PHP extensions: pdo_mysql, gd, redis, opcache, zip, bcmath, intl
- OPcache JIT enabled for production
- Xdebug available in development stage

### 2. Web Server (Nginx)

| Attribute | Value |
|-----------|-------|
| Image | `nginx:1.25-alpine3.18` |
| Container | `vanessa-web` |
| Ports | `80`, `443` |
| User | `nginx` (non-root) |

**Features:**
- Reverse proxy to PHP-FPM
- Rate limiting (10 req/s API, 5 req/min auth)
- Security headers (CSP, XSS, CSRF protection)
- Static file caching (1 year for assets)

### 3. Database (MySQL 8.0)

| Attribute | Value |
|-----------|-------|
| Image | `mysql:8.0` |
| Container | `vanessa-db` |
| Port | `3306` |
| Charset | `utf8mb4_unicode_ci` |

**Features:**
- InnoDB with optimized buffer pool
- Persistent volume for data
- Health checks enabled
- Production-ready MySQL configuration

### 4. Cache/Queue (Redis)

| Attribute | Value |
|-----------|-------|
| Image | `redis:7-alpine` |
| Container | `vanessa-redis` |
| Port | `6379` |
| Auth | Password protected |

**Features:**
- AOF persistence enabled
- Max memory policy: allkeys-lru
- Used for: cache, sessions, queues

### 5. Queue Worker

| Attribute | Value |
|-----------|-------|
| Container | `vanessa-queue` |
| Command | `php artisan queue:work` |

**Features:**
- Processes background jobs continuously
- Auto-restarts on failure
- Same codebase as app container

### 6. Scheduler

| Attribute | Value |
|-----------|-------|
| Container | `vanessa-scheduler` |
| Command | `php artisan schedule:run` every minute |

**Features:**
- Runs Laravel scheduled tasks
- No cron daemon needed (loop-based)

---

## Configuration

### Environment Variables

Create `.env` file from `.env.docker.example`:

```bash
cp .env.docker.example .env
```

**Critical Settings:**

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_KEY` | Yes | Laravel app key (generate with `openssl rand -base64 32`) |
| `APP_ENV` | Yes | `local` (dev) or `production` |
| `DB_PASSWORD` | Yes | MySQL user password |
| `DB_ROOT_PASSWORD` | Yes | MySQL root password |
| `REDIS_PASSWORD` | Yes | Redis authentication password |
| `DOCKER_TARGET` | No | `development` or `production` |

### Port Mapping

| Service | Container Port | Host Port (default) |
|---------|---------------|-------------------|
| Nginx | 80 | 80 |
| Nginx | 443 | 443 |
| MySQL | 3306 | 3306 |
| Redis | 6379 | 6379 |

Change in `.env`:
```bash
HTTP_PORT=8080
DB_PORT=3307
```

---

## Development Workflow

### Start Development Environment

```bash
# Using docker compose directly
docker compose up -d

# Or with helper script
./scripts/docker.sh start
```

### Run Artisan Commands

```bash
# Direct
docker compose exec app php artisan migrate

# Or with helper
./scripts/docker.sh artisan migrate
```

### Run Tests

```bash
# All tests
docker compose exec app php artisan test

# Or
./scripts/docker.sh test
```

### Access Container Shell

```bash
# App container
docker compose exec app bash

# Database
docker compose exec db bash

# Or with helper
./scripts/docker.sh shell app
./scripts/docker.sh shell db
```

### View Logs

```bash
# All logs
docker compose logs -f

# Specific service
docker compose logs -f app

# Or with helper
./scripts/docker.sh logs
./scripts/docker.sh logs app
```

### Database Operations

```bash
# Backup
docker compose exec db mysqldump -u root -p vanessa > backup.sql

# Or with helper
./scripts/docker.sh backup

# Restore
docker compose exec -T db mysql -u root -p vanessa < backup.sql

# Or with helper
./scripts/docker.sh restore backup.sql
```

### Rebuild After Code Changes

```bash
# If you modify Dockerfiles
docker compose down
docker compose build --no-cache
docker compose up -d

# Or with helper
./scripts/docker.sh rebuild
```

---

## Production Deployment

### Build Production Images

```bash
# Set production target
export DOCKER_TARGET=production

# Build
docker compose build

# Or with explicit target
docker compose build --build-arg DOCKER_TARGET=production
```

### Optimize for Production

```bash
# Inside container
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan event:cache

# Or with helper
./scripts/docker.sh optimize
```

### Push to Registry

```bash
# Tag images
docker tag vanessa-app:latest your-registry/vanessa-app:v1.0.0
docker tag vanessa-web:latest your-registry/vanessa-web:v1.0.0

# Push
docker push your-registry/vanessa-app:v1.0.0
docker push your-registry/vanessa-web:v1.0.0
```

### Production Environment Variables

```bash
APP_ENV=production
APP_DEBUG=false
DOCKER_TARGET=production
RUN_MIGRATIONS=true
```

---

## Troubleshooting

### Container Won't Start

```bash
# Check logs
docker compose logs [service]

# Check status
docker compose ps

# Rebuild
docker compose down
docker compose build --no-cache
docker compose up -d
```

### Database Connection Issues

```bash
# Verify MySQL is running
docker compose exec db mysqladmin ping

# Check credentials
docker compose exec db mysql -u vanessa -p -e "SELECT 1"

# Reset data (WARNING: destroys data)
docker compose down -v
docker compose up -d
```

### Permission Issues

```bash
# Fix storage permissions
docker compose exec app chown -R www-data:www-data /var/www/html/storage

# Or on host (Linux/macOS)
sudo chown -R $USER:$USER backend/storage
```

### Port Already in Use

```bash
# Change ports in .env
HTTP_PORT=8080
DB_PORT=3307

# Restart
docker compose up -d
```

### Clear Everything and Start Fresh

```bash
# Stop and remove everything
docker compose down -v --rmi all

# Remove unused images
docker system prune -f

# Start fresh
docker compose up -d
```

---

## Best Practices

### 1. Security

- **Never commit `.env` files** to version control
- Use strong passwords for DB and Redis
- Run containers as non-root users
- Keep base images updated
- Use secrets management in production (Docker Swarm/Kubernetes)

### 2. Performance

- Enable OPcache in production
- Use Redis for cache/sessions
- Optimize MySQL buffer pool size
- Implement database indexing
- Use CDN for static assets

### 3. Monitoring

```bash
# Check resource usage
docker stats

# Health checks are built-in:
curl http://localhost/health      # Nginx
docker compose exec app php -r "echo 'OK';"  # PHP
```

### 4. Backup Strategy

```bash
# Automated daily backup (add to cron)
0 2 * * * cd /path/to/vanessa && ./scripts/docker.sh backup
```

### 5. Updates

```bash
# Update images
docker compose pull
docker compose up -d

# Update application
docker compose exec app composer update
docker compose exec app php artisan migrate
```

---

## Helper Commands Reference

### Linux/macOS (`./scripts/docker.sh`)

| Command | Description |
|---------|-------------|
| `start` | Start all services |
| `stop` | Stop all services |
| `restart` | Restart services |
| `build` | Build images |
| `rebuild` | Full rebuild |
| `status` | Show status |
| `logs [service]` | View logs |
| `shell [service]` | Open shell |
| `artisan [cmd]` | Run Artisan |
| `composer [cmd]` | Run Composer |
| `test` | Run tests |
| `migrate` | Run migrations |
| `fresh` | Fresh migrate + seed |
| `cache` | Clear caches |
| `optimize` | Optimize production |
| `db` | Database shell |
| `redis` | Redis CLI |
| `backup` | Backup database |
| `restore [file]` | Restore backup |
| `clean` | Clean Docker system |
| `update` | Update dependencies |
| `install` | Install dependencies |
| `frontend` | Build frontend |

### Windows (`./scripts/docker.ps1`)

Same commands as Linux/macOS version.

---

## File Structure

```
.
├── docker/
│   ├── php/
│   │   ├── Dockerfile
│   │   ├── entrypoint.sh
│   │   ├── php.ini-production
│   │   ├── php.ini-development
│   │   ├── opcache.ini
│   │   └── php-fpm.d/
│   │       └── www.conf
│   ├── nginx/
│   │   ├── Dockerfile
│   │   ├── nginx.conf
│   │   └── conf.d/
│   │       └── app.conf
│   ├── mysql/
│   │   └── my.cnf
│   └── redis/
│       └── redis.conf
├── docker-compose.yml
├── .env.docker.example
├── .dockerignore
└── scripts/
    ├── docker.sh
    └── docker.ps1
```

---

## Support

- **Docker Docs**: https://docs.docker.com/
- **Laravel Docs**: https://laravel.com/docs
- **Compose File Reference**: https://docs.docker.com/compose/compose-file/

---

*Built for production, designed for scale.*
