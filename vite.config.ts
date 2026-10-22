import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { fileURLToPath, URL } from 'node:url'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    // Performance optimizations
    rollupOptions: {
      output: {
        // Code splitting for better caching
        manualChunks: {
          vendor: ['react', 'react-dom'],
          domegallery: ['@use-gesture/react'],
        },
      },
    },
    // Enable CSS code splitting
    cssCodeSplit: true,
    // Reduce bundle size with esbuild (faster than terser)
    minify: 'esbuild',
    target: 'es2015',
  },
  // Enable CSS inlining for critical styles
  css: {
    devSourcemap: false,
  },
})
