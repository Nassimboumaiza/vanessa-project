# =============================================================================
# Next.js Frontend Dockerfile - Production-Grade Multi-Stage Build
# =============================================================================
# Stage 1: Dependencies
# Stage 2: Builder
# Stage 3: Production Runner
# Stage 4: Development
# =============================================================================

# -----------------------------------------------------------------------------
# STAGE 1: Dependencies
# -----------------------------------------------------------------------------
FROM node:20-alpine3.19 AS deps

LABEL maintainer="Vanessa Perfumes <dev@vanessaperfumes.com>"
LABEL description="Next.js Frontend Dependencies"

# Install security updates and required packages
RUN apk add --no-cache libc6-compat \
    && npm install -g npm@latest

WORKDIR /app

# Copy package files for better layer caching
COPY luxury-perfume-frontend/package*.json ./

# Install ALL dependencies (including dev) needed for build
RUN npm ci --ignore-scripts \
    && npm cache clean --force

# -----------------------------------------------------------------------------
# STAGE 2: Builder
# -----------------------------------------------------------------------------
FROM node:20-alpine3.19 AS builder

WORKDIR /app

# Copy dependencies from deps stage
COPY --from=deps /app/node_modules ./node_modules

# Copy source code
COPY luxury-perfume-frontend/ ./

# Set production environment for build
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV NEXT_LINT_SKIP=1

# Build the application
RUN npm run build

# Production dependencies already included in build

# -----------------------------------------------------------------------------
# STAGE 3: Production Runner
# -----------------------------------------------------------------------------
FROM node:20-alpine3.19 AS production

# Security: Create non-root user
RUN addgroup --system --gid 1001 nodejs \
    && adduser --system --uid 1001 nextjs

WORKDIR /app

# Set environment variables
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000
ENV HOSTNAME=0.0.0.0

# Copy necessary files from builder
COPY --from=builder --chown=nextjs:nodejs /app/public ./public
COPY --from=builder --chown=nextjs:nodejs /app/.next ./.next
COPY --from=builder --chown=nextjs:nodejs /app/node_modules ./node_modules
COPY --from=builder --chown=nextjs:nodejs /app/package.json ./package.json

# Create required directories
RUN mkdir -p /app/.next/cache \
    && chown -R nextjs:nodejs /app

# Security: Set proper permissions
RUN chmod -R 755 /app/public \
    && chmod -R 755 /app/.next

# Switch to non-root user
USER nextjs

# Expose port
EXPOSE 3000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD node -e "require('http').get('http://localhost:3000/api/health', (r) => r.statusCode === 200 ? process.exit(0) : process.exit(1))" || exit 1

# Start the application
CMD ["npm", "start"]

# -----------------------------------------------------------------------------
# STAGE 4: Development
# -----------------------------------------------------------------------------
FROM node:20-alpine3.19 AS development

WORKDIR /app

# Install git for development
RUN apk add --no-cache git

# Set development environment
ENV NODE_ENV=development
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000
ENV HOSTNAME=0.0.0.0

# Copy package files
COPY luxury-perfume-frontend/package*.json ./

# Install all dependencies (including dev)
RUN npm ci

# Copy source code
COPY luxury-perfume-frontend/ ./

# Expose port for development
EXPOSE 3000

# Start development server
CMD ["npm", "run", "dev"]
