import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

const wrapAdminInIIFE = () => ({
  name: 'wrap-admin-iife',
  generateBundle(_, bundle) {
    const adminChunk = bundle['js/admin.js'];

    if (adminChunk && adminChunk.type === 'chunk') {
      adminChunk.code = `(function(){\n${adminChunk.code}\n})();`;
    }
  },
});

const resolvePath = (relative) => path.resolve(__dirname, relative);

export default defineConfig({
  plugins: [vue(), wrapAdminInIIFE()],
  base: '',
  build: {
    outDir: '.',
    emptyOutDir: false,
    manifest: true,
    rollupOptions: {
      input: {
        admin: resolvePath('src/admin/main.js'),
        frontend: resolvePath('src/frontend/form-client.js'),
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: ({ name }) => {
          if (!name) {
            return 'assets/[name][extname]';
          }

          const ext = path.extname(name);

          if (ext === '.css' || name.endsWith('.css')) {
            const base = ext ? path.basename(name, ext) : path.basename(name);
            const safeExt = ext || '.css';

            return `css/${base}${safeExt}`;
          }

          const base = ext ? path.basename(name, ext) : path.basename(name);

          return `assets/${base}-[hash]${ext}`;
        },
      },
    },
  },
  resolve: {
    alias: {
      '@': resolvePath('src'),
    },
  },
});
