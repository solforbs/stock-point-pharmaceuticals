import path from 'node:path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  plugins: [react(), tailwindcss()],
  // Read VITE_* from the application's own .env one level up, so the SPA and
  // the server are configured from one file. Only VITE_-prefixed values are
  // exposed to the browser; the rest of .env stays server-side.
  envDir: path.resolve(import.meta.dirname, '..'),
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, './src'),
    },
  },
  // Production: Laravel serves the built SPA from public/spa on the same
  // origin as /api, so Sanctum's session cookie works without CORS.
  base: command === 'build' ? '/spa/' : '/',
  build: {
    outDir: '../public/spa',
    emptyOutDir: true,
  },
  server: {
    host: true, // bind 0.0.0.0 + :: — "localhost" alone was IPv6-only here
    port: 5173,
    proxy: {
      // Proxied so the browser sees the API as same-origin — Sanctum's
      // SPA cookie auth needs that; a cross-origin XHR can't carry it.
      '/api': { target: 'http://localhost:8000', changeOrigin: true },
      '/sanctum': { target: 'http://localhost:8000', changeOrigin: true },
      '/auth': { target: 'http://localhost:8000', changeOrigin: true },
      '/broadcasting': { target: 'http://localhost:8000', changeOrigin: true },
      // Laravel's health route: the POS probes it to tell when the server is back.
      '/up': { target: 'http://localhost:8000', changeOrigin: true },
    },
  },
}))
