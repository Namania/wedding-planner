import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'
import Components from 'unplugin-vue-components/vite'
import { PrimeVueResolver } from 'unplugin-vue-components/resolvers'

export default defineConfig(({ mode, command }) => {

  const env = loadEnv(mode, process.cwd(), '');

  return {
    base: command === 'build' ? '/build/' : '/',
    plugins: [
      vue(),
      vueDevTools(),
      tailwindcss(),
      Components({
        resolvers: [
          PrimeVueResolver()
        ]
      }),
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    build: {
      // Emit into the Laravel public dir so the backend can serve the
      // built SPA directly, no separate frontend container in production.
      outDir: '../public/build',
    },
    server: {
      host: '0.0.0.0',
      port: 5173,
      watch: {
        usePolling: true,
      },
      hmr: {
        host: 'localhost',
      },
      allowedHosts: [
        env.VITE_ALLOWED_HOST || 'localhost'
      ]
    }
  }
});
