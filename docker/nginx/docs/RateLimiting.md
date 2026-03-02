# Nginx Rate Limiting Configuration

**Project:** Vanessa Perfumes E-commerce Platform  
**Date:** March 2026

---

## Overview

This document describes the rate limiting implementation at the Nginx load balancer level, providing defense-in-depth protection for the Laravel API.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Request Flow                                  │
│                                                                  │
│   Client ───> Nginx (Rate Limit) ───> Laravel ───> Database     │
│               │                                                   │
│               ├── 429 Response (if exceeded)                     │
│               └── Forward to app (if within limit)               │
└─────────────────────────────────────────────────────────────────┘
```

## Rate Limit Zones

| Zone | Rate | Burst | Purpose |
|------|------|-------|---------|
| `api_general` | 60 req/min | 20 | General API endpoints |
| `auth_strict` | 5 req/min | 2 | Login, register, password reset |
| `search` | 20 req/min | 10 | Product search queries |
| `upload` | 10 req/min | 5 | File uploads |
| `conn_limit` | 50 concurrent | - | Connection limit per IP |

## Endpoint Mappings

### Authentication Endpoints (Strict)
```
/api/v1/login
/api/v1/register
/api/v1/forgot-password
/api/v1/reset-password
/api/v1/email/verify
```
**Limit:** 5 requests/minute per IP

### Search Endpoints (Moderate)
```
/api/v1/products/search
/api/v1/search
```
**Limit:** 20 requests/minute per IP

### Upload Endpoints (Conservative)
```
/api/v1/admin/products/{id}/images
/api/v1/admin/upload
/api/v1/admin/images
```
**Limit:** 10 requests/minute per IP

### General API Endpoints
```
/api/v1/*
```
**Limit:** 60 requests/minute per IP

## Response Headers

When rate limiting is active, the following headers are returned:

| Header | Description |
|--------|-------------|
| `X-RateLimit-Limit` | Maximum requests per window |
| `X-RateLimit-Remaining` | Remaining requests in window |
| `X-RateLimit-Reset` | Seconds until window resets |
| `Retry-After` | Seconds to wait before retry (on 429) |

## Error Response (429)

```json
{
    "success": false,
    "message": "Too many requests. Please slow down.",
    "error": "RATE_LIMIT_EXCEEDED",
    "retry_after": 60
}
```

## Configuration Files

### Main Configuration
`docker/nginx/nginx.conf` - Rate limit zone definitions

```nginx
# Rate limiting zones
limit_req_zone $binary_remote_addr zone=api_general:10m rate=60r/m;
limit_req_zone $binary_remote_addr zone=auth_strict:10m rate=5r/m;
limit_req_zone $binary_remote_addr zone=search:10m rate=20r/m;
limit_req_zone $binary_remote_addr zone=upload:10m rate=10r/m;
limit_conn_zone $binary_remote_addr zone=conn_limit:10m;
```

### Server Configuration
`docker/nginx/conf.d/app.conf` - Endpoint-specific rate limiting

```nginx
# Auth endpoints
location ~ ^/api/v1/(login|register|forgot-password|reset-password) {
    limit_req zone=auth_strict burst=2 nodelay;
    limit_req_status 429;
    # ...
}

# General API
location /api/ {
    limit_req zone=api_general burst=20 nodelay;
    limit_req_status 429;
    # ...
}
```

## Burst and Nodelay Explained

### Burst
Allows temporary request spikes above the rate limit. Requests within burst are queued.

### Nodelay
Processes burst requests immediately without delay. Without nodelay, burst requests would be processed at the defined rate.

**Example:**
- Rate: 60 req/min (1 req/sec)
- Burst: 20
- With nodelay: 20 requests can be processed instantly, then rate limit applies
- Without nodelay: 20 requests processed at 1/sec rate

## Internal Network Whitelist

Internal networks are exempt from rate limiting:

```
127.0.0.1
10.0.0.0/8
172.16.0.0/12
192.168.0.0/16
```

## Monitoring

### Nginx Stub Status
```bash
curl http://localhost/nginx_status
```

### Log Analysis
Rate-limited requests are logged with 429 status:

```bash
grep " 429 " /var/log/nginx/access.log
```

## Testing Rate Limits

### Test Auth Endpoint
```bash
# Should succeed 5 times, then fail
for i in {1..10}; do
    curl -X POST http://localhost/api/v1/login \
        -H "Content-Type: application/json" \
        -d '{"email":"test@test.com","password":"test"}' \
        -w "\nStatus: %{http_code}\n"
done
```

### Test General API
```bash
# Should succeed 60+ times with burst
for i in {1..100}; do
    curl http://localhost/api/v1/products \
        -w "%{http_code}\n" -o /dev/null -s
done
```

## Multi-Layer Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                    Defense in Depth                               │
│                                                                  │
│  Layer 1: Nginx (This implementation)                           │
│           └── Per-IP rate limiting, connection limits            │
│                                                                  │
│  Layer 2: Laravel Application                                    │
│           └── Per-user, per-endpoint, authenticated limits       │
│           └── Already implemented in bootstrap/app.php           │
│                                                                  │
│  Layer 3: Database                                               │
│           └── Connection pooling, query limits                   │
└─────────────────────────────────────────────────────────────────┘
```

## Best Practices

1. **Monitor rate limit hits** - High 429 rates may indicate attacks
2. **Adjust limits based on usage** - Analyze traffic patterns
3. **Use burst for legitimate spikes** - Allow for normal user behavior
4. **Whitelist internal services** - Prevent blocking your own services
5. **Log rate-limited requests** - For security analysis

## Troubleshooting

### Issue: Legitimate users getting 429

**Solution:** Increase burst or rate limit for that endpoint

### Issue: Rate limiting not working

**Check:**
1. Zone defined in nginx.conf
2. Zone referenced in app.conf
3. Nginx reloaded after config change

### Issue: Headers not appearing

**Solution:** Ensure `always` flag is used with `add_header`

```nginx
add_header X-RateLimit-Limit 60 always;
```

## References

- [Nginx Rate Limiting Module](http://nginx.org/en/docs/http/ngx_http_limit_req_module.html)
- [Nginx Connection Limiting](http://nginx.org/en/docs/http/ngx_http_limit_conn_module.html)
- [OWASP Rate Limiting Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Denial_of_Service_Cheat_Sheet.html)
