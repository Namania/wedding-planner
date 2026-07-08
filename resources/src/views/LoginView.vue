<template>
    <div class="min-h-[80vh] flex flex-col justify-center items-center px-4">
        <div
            class="w-full max-w-md bg-surface-0 dark:bg-surface-900 p-6 sm:p-8 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-md space-y-6">

            <div class="text-center space-y-2">
                <h1 class="text-2xl font-bold tracking-tight">Content de vous revoir !</h1>
                <p class="text-sm text-muted-color">Connectez-vous pour gérer vos préparatifs.</p>
            </div>

            <form @submit.prevent="handleLogin" class="space-y-4">
                <Message v-if="errorMessage" severity="error" variant="simple" size="small" class="w-full">
                    {{ errorMessage }}
                </Message>

                <div class="flex flex-col gap-1.5">
                    <label for="email" class="text-xs font-bold uppercase tracking-wider text-muted-color">Adresse
                        Email</label>
                    <InputText id="email" v-model.trim="email" type="email" placeholder="exemple@domaine.com"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !email }" autofocus />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password" class="text-xs font-bold uppercase tracking-wider text-muted-color">Mot de
                        passe</label>
                    <Password id="password" v-model="password" placeholder="••••••••" class="w-full"
                        :class="{ 'p-invalid': submitted && !password }" :feedback="false" toggleMask :fluid="true"
                        :inputStyle="{ borderRadius: '0.75rem' }" />
                </div>

                <Button type="submit" label="Se connecter" :loading="isLoading"
                    class="w-full !rounded-xl !py-3 font-semibold mt-2" />
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/api/client'
import axios from 'axios'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Message from 'primevue/message'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const submitted = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const handleLogin = async () => {
    submitted.value = true
    errorMessage.value = ''

    if (!email.value || !password.value) return

    isLoading.value = true

    try {
        await apiClient.get('../sanctum/csrf-cookie', { withCredentials: true })

        const response = await apiClient.post('/login', {
            email: email.value,
            password: password.value
        })

        authStore.setUser(response.data.user)

        router.push({ name: 'guests' })

    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            errorMessage.value = error.response.data.message
        } else if (error instanceof Error) {
            errorMessage.value = error.message
        } else {
            errorMessage.value = 'Une erreur est survenue lors de la connexion.'
        }
    } finally {
        isLoading.value = false
    }
}
</script>
