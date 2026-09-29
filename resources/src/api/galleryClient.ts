import axios from 'axios'
import type { InternalAxiosRequestConfig } from 'axios'

/** Marque une requête déjà rejouée après rafraîchissement du jeton CSRF. */
type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

const galleryClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
    withXSRFToken: true,
})

galleryClient.interceptors.response.use(
    (response) => response,
    async (error) => {
        const config = error.config as RetriableConfig | undefined

        // La session dure cinq minutes. Après une pause, le cookie de
        // reconnexion en rouvre une neuve, donc un nouveau jeton CSRF que le
        // SPA n'a pas encore. Sans ce rejeu, la première photo envoyée après
        // toute inactivité échouerait en « Page Expired ».
        if (error.response?.status === 419 && config && !config._csrfRetried) {
            config._csrfRetried = true

            try {
                await galleryClient.get('../sanctum/csrf-cookie')
            } catch {
                // Le rafraîchissement a échoué : on propage la 419 d'origine, plus
                // parlante ici que l'erreur du rafraîchissement lui-même.
                return Promise.reject(error)
            }

            // Hors du try : une erreur du rejeu est une vraie erreur de la requête et
            // doit remonter telle quelle, sinon un 422 de validation serait déguisé en
            // « Page Expired ».
            return galleryClient(config)
        }

        // `/gallery/me` sert justement à détecter l'absence de session : un 401
        // y est une réponse normale, pas une session qui expire en cours de
        // route. Idem pour `/gallery/logout`, qui peut très bien recevoir un
        // 401 si la session était déjà fermée. Rediriger sur ces deux routes
        // empêcherait par exemple ShareView d'afficher le formulaire
        // d'inscription : le navigateur quitterait la page avant que son
        // `catch` n'ait la main.
        const authRoutes = ['/gallery/me', '/gallery/logout']
        const isAuthRequest = authRoutes.includes(config?.url ?? '')

        const status = error.response?.status

        if (!isAuthRequest && (status === 401 || (status === 403 && window.location.pathname.startsWith('/gallery')))) {
            if (window.location.pathname !== '/gallery/login') {
                window.location.href = '/gallery/login'
            }
        }

        return Promise.reject(error)
    }
)

export default galleryClient
