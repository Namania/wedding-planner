import { ref, computed } from 'vue'
import { defineStore } from 'pinia'

interface User {
    id: number
    name: string
    email: string
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null)

    const isAuthenticated = computed(() => user.value !== null)

    function setUser(newUser: User | null) {
        user.value = newUser
    }

    function logout() {
        user.value = null
    }

    return { user, isAuthenticated, setUser, logout }
})
