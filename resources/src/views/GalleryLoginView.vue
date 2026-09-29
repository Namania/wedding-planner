<template>
    <div class="min-h-screen flex flex-col justify-center items-center px-4 py-8">
        <div
            class="w-full max-w-md bg-surface-0 dark:bg-surface-900 p-6 sm:p-8 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-md space-y-6">

            <div class="text-center space-y-2">
                <i class="pi pi-camera text-3xl text-indigo-600 dark:text-indigo-400"></i>
                <h1 class="text-2xl font-bold tracking-tight">Galerie du mariage</h1>
                <p class="text-sm text-muted-color">Reconnectez-vous avec votre email et votre mot de passe.</p>
            </div>

            <form @submit.prevent="handleLogin" class="space-y-4">
                <Message v-if="errorMessage" severity="error" variant="simple" size="small" class="w-full">
                    {{ errorMessage }}
                </Message>

                <div class="flex flex-col gap-1.5">
                    <label for="email" class="text-xs font-bold uppercase tracking-wider text-muted-color">Votre
                        email</label>
                    <InputText id="email" type="email" v-model.trim="email" placeholder="Ex: camille@exemple.com"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !email }" autofocus />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password" class="text-xs font-bold uppercase tracking-wider text-muted-color">Votre
                        mot de passe</label>
                    <Password id="password" v-model="password" placeholder="••••••••" :feedback="false" toggleMask
                        :fluid="true" :inputStyle="{ borderRadius: '0.75rem' }"
                        :class="{ 'p-invalid': submitted && !password }" />
                </div>

                <Button type="submit" label="Se connecter" :loading="loading"
                    class="w-full !rounded-xl !py-3 font-semibold mt-2" />
            </form>

            <p class="text-center text-xs text-muted-color">
                Pas encore de compte ? Scannez le QR code présent sur les tables du mariage.
            </p>

            <p class="text-center text-xs text-muted-color">
                Mot de passe oublié ? Demandez aux mariés : ils peuvent vous en attribuer un nouveau.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import galleryClient from '@/api/galleryClient'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Message from 'primevue/message'
import axios from 'axios'

const router = useRouter()

const email = ref('')
const password = ref('')
const submitted = ref(false)
const loading = ref(false)
const errorMessage = ref('')

onMounted(async () => {
    try {
        await galleryClient.get('/gallery/me')
        router.replace('/gallery')
    } catch {
        // Pas de session ouverte : on affiche le formulaire.
    }
})

const handleLogin = async () => {
    submitted.value = true
    errorMessage.value = ''

    if (!email.value || !password.value) return

    loading.value = true

    try {
        await galleryClient.post('/gallery/login', {
            email: email.value,
            password: password.value,
        })

        router.push('/gallery')
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            errorMessage.value = 'Email ou mot de passe incorrect.'
        } else if (axios.isAxiosError(error) && error.response?.status === 429) {
            errorMessage.value = 'Trop de tentatives, réessayez dans quelques minutes.'
        } else {
            errorMessage.value = 'Une erreur inattendue est survenue.'
        }
    } finally {
        loading.value = false
    }
}
</script>
