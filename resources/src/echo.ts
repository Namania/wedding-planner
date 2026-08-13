import Echo from 'laravel-echo'
import Pusher, { type ChannelAuthorizationCallback } from 'pusher-js'
import axios from 'axios'

declare global {
    interface Window {
        Pusher: typeof Pusher
    }
}

window.Pusher = Pusher

// /broadcasting/auth vit à la racine de l'API Laravel (middleware "web"),
// pas sous /api comme le reste des routes.
const appUrl = (import.meta.env.VITE_API_BASE_URL as string).replace(/\/api\/?$/, '')

const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Authorizer maison : le connecteur Pusher par défaut ne sait pas envoyer
    // les cookies de session cross-origin (front sur :5172, API sur :8000).
    authorizer: (channel: { name: string }) => ({
        authorize(socketId: string, callback: ChannelAuthorizationCallback) {
            axios
                .post(
                    `${appUrl}/broadcasting/auth`,
                    { socket_id: socketId, channel_name: channel.name },
                    { withCredentials: true, withXSRFToken: true }
                )
                .then((response) => callback(null, response.data))
                .catch((error) => callback(error, null))
        },
    }),
})

export default echo
