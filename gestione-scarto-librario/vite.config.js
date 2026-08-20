import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
  const target = mode === 'admin' ? 'admin' : 'public';
  return {
    plugins: [react()],
    define: {
      __SCARTO_ADMIN__: JSON.stringify(target === 'admin')
    },
    build: {
      outDir: `dist/${target}`,
      assetsDir: 'assets',
      cssCodeSplit: false,
      emptyOutDir: true,
      rollupOptions: {
        input: 'src/index.tsx',
        output: {
          entryFileNames: 'assets/index-[hash].js',
          chunkFileNames: 'assets/[name]-[hash].js',
          assetFileNames: 'assets/index-[hash].[ext]'
        }
      },
      manifest: true,
      sourcemap: false
    }
  };
});
