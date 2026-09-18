import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: true, // bind 0.0.0.0 + :: — "localhost" alone was IPv6-only here
    port: 5173,
    proxy: {
      // Proxied so the browser sees the API as same-origin — Sanctum's
      // SPA cookie auth needs that; a cross-origin XHR can't carry it.
      '/api': { target: 'http://localhost:8000', changeOrigin: true },
      '/sanctum': { target: 'http://localhost:8000', changeOrigin: true },
      '/auth': { target: 'http://localhost:8000', changeOrigin: true },
    },
  },
})
