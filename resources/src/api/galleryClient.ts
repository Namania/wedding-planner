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
                return Promise.reject(error)
            }

            return galleryClient(config)
        }

        const status = error.response?.status

        if (status === 401 || (status === 403 && window.location.pathname.startsWith('/gallery'))) {
            if (window.location.pathname !== '/gallery/login') {
                window.location.href = '/gallery/login'
            }
        }

        return Promise.reject(error)
    }
)

export default galleryClient
