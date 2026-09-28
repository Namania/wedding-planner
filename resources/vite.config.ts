import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'
import Components from 'unplugin-vue-components/vite'
import { PrimeVueResolver } from 'unplugin-vue-components/resolvers'

export default defineConfig(({ mode }) => {

  const env = loadEnv(mode, process.cwd(), '');

  return {
    // Servi à la racine par nginx (image .docker/front). Le préfixe '/build/'
    // n'avait de sens que lorsque Laravel servait le SPA depuis public/build.
    base: '/',
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
      // Récupéré tel quel par l'image front, qui copie ce dossier dans nginx.
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
