import axios from 'axios'

const GALLERY_TOKEN_KEY = 'gallery_token'

export function getGalleryToken(): string | null {
    try {
        return localStorage.getItem(GALLERY_TOKEN_KEY)
    } catch {
        return null
    }
}

export function setGalleryToken(token: string | null): void {
    try {
        if (token === null) {
            localStorage.removeItem(GALLERY_TOKEN_KEY)
        } else {
            localStorage.setItem(GALLERY_TOKEN_KEY, token)
        }
    } catch {
        // noop
    }
}

const galleryClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
})

galleryClient.interceptors.request.use((config) => {
    const token = getGalleryToken()
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

galleryClient.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status
        if (status === 401 || (status === 403 && window.location.pathname.startsWith('/gallery'))) {
            setGalleryToken(null)
            if (window.location.pathname !== '/gallery/login') {
                window.location.href = '/gallery/login'
            }
        }
        return Promise.reject(error)
    }
)

export default galleryClient
