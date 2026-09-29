import Echo from 'laravel-echo'
import Pusher, { type ChannelAuthorizationCallback } from 'pusher-js'
import axios, { type AxiosResponse } from 'axios'

declare global {
    interface Window {
        Pusher: typeof Pusher
    }
}

window.Pusher = Pusher

const appUrl = (import.meta.env.VITE_API_BASE_URL as string).replace(/\/api\/?$/, '')

let instance: Echo<'reverb'> | null = null

/**
 * Autorise un canal, avec le même rejeu qu'un 419 sur `galleryClient` : le
 * socket peut se reconnecter après une longue inactivité (téléphone en
 * veille), avec un jeton CSRF déjà périmé. Sans ce rafraîchissement, la
 * reconnexion échouerait silencieusement et les photos cesseraient d'arriver
 * en direct jusqu'au rechargement de la page.
 */
function authorizeChannel(
    channelName: string,
    socketId: string,
    retried = false
): Promise<AxiosResponse> {
    return axios
        .post(
            `${appUrl}/broadcasting/auth`,
            { socket_id: socketId, channel_name: channelName },
            { withCredentials: true, withXSRFToken: true }
        )
        .catch(async (error: unknown) => {
            if (retried || !axios.isAxiosError(error) || error.response?.status !== 419) {
                throw error
            }

            try {
                await axios.get(`${appUrl}/sanctum/csrf-cookie`, { withCredentials: true, withXSRFToken: true })
            } catch {
                // Le rafraîchissement a échoué : on propage la 419 d'origine.
                throw error
            }

            // Hors du try : une erreur du rejeu doit remonter telle quelle.
            return authorizeChannel(channelName, socketId, true)
        })
}

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
        // Authorizer maison : le connecteur Pusher par défaut ne sait pas envoyer
        // les cookies de session cross-origin (front sur :5172, API sur :8000).
        authorizer: (channel: { name: string }) => ({
            authorize(socketId: string, callback: ChannelAuthorizationCallback) {
                authorizeChannel(channel.name, socketId)
                    .then((response) => callback(null, response.data))
                    .catch((error: Error) => callback(error, null))
            },
        }),
    })

    return instance
}
