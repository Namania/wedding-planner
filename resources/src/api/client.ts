import { useAuthStore } from '@/stores/auth'
import axios from 'axios'
import type { InternalAxiosRequestConfig } from 'axios'

const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
    withXSRFToken: true,
})

/** Marque une requête déjà rejouée après rafraîchissement du jeton CSRF. */
type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

apiClient.interceptors.response.use(
    (response) => response,
    async (error) => {
        const config = error.config as RetriableConfig | undefined

        // La session ne dure que cinq minutes. Après une pause, le cookie
        // « remember me » rouvre une session neuve — donc un nouveau jeton
        // CSRF, que le SPA n'a pas encore. Sans ce rejeu, la première écriture
        // qui suit toute inactivité échouerait en « Page Expired ». Le drapeau
        // borne la reprise à un seul essai.
        if (error.response?.status === 419 && config && !config._csrfRetried) {
            config._csrfRetried = true

            try {
                await apiClient.get('../sanctum/csrf-cookie')

                return await apiClient(config)
            } catch {
                return Promise.reject(error)
            }
        }

        const authRoutes = ['/login', '/logout', '/two-factor-setup', '/two-factor-challenge']
        const isAuthRequest = authRoutes.includes(config?.url ?? '')

        if (error.response && error.response.status === 401 && !isAuthRequest) {
            const authStore = useAuthStore()
            authStore.clearSession()
        }

        return Promise.reject(error)
    }
);

export default apiClient
