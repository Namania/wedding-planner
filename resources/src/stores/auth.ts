import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/api/client';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);

    const isAuthenticated = computed(() => !!user.value);

    async function checkAuth() {
        try {
            const { data } = await apiClient.get('/user');
            user.value = data;
        } catch {
            user.value = null;
        }
    }

    async function login(credentials: { email: string; password: string }) {
        await apiClient.get('../sanctum/csrf-cookie');

        const { data } = await apiClient.post('/login', credentials);
        user.value = data.user;
    }

    async function logout() {
        await apiClient.post('/logout');
        user.value = null;
    }

    return { user, isAuthenticated, login, logout, checkAuth };
});
