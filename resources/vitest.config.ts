import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'

export default defineConfig({
    resolve: {
        alias: {
            // Même alias que vite.config.ts, mais sans les plugins Vue/Tailwind
            // dont les tests (axios + Pinia, sans DOM) n'ont pas besoin.
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    test: {
        // Ni composant Vue ni DOM à monter ici : l'environnement Node par
        // défaut de Vitest suffit pour tester l'intercepteur axios.
        environment: 'node',
    },
})
