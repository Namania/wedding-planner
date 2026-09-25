import { useAuthStore } from '@/stores/auth'
import axios from 'axios'

const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
    withXSRFToken: true,
})

apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        const authRoutes = ['/login', '/logout', '/two-factor-setup', '/two-factor-challenge']
        const isAuthRequest = authRoutes.includes(error.config?.url ?? '')
        if (error.response && error.response.status === 401 && !isAuthRequest) {
            const authStore = useAuthStore()
            authStore.clearSession()
        }
        return Promise.reject(error)
    }
);

export default apiClient
