import { beforeEach, describe, expect, it } from 'vitest'
import MockAdapter from 'axios-mock-adapter'
import galleryClient from '@/api/galleryClient'

describe('galleryClient', () => {
    let mock: MockAdapter

    beforeEach(() => {
        mock = new MockAdapter(galleryClient)
    })

    it('envoie les cookies de session plutôt qu\'un en-tête Authorization', () => {
        expect(galleryClient.defaults.withCredentials).toBe(true)
        expect(galleryClient.defaults.withXSRFToken).toBe(true)
    })

    it('rafraîchit le jeton CSRF et rejoue une fois après un 419', async () => {
        let attempts = 0

        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPost('/gallery/photos').reply(() => {
            attempts += 1
            return attempts === 1 ? [419, {}] : [201, { ok: true }]
        })

        const { data } = await galleryClient.post('/gallery/photos', {})

        expect(attempts).toBe(2)
        expect(data).toEqual({ ok: true })
    })

    it('abandonne après un seul rejeu si le 419 persiste', async () => {
        let attempts = 0

        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPost('/gallery/photos').reply(() => {
            attempts += 1
            return [419, {}]
        })

        await expect(galleryClient.post('/gallery/photos', {})).rejects.toMatchObject({
            response: { status: 419 },
        })

        expect(attempts).toBe(2)
    })

    it('abandonne si le rafraîchissement CSRF échoue lui-même', async () => {
        mock.onGet('../sanctum/csrf-cookie').reply(500)
        mock.onPost('/gallery/photos').reply(419)

        await expect(galleryClient.post('/gallery/photos', {})).rejects.toMatchObject({
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
        mock.onPost('/gallery/photos').reply(() => {
            attempts += 1
            return attempts === 1 ? [419, {}] : [422, { message: 'Erreur de validation' }]
        })

        // Le rejeu échoue pour une tout autre raison (validation). L'appelant
        // doit voir le 422, pas le 419 d'origine devenu périmé : sinon, un
        // problème de formulaire ressemblerait à une session expirée.
        await expect(galleryClient.post('/gallery/photos', {})).rejects.toMatchObject({
            response: { status: 422 },
        })

        expect(attempts).toBe(2)
    })
})
