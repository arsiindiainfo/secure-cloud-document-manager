/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    // Forked worker processes take too long to come up in this sandbox and
    // trip vitest's worker-ready timeout; the lighter thread pool starts
    // fast enough to run reliably here.
    pool: 'threads',
  },
})
