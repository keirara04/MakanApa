/// <reference types="vitest/config" />
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv, type UserConfig } from 'vite'
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
      workbox: {
        // App shell only. API responses are never cached by the service worker: picks and
        // places must be live, and the session token must not end up in a cache.
        globPatterns: ['**/*.{js,css,html,webp,svg,woff2}'],
        navigateFallback: '/index.html',
      },
    }),
  ],
  // 5173 belongs to the Laravel site's own Vite dev server (api/).
  server: { port: 5174, strictPort: true },
  test: {
    environment: 'jsdom',
  },
}

export default defineConfig(({ command, mode }) => {
  // A production build without an API URL would quietly talk to localhost. Fail the build instead.
  if (command === 'build' && mode === 'production' && !loadEnv(mode, process.cwd()).VITE_API_BASE_URL) {
    throw new Error('Set VITE_API_BASE_URL (e.g. in .env.production.local) before building for production.')
  }
  return config
})
