import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  base: './',
  build: {
    outDir: '../dist',
    emptyOutDir: true,
    cssCodeSplit: true,
    rollupOptions: {
      output: {
        entryFileNames: 'admin-react.js',
        chunkFileNames: 'admin-react-[name]-[hash].js',
        assetFileNames(assetInfo) {
          const name = assetInfo.names?.[0] || assetInfo.name || ''
          if (name === 'admin-react.css' || name === 'index.css') return 'admin-react.css'
          return 'admin-react-[name]-[hash][extname]'
        },
        manualChunks(id) {
          if (id.includes('node_modules')) {
            if (id.includes('recharts')) return 'charts'
            if (id.includes('mantine-datatable')) return 'tables'
            if (id.includes('@mantine') || id.includes('react')) return 'vendor'
          }
          return undefined
        }
      }
    }
  }
})
