import { defineConfig } from 'vite';
import injectHTML from 'vite-plugin-html-inject';
import { ViteMinifyPlugin } from 'vite-plugin-minify';
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer';

export default defineConfig({
  root: '.',
  base: '/tarhan/',
  server: {
    port: 3000,
  },
  publicDir: 'public',
  build: {
    outDir: './dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: './index.html',
        information: './information.html',
        jury: './jury.html',
        ambassadors: './ambassadors.html',
        archive: './archive.html',
        season: './season.html',
        participant: './participant.html',
        entrance: './entrance.html',
        registration: './registration.html',
        forgotPassword: './forgot-password.html',
        resetPassword: './reset-password.html',
        notFound: './404.html',
      },
    },
  },
  plugins: [
    injectHTML(),
    ViteMinifyPlugin(),
    ViteImageOptimizer({
      png: {
        quality: 80,
      },
      jpg: {
        quality: 80,
      },
    }),
  ],
});
