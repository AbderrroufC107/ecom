import { defineConfig } from 'vite';
import { resolve } from 'path';
import legacy from '@vitejs/plugin-legacy';

export default defineConfig(({ mode }) => {
  const ROOT = resolve(__dirname, '..');
  const DIST = resolve(ROOT, 'assets', 'dist');
  const TMP = resolve(__dirname, 'tmp');

  const isCssOnly = mode === 'css';
  const isJsOnly = mode === 'js';

  return {
    root: __dirname,
    base: '/assets/dist/',

    plugins: [
      !isCssOnly && legacy({
        targets: ['defaults', 'not IE 11'],
        renderLegacyChunks: false,
        polyfills: false,
      }),
    ].filter(Boolean),

    build: {
      outDir: DIST,
      emptyOutDir: false,
      cssCodeSplit: false,
      minify: 'terser',
      terserOptions: {
        compress: { drop_console: true },
        format: { comments: false },
      },
      rollupOptions: {
        input: isJsOnly
          ? resolve(TMP, 'entry.js')
          : isCssOnly
            ? resolve(__dirname, 'src', 'styles.css')
            : {
                app: resolve(TMP, 'entry.js'),
                styles: resolve(__dirname, 'src', 'styles.css'),
              },
        output: {
          entryFileNames: 'app.min.js',
          chunkFileNames: 'vendor.min.js',
          assetFileNames: (chunkInfo) => {
            if (chunkInfo.name && chunkInfo.name.endsWith('.css')) {
              return 'styles.min.css';
            }
            return '[name].[ext]';
          },
          manualChunks: {
            vendor: ['react', 'react-dom'],
          },
        },
      },
    },

    resolve: {
      alias: {
        '@js': resolve(ROOT, 'assets', 'js'),
        '@css': resolve(ROOT, 'assets', 'css'),
      },
    },
  };
});
