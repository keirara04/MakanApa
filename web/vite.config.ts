/// <reference types="vitest/config" />
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv, type Plugin, type UserConfig } from 'vite'
import { VitePWA } from 'vite-plugin-pwa'

const config: UserConfig = {
  plugins: [
    react(),
    tailwindcss(),
    VitePWA({
      registerType: 'prompt',
      includeAssets: ['apple-touch-icon.png', 'mascot/*.webp'],
      manifest: {
        id: '/',
        name: 'MakanApa',
        short_name: 'MakanApa',
        description: 'Can’t decide what to eat? MakanApa picks somewhere good nearby.',
        lang: 'en',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        orientation: 'portrait',
        background_color: '#FFF4DD',
        theme_color: '#FFF4DD',
        categories: ['food', 'lifestyle'],
        icons: [
          { src: '/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icon-512.png', sizes: '512x512', type: 'image/png' },
          { src: '/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      // Our own worker (src/sw.ts): the app-shell precache plus Web Push and notification taps.
      strategies: 'injectManifest',
      srcDir: 'src',
      filename: 'sw.ts',
      injectManifest: {
        globPatterns: ['**/*.{js,css,html,webp,svg,woff2}'],
      },
    }),
  ],
  // 5173 belongs to the Laravel site's own Vite dev server (api/).
  server: { port: 5174, strictPort: true },
  test: {
    environment: 'jsdom',
  },
}

/**
 * The Content-Security-Policy, as a meta tag in built pages only (Vite's dev server needs inline
 * scripts). The session token lives in localStorage, so scripts may come only from this site,
 * Google sign-in and Google Maps. Hosts can't set frame-ancestors from a meta tag: the static host
 * sends that header (see ANDROID_PWA_PLAN.md).
 */
function contentSecurityPolicy(apiBaseUrl: string): Plugin {
  const api = new URL(apiBaseUrl).origin
  const policy = [
    "default-src 'self'",
    // Google sign-in, and Google Maps' hosts as Google lists them for an allowlist policy (its
    // example's 'unsafe-eval' and inline scripts left out: add only if the map shows it needs them).
    "script-src 'self' https://accounts.google.com/gsi/client https://*.googleapis.com https://*.gstatic.com https://*.google.com https://*.ggpht.com https://*.googleusercontent.com blob:",
    // Google sign-in and Maps inject their own styles; Maps loads Roboto from Google Fonts.
    "style-src 'self' 'unsafe-inline' https://accounts.google.com/gsi/style https://fonts.googleapis.com",
    "font-src 'self' https://fonts.gstatic.com",
    // Place photos come from Google's and the API's image hosts.
    "img-src 'self' data: blob: https:",
    `connect-src 'self' ${api} https://*.googleapis.com https://*.google.com https://*.gstatic.com data: blob:`,
    'frame-src https://*.google.com',
    // The service worker, and the vector map's workers.
    "worker-src 'self' blob:",
    "manifest-src 'self'",
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
  ].join('; ')
  return {
    name: 'makanapa-csp',
    apply: 'build',
    transformIndexHtml: (html) => html.replace('<meta charset="UTF-8" />', `<meta charset="UTF-8" />\n    <meta http-equiv="Content-Security-Policy" content="${policy}" />`),
  }
}

export default defineConfig(({ command, mode }) => {
  const apiBaseUrl = loadEnv(mode, process.cwd()).VITE_API_BASE_URL || process.env.VITE_API_BASE_URL
  // A production build without an API URL would quietly talk to localhost. Fail the build instead.
  if (command === 'build' && mode === 'production' && !apiBaseUrl) {
    throw new Error('Set VITE_API_BASE_URL (e.g. in .env.production.local) before building for production.')
  }
  return { ...config, plugins: [...(config.plugins ?? []), ...(apiBaseUrl ? [contentSecurityPolicy(apiBaseUrl)] : [])] }
})
