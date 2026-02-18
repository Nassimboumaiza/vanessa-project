# CI/CD Pipeline & Deployment Strategy

## Overview

This document outlines the complete CI/CD pipeline and deployment strategy for the Vanessa Perfumes platform, a full-stack application with Next.js frontend and Laravel backend.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           CI/CD PIPELINE                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐        │
│  │   Code   │────▶│    CI    │────▶│   CD     │────▶│ Deploy   │        │
│  │  Commit  │     │ (Tests)  │     │ (Build)  │     │ (Ship)   │        │
│  └──────────┘     └──────────┘     └──────────┘     └──────────┘        │
│       │                                          │                      │
│       ▼                                          ▼                      │
│  ┌──────────┐                              ┌──────────┐                │
│  │  PR to   │                              │ Staging  │                │
│  │ develop  │                              │ / Prod   │                │
│  └──────────┘                              └──────────┘                │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

## GitHub Actions Workflows

### 1. CI Workflow (`.github/workflows/ci.yml`)

**Triggers:**
- Pull requests to `main` and `develop` branches
- Changes to relevant code paths

**Jobs:**

| Job | Purpose | Fail Strategy |
|-----|---------|---------------|
| `changes` | Detect which components changed | None |
| `backend-lint` | Laravel Pint + PHPStan | Fail fast |
| `backend-test` | PHPUnit tests with coverage | Fail on <80% coverage |
| `frontend-lint` | ESLint + TypeScript + Prettier | Fail fast |
| `frontend-test` | Build verification | Fail on build error |
| `docker-build-*` | Multi-arch image build test | Fail on build error |
| `security-scan-*` | Trivy + npm audit | Report only |
| `pr-status-check` | Enforce all checks passed | Gate for merge |

**Caching Strategy:**
- Composer dependencies cached by lock file hash
- NPM dependencies cached by package-lock.json
- Docker layers cached via GitHub Actions cache
- Cache invalidation on dependency changes

### 2. CD Workflow (`.github/workflows/cd.yml`)

**Triggers:**
- Push to `main` branch → Staging
- Tag push `v*` → Production
- Manual workflow dispatch

**Jobs:**

| Job | Purpose | Environment |
|-----|---------|-------------|
| `prepare` | Version calculation, metadata | N/A |
| `build-backend` | Build & push PHP-FPM image | N/A |
| `build-frontend` | Build & push Next.js image | N/A |
| `migration-check` | Validate pending migrations | Target DB |
| `deploy-staging` | Deploy to staging server | Staging |
| `deploy-production` | Blue/green production deploy | Production |
| `cleanup` | Remove old image versions | N/A |

## Container Registry Strategy

### Image Tagging Convention

```
ghcr.io/{owner}/vanessa/backend:{tag}
ghcr.io/{owner}/vanessa/frontend:{tag}
```

| Tag Type | Example | Usage |
|----------|---------|-------|
| Semantic | `v1.2.3` | Production releases |
| Short SHA | `sha-abc1234` | Development builds |
| Branch | `main`, `develop` | Latest branch builds |
| Environment | `staging`, `production` | Environment-specific |
| `latest` | `latest` | Current production |

## Deployment Environments

### Staging Environment

**Strategy:** Rolling Update

```yaml
# Deployment flow:
1. Pull new images
2. Update backend service (rolling)
3. Run database migrations
4. Update frontend service
5. Health check verification
```

**Characteristics:**
- Single instance per service
- Immediate updates
- Production-like configuration
- Used for final validation

### Production Environment

**Strategy:** Blue/Green Deployment

```
┌─────────────────────────────────────────────────────────────────┐
│                     BLUE/GREEN DEPLOYMENT                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   Current State                    During Deploy                │
│   ┌─────────┐                     ┌─────────┐  ┌─────────┐       │
│   │  Blue   │◀───Traffic        │  Blue   │  │  Green  │◀───┐ │
│   │ (v1.0)  │                     │ (v1.0)  │  │ (v1.1)  │    │ │
│   └────┬────┘                     └────┬────┘  └────┬────┘    │ │
│        │                              │            │         │ │
│        ▼                              └────────────┘         │ │
│   ┌─────────┐                             │                  │ │
│   │  DB     │◀────────────────────────────┘                  │ │
│   └─────────┘                                               │ │
│                                                              │ │
│   After Deploy                                               │ │
│   ┌─────────┐                     ┌─────────┐               │ │
│   │  Green  │◀───Traffic         │  Blue   │ (standby)      │ │
│   │ (v1.1)  │                     │ (v1.0)  │               │ │
│   └────┬────┘                     └─────────┘               │ │
│        │                                                      │ │
│        ▼                                                      │ │
│   ┌─────────┐                                                │ │
│   │  DB     │                                                │ │
│   └─────────┘                                                │ │
│                                                              │ │
└──────────────────────────────────────────────────────────────┘
```

