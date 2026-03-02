import { withSentryConfig } from "@sentry/nextjs";
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'standalone',
  poweredByHeader: false,
  compress: true,

  // Security Headers Configuration
  async headers() {
    const isProduction = process.env.NODE_ENV === 'production';

    return [
      {
        // Apply security headers to all routes
        source: '/:path*',
        headers: [
          // Prevent MIME type sniffing
          {
            key: 'X-Content-Type-Options',
            value: 'nosniff',
          },
          // Prevent clickjacking
          {
            key: 'X-Frame-Options',
            value: 'DENY',
          },
          // XSS Protection (deprecated but useful for legacy browsers)
          {
            key: 'X-XSS-Protection',
            value: '0',
          },
          // Control referrer information
          {
            key: 'Referrer-Policy',
            value: 'strict-origin-when-cross-origin',
          },
          // Permissions Policy (formerly Feature-Policy)
          {
            key: 'Permissions-Policy',
            value: 'accelerometer=(), ambient-light-sensor=(), autoplay=(), battery=(), camera=(), cross-origin-isolated=(), display-capture=(), document-domain=(), encrypted-media=(), execution-while-not-rendered=(), execution-while-out-of-viewport=(), fullscreen=(self), geolocation=(), gyroscope=(), keyboard-map=(), magnetometer=(), microphone=(), midi=(), navigation-override=(), payment=(), picture-in-picture=(), publickey-credentials-get=(), screen-wake-lock=(), sync-xhr=(), usb=(), web-share=(), xr-spatial-tracking=()',
          },
          // Cross-Origin headers
          {
            key: 'Cross-Origin-Resource-Policy',
            value: 'same-origin',
          },
          {
            key: 'Cross-Origin-Opener-Policy',
            value: 'same-origin',
          },
          {
            key: 'Cross-Origin-Embedder-Policy',
            value: 'require-corp',
          },
          // Content Security Policy
          {
            key: 'Content-Security-Policy',
            value: getContentSecurityPolicy(isProduction),
          },
          // HSTS - Production only with HTTPS
          ...(isProduction ? [{
            key: 'Strict-Transport-Security',
            value: 'max-age=31536000; includeSubDomains; preload',
          }] : []),
        ],
      },
      {
        // API routes - no caching
        source: '/api/:path*',
        headers: [
          {
            key: 'Cache-Control',
            value: 'no-store, no-cache, must-revalidate, max-age=0',
          },
          {
            key: 'Pragma',
            value: 'no-cache',
          },
          {
            key: 'Expires',
            value: '0',
          },
        ],
      },
    ];
  },
};

/**
 * Generate Content Security Policy based on environment
 */
function getContentSecurityPolicy(isProduction: boolean): string {
  const directives: string[] = [
    "default-src 'self'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
    "object-src 'none'",
    "media-src 'self'",
    "worker-src 'self' blob:",
    "manifest-src 'self'",
  ];

  // Script sources
  if (isProduction) {
    directives.push("script-src 'self' https://browser.sentry-cdn.com");
  } else {
    // Development: allow eval and inline scripts for hot-reload
    directives.push("script-src 'self' 'unsafe-inline' 'unsafe-eval' blob:");
  }

  // Style sources
  directives.push("style-src 'self' 'unsafe-inline' https://fonts.googleapis.com");

  // Font sources
  directives.push("font-src 'self' https://fonts.gstatic.com data:");

  // Image sources
  directives.push("img-src 'self' data: https: blob:");

  // Connect sources
  const connectSources = ["'self'"];
  // Sentry endpoints
  connectSources.push('https://o4510969532907520.ingest.de.sentry.io');
  connectSources.push('https://o4510969532907520.ingest.sentry.io');

  // Development: allow local backend connections
  if (!isProduction) {
    connectSources.push('http://localhost:8000');
    connectSources.push('http://localhost:8001');
    connectSources.push('http://127.0.0.1:8000');
    connectSources.push('http://127.0.0.1:8001');
  }

  directives.push(`connect-src ${connectSources.join(' ')}`);

  // Frame sources
  directives.push("frame-src 'none'");

  // CSP Reporting (production only)
  if (isProduction && process.env.CSP_REPORT_URI) {
    directives.push(`report-uri ${process.env.CSP_REPORT_URI}`);
    directives.push("report-to csp-endpoint");
  }

  // Upgrade insecure requests in production
  if (isProduction) {
    directives.push('upgrade-insecure-requests');
  }

  return directives.join('; ');
}

export default withSentryConfig(nextConfig, {
  // For all available options, see:
  // https://www.npmjs.com/package/@sentry/webpack-plugin#options

  org: "vanessa-5s",

  project: "javascript-nextjs",

  // Only print logs for uploading source maps in CI
  silent: !process.env.CI,

  // For all available options, see:
  // https://docs.sentry.io/platforms/javascript/guides/nextjs/manual-setup/

  // Upload a larger set of source maps for prettier stack traces (increases build time)
  widenClientFileUpload: true,

  // Route browser requests to Sentry through a Next.js rewrite to circumvent ad-blockers.
  // This can increase your server load as well as your hosting bill.
  // Note: Check that the configured route will not match with your Next.js middleware, otherwise reporting of client-
  // side errors will fail.
  tunnelRoute: "/monitoring",

  webpack: {
    // Enables automatic instrumentation of Vercel Cron Monitors. (Does not yet work with App Router route handlers.)
    // See the following for more information:
    // https://docs.sentry.io/product/crons/
    // https://vercel.com/docs/cron-jobs
    automaticVercelMonitors: true,

    // Tree-shaking options for reducing bundle size
    treeshake: {
      // Automatically tree-shake Sentry logger statements to reduce bundle size
      removeDebugLogging: true,
    },
  }
});
