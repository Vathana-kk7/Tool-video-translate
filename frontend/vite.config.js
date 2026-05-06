import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  // DEV server (used only when running `npm run dev` inside /frontend)
  server: {
    port: 5173,
    host: true,
    fs: {
      strict: false,
    },
  },
  // Build output into Laravel public directory
  build: {
    outDir: '../public/build',
    assetsDir: 'assets',
    emptyOutDir: true,
    manifest: true,
  },
})