**Deployment Steps:**
1. Determine active (blue) and inactive (green) environments
2. Deploy new version to inactive environment
3. Run database migrations
4. Health check on inactive environment
5. Switch load balancer to new environment
6. Wait and verify
7. Stop old environment (keep briefly for rollback)

**Rollback:**
- Instant rollback via load balancer switch
- Old environment kept for 60 seconds after switch
- Database migrations are backward-compatible requirement

## Database Migration Strategy

### Safe Migration Principles

1. **Additive Changes First**
   - Add new columns before removing old ones
   - Add new tables before removing dependencies

2. **Backward Compatibility**
   - New code must work with old schema
   - Old code must work with new schema
   - Two-phase migrations for breaking changes

3. **Migration Order**
   ```
   Pre-deploy migrations  →  Deploy new code  →  Post-deploy migrations
   (additive only)           (rolling update)    (destructive ops)
   ```

4. **Migration Safety Checks**
   - Long-running migrations run manually
   - Schema change size limits enforced
   - Automated rollback procedures

### Migration Execution

```bash
# CI Check
php artisan migrate:status

# Staging/Production Deploy
php artisan migrate --force

# Rollback (if needed)
php artisan migrate:rollback --step=1
```

## Zero-Downtime Deployment

### Health Checks

**Backend (PHP-FPM):**
```dockerfile
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
    cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
```

**Frontend (Next.js):**
```dockerfile
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD node -e "require('http').get('http://localhost:3000/api/health', ...)"
```

**Load Balancer:**
- Only route traffic to healthy containers
- Graceful connection draining
- 5-second timeout for health checks

### Resource Management

**Memory Limits:**
- PHP: 512MB per worker
- Node.js: 512MB per instance
- Container restart on OOM

**CPU Quotas:**
- Soft limit: 1.0 CPU per container
- Burst allowed for startup

## Secrets Management

### GitHub Secrets

| Secret | Purpose | Environment |
|--------|---------|-------------|
| `GITHUB_TOKEN` | Registry auth, API access | Auto |
| `STAGING_SSH_KEY` | Staging server access | Staging |
| `STAGING_HOST` | Staging server hostname | Staging |
| `STAGING_USER` | Staging deploy user | Staging |
| `PRODUCTION_SSH_KEY` | Production server access | Production |
| `PRODUCTION_HOST` | Production server hostname | Production |
| `PRODUCTION_USER` | Production deploy user | Production |
| `DB_HOST` | Database hostname | All |
| `DB_DATABASE` | Database name | All |
| `DB_USERNAME` | Database user | All |
| `DB_PASSWORD` | Database password | All |
| `SLACK_WEBHOOK_URL` | Deployment notifications | Production |

### Environment Variables

**GitHub Variables (non-secret):**
- `NEXT_PUBLIC_API_URL` - Frontend API endpoint
- `APP_URL` - Application base URL
- `FRONTEND_URL` - Frontend origin for CORS

### Security Scanning

**Trivy:**
- Scans container images for CVEs
- Scans filesystem dependencies
- Reports to GitHub Security tab

**npm audit:**
- Frontend dependency scanning
- Fails on HIGH/CRITICAL vulnerabilities
- Auto-update via Dependabot

## Monitoring & Observability

### Deployment Notifications

**Slack Integration:**
- Success/failure notifications
- Version and commit information
- Deployment duration metrics

### Smoke Tests

**Post-Deployment Verification:**
```bash
# Health endpoints
curl -f https://vanessaperfumes.com/health
curl -f https://api.vanessaperfumes.com/api/health

# Critical paths
curl -f https://vanessaperfumes.com/api/products
curl -f https://vanessaperfumes.com/api/auth/me
```

### Log Aggregation

**Container Logs:**
- Forwarded to centralized logging
- Structured JSON format
- 30-day retention

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Build cache miss | Lock file changed | Verify dependencies committed |
| Migration failure | Schema conflict | Run rollback, fix, retry |
| Health check fail | App not ready | Increase start_period |
| Image push fail | Auth expired | Check GITHUB_TOKEN |
| Deploy timeout | Network issue | Retry with SSH tunnel |

### Rollback Procedures

**Immediate Rollback:**
```bash
# Revert to previous image
docker compose up -d --no-deps app-blue  # If green is new
docker compose exec nginx reload

# Or rollback migrations
php artisan migrate:rollback
```

**GitHub Actions Retry:**
1. Go to Actions → Failed workflow
2. Click "Re-run jobs"
3. Select failed jobs or all jobs

## Performance Optimization

### Build Optimization

**Docker Layer Caching:**
- Dependency files copied first
- Source code changes don't invalidate dependency layers
- Multi-stage builds minimize final image size

**GitHub Actions Caching:**
- `actions/cache` for dependencies
- `gha` cache for Docker layers
- Cache keys based on lock files

### Image Optimization

**Backend:**
- Alpine Linux base (small footprint)
- OPcache enabled in production
- Composer autoloader optimized

**Frontend:**
- Next.js standalone output
- Static assets served efficiently
- Node modules excluded from final image
