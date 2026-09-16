<template>
    <div class="min-h-screen flex flex-col justify-center items-center px-4 py-8">
        <div
            class="w-full max-w-md bg-surface-0 dark:bg-surface-900 p-6 sm:p-8 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-md space-y-6">

            <div class="text-center space-y-2">
                <i class="pi pi-camera text-3xl text-indigo-600 dark:text-indigo-400"></i>
                <h1 class="text-2xl font-bold tracking-tight">Galerie du mariage</h1>
                <p class="text-sm text-muted-color">Reconnectez-vous avec votre prénom et votre code PIN.</p>
            </div>

            <form @submit.prevent="handleLogin" class="space-y-4">
                <Message v-if="errorMessage" severity="error" variant="simple" size="small" class="w-full">
                    {{ errorMessage }}
                </Message>

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Votre
                        prénom</label>
                    <InputText id="name" v-model.trim="name" placeholder="Ex: Camille" class="w-full !rounded-xl"
                        :class="{ 'p-invalid': submitted && !name }" autofocus />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="pin" class="text-xs font-bold uppercase tracking-wider text-muted-color">Code
                        PIN</label>
                    <InputText id="pin" v-model.trim="pin" inputmode="numeric" maxlength="6" placeholder="••••"
                        type="password" class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !pin }" />
                </div>

                <Button type="submit" label="Se connecter" :loading="loading"
                    class="w-full !rounded-xl !py-3 font-semibold mt-2" />
            </form>

            <p class="text-center text-xs text-muted-color">
                Pas encore de compte ? Scannez le QR code présent sur les tables du mariage.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import galleryClient, { getGalleryToken, setGalleryToken } from '@/api/galleryClient'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import axios from 'axios'

const router = useRouter()

const name = ref('')
const pin = ref('')
const submitted = ref(false)
const loading = ref(false)
const errorMessage = ref('')

onMounted(() => {
    if (getGalleryToken()) {
        router.replace('/gallery')
    }
})

const handleLogin = async () => {
    submitted.value = true
    errorMessage.value = ''

    if (!name.value || !pin.value) return

    loading.value = true

    try {
        const { data } = await galleryClient.post('/gallery/login', {
            name: name.value,
            pin: pin.value,
        })

        setGalleryToken(data.token)
        router.push('/gallery')
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            errorMessage.value = 'Prénom ou code PIN incorrect.'
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
