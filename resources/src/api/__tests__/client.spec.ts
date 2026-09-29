import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import apiClient from '@/api/client'

describe('apiClient', () => {
    let mock: MockAdapter

    beforeEach(() => {
        setActivePinia(createPinia())
        mock = new MockAdapter(apiClient)
    })

    it('rafraîchit le jeton CSRF et rejoue une fois après un 419', async () => {
        let attempts = 0

        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPut('/wedding').reply(() => {
            attempts += 1
            return attempts === 1 ? [419, {}] : [200, { ok: true }]
        })

        const { data } = await apiClient.put('/wedding', {})

        expect(attempts).toBe(2)
        expect(data).toEqual({ ok: true })
    })

    it('abandonne après un seul rejeu si le 419 persiste', async () => {
        let attempts = 0

        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPut('/wedding').reply(() => {
            attempts += 1
            return [419, {}]
        })

        await expect(apiClient.put('/wedding', {})).rejects.toMatchObject({
            response: { status: 419 },
        })

        // Deux appels au total : l'original et un unique rejeu. Sans le
        // drapeau, l'intercepteur boucle jusqu'à épuisement de la pile.
        expect(attempts).toBe(2)
    })

    it('abandonne si le rafraîchissement CSRF échoue lui-même', async () => {
        mock.onGet('../sanctum/csrf-cookie').reply(500)
        mock.onPut('/wedding').reply(419)

        await expect(apiClient.put('/wedding', {})).rejects.toMatchObject({
            response: { status: 419 },
        })

        // Le rafraîchissement a bien été tenté : sans cette vérification, le
        // test passerait même si l'intercepteur n'appelait jamais
        // /sanctum/csrf-cookie et se contentait de laisser filer le 419.
        expect(mock.history.get).toHaveLength(1)
        expect(mock.history.get[0]?.url).toBe('../sanctum/csrf-cookie')
    })

    it('propage une vraie erreur du rejeu (422) plutôt que le 419 d\'origine', async () => {
        let attempts = 0

        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPut('/wedding').reply(() => {
            attempts += 1
            return attempts === 1 ? [419, {}] : [422, { message: 'Erreur de validation' }]
        })

        // Le rejeu échoue pour une tout autre raison (validation). L'appelant
        // doit voir le 422, pas le 419 d'origine devenu périmé : sinon, un
        // problème de formulaire ressemblerait à une session expirée.
        await expect(apiClient.put('/wedding', {})).rejects.toMatchObject({
            response: { status: 422 },
        })

        expect(attempts).toBe(2)
    })
})
