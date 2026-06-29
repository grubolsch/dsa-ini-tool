/// <reference types="vitest" />
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// The Vite dev server runs inside the `node` container, so its server-side proxy
// must reach nginx over the Docker network (http://nginx:80), NOT localhost:8080
// (which would resolve to the node container itself). Override with VITE_PROXY_TARGET
// when running Vite directly on the host (use http://localhost:8080 there).
const proxyTarget = process.env.VITE_PROXY_TARGET ?? 'http://nginx:80';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    host: true,
    proxy: {
      '/api': { target: proxyTarget, changeOrigin: true },
      '/uploads': { target: proxyTarget, changeOrigin: true },
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: [],
  },
});
