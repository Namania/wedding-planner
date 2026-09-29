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
 * Autorise un canal. Aucun rejeu sur 419 ici, contrairement à
 * `galleryClient` : `broadcasting/auth` est exemptée de la vérification CSRF
 * (voir `validateCsrfTokens(except:)` dans bootstrap/app.php), cette route ne
 * peut donc pas répondre 419.
 *
 * La route reste sous le groupe de middlewares `web` : `SessionGuard` y
 * rejoue le cookie de reconnexion comme sur n'importe quelle autre route de
 * ce groupe. Un téléphone qui se réveille après cinq minutes sans requête a
 * bien vu sa session expirer, mais tant que ce cookie reste valide il
 * réauthentifie seul, silencieusement, et la requête répond 200 : le 403
 * n'arrive que si le cookie de reconnexion lui-même a expiré ou a été tourné
 * (rotation, déconnexion), pas à la simple expiration de la session de cinq
 * minutes. Ce cas n'est pas traité ici : les photos cessent alors d'arriver
 * en direct jusqu'au rechargement de la page.
 */
function authorizeChannel(channelName: string, socketId: string): Promise<AxiosResponse> {
    return axios.post(
        `${appUrl}/broadcasting/auth`,
        { socket_id: socketId, channel_name: channelName },
        { withCredentials: true, withXSRFToken: true }
    )
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
