import Echo from 'laravel-echo'
import Pusher, { type ChannelAuthorizationCallback } from 'pusher-js'
import axios from 'axios'
import { getGalleryToken } from '@/api/galleryClient'

declare global {
    interface Window {
        Pusher: typeof Pusher
    }
}

window.Pusher = Pusher

const appUrl = (import.meta.env.VITE_API_BASE_URL as string).replace(/\/api\/?$/, '')

let instance: Echo<'reverb'> | null = null

export function getGalleryEcho(): Echo<'reverb'> {
    if (instance !== null) return instance

    instance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel: { name: string }) => ({
            authorize(socketId: string, callback: ChannelAuthorizationCallback) {
                axios
                    .post(
                        `${appUrl}/broadcasting/auth`,
                        { socket_id: socketId, channel_name: channel.name },
                        { headers: { Authorization: `Bearer ${getGalleryToken()}` } }
                    )
                    .then((response) => callback(null, response.data))
                    .catch((error) => callback(error, null))
            },
        }),
    })

    return instance
}
