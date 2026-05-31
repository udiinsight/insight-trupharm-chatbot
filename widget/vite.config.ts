import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Single-bundle build: outputs dist/insight-chat.js (IIFE) + dist/insight-chat.css.
// The WordPress mu-plugin enqueues these two files on every public page.
export default defineConfig({
  plugins: [react()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    target: 'es2019',
    minify: 'terser',
    sourcemap: false,
    cssCodeSplit: false,
    emptyOutDir: true,
    lib: {
      entry: 'src/main.tsx',
      formats: ['iife'],
      name: 'InsightChatWidget',
      fileName: () => 'insight-chat.js',
    },
    rollupOptions: {
      output: {
        // Inline everything; we serve a single JS + a single CSS.
        assetFileNames: (info) => {
          if ((info.names ?? []).some((n) => n.endsWith('.css'))) return 'insight-chat.css';
          return 'assets/[name][extname]';
        },
        inlineDynamicImports: true,
      },
    },
  },
  server: {
    port: 5173,
    open: true,
  },
});
