import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  base: '/customer-store/',
  plugins: [react()],
  server: { port: 8080 },
  resolve: {
    alias: {
      '@': '/src',
    },
  },
});
