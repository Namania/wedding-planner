<template>
    <Button
        class="w-full mt-4"
        label="Déconnexion"
        icon="pi pi-sign-out"
        severity="danger"
        variant="outlined"
        @click="handleLogout"
    />
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()

const handleLogout = async () => {
    try {
        await authStore.logout()
        router.push({ name: 'login' })
    } catch (error) {
        console.error("Erreur lors de la déconnexion", error)
    }
}
</script>
