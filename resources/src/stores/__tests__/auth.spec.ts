import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import apiClient from '@/api/client'
import { useAuthStore } from '@/stores/auth'

describe('auth store', () => {
    let mock: MockAdapter

    beforeEach(() => {
        setActivePinia(createPinia())
        mock = new MockAdapter(apiClient)
    })

    it('ouvre la session directement quand le back reconnaît un appareil de confiance', async () => {
        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPost('/login').reply(200, { user: { id: 1, email: 'a@b.test' } })

        const auth = useAuthStore()
        await auth.login({ email: 'a@b.test', password: 'secret' })

        // Pas de second facteur à traverser : la réponse contenait déjà
        // l'utilisateur, il ne doit rester aucune trace d'un challenge en
        // cours (sinon l'écran TOTP réapparaîtrait à tort).
        expect(auth.isAuthenticated).toBe(true)
        expect(auth.twoFactorState).toBeNull()
    })

    it('enchaîne sur le second facteur quand aucun utilisateur ne revient', async () => {
        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPost('/login').reply(200, { two_factor: 'required', challenge_token: 'tok' })

        const auth = useAuthStore()
        await auth.login({ email: 'a@b.test', password: 'secret' })

        expect(auth.isAuthenticated).toBe(false)
        expect(auth.twoFactorState).toBe('challenge')
    })

    it('transmet le choix de retenir l\'appareil au second facteur', async () => {
        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPost('/login').reply(200, { two_factor: 'required', challenge_token: 'tok' })
        mock.onPost('/two-factor-challenge').reply((config) => {
            const body = JSON.parse(config.data)
            expect(body.trust_device).toBe(true)
            return [200, { user: { id: 1 } }]
        })

        const auth = useAuthStore()
        await auth.login({ email: 'a@b.test', password: 'secret' })
        await auth.submitTwoFactor('123456', true)

        expect(auth.isAuthenticated).toBe(true)
    })
})
