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
})
