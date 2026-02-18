# =============================================================================
# Nginx Web Server Dockerfile
# =============================================================================
# Lightweight Alpine-based Nginx with security hardening
# =============================================================================

FROM nginx:1.25-alpine3.18

# Metadata
LABEL maintainer="Vanessa Perfumes <dev@vanessaperfumes.com>"
LABEL description="Nginx Reverse Proxy for Laravel Application"
LABEL version="1.0"

# Install additional tools
RUN apk add --no-cache \
    curl \
    ca-certificates \
    && rm -rf /var/cache/apk/*

# Remove default Nginx configuration
RUN rm -f /etc/nginx/conf.d/default.conf

# Copy custom Nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/ /etc/nginx/conf.d/

# Create necessary directories
RUN mkdir -p /var/www/html/public \
    /var/cache/nginx \
    /var/run \
    /var/log/nginx \
    && chown -R nginx:nginx /var/www/html \
    && chown -R nginx:nginx /var/cache/nginx \
    && chown -R nginx:nginx /var/run \
    && touch /var/run/nginx.pid \
    && chown nginx:nginx /var/run/nginx.pid

# Security: Run as non-root user
USER nginx

# Expose HTTP and HTTPS ports
EXPOSE 80
EXPOSE 443

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start Nginx
CMD ["nginx", "-g", "daemon off;"]
