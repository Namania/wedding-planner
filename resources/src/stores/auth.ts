import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/api/client';

/** Étape de connexion en cours. Le TOTP est obligatoire : après le mot de
 *  passe, l'utilisateur passe soit par l'enrôlement, soit par le challenge. */
export type TwoFactorState = null | 'setup' | 'challenge';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);

    const twoFactorState = ref<TwoFactorState>(null);
    const challengeToken = ref<string | null>(null);
    const otpauthUri = ref<string | null>(null);
    const secret = ref<string | null>(null);

    const isAuthenticated = computed(() => !!user.value);

    async function checkAuth() {
        try {
            const { data } = await apiClient.get('/user');
            user.value = data;
        } catch {
            user.value = null;
        }
    }

    function resetTwoFactor() {
        twoFactorState.value = null;
        challengeToken.value = null;
        otpauthUri.value = null;
        secret.value = null;
    }

    async function login(credentials: { email: string; password: string }) {
        await apiClient.get('../sanctum/csrf-cookie');

        const { data } = await apiClient.post('/login', credentials);

        // Aucune session n'est ouverte à ce stade : la réponse ne contient pas
        // d'utilisateur, seulement de quoi enchaîner sur le second facteur.
        challengeToken.value = data.challenge_token;

        if (data.two_factor === 'setup_required') {
            twoFactorState.value = 'setup';
            otpauthUri.value = data.otpauth_uri;
            secret.value = data.secret;
        } else {
            twoFactorState.value = 'challenge';
        }
    }

    async function submitTwoFactor(code: string) {
        const endpoint = twoFactorState.value === 'setup'
            ? '/two-factor-setup'
            : '/two-factor-challenge';

        const { data } = await apiClient.post(endpoint, {
            challenge_token: challengeToken.value,
            code,
        });

        user.value = data.user;
        resetTwoFactor();
    }

    async function logout() {
        await apiClient.post('/logout');
        user.value = null;
        resetTwoFactor();
    }

    function clearSession() {
        user.value = null;
        resetTwoFactor();
    }

    return {
        user,
        isAuthenticated,
        twoFactorState,
        otpauthUri,
        secret,
        login,
        submitTwoFactor,
        logout,
        checkAuth,
        clearSession,
    };
});
