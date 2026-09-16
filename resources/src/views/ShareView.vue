<template>
    <div class="min-h-screen flex flex-col justify-center items-center px-4 py-8">
        <div
            class="w-full max-w-md bg-surface-0 dark:bg-surface-900 p-6 sm:p-8 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-md space-y-6">

            <div v-if="checking" class="space-y-3">
                <Skeleton height="2rem" class="!rounded-xl" />
                <Skeleton height="8rem" class="!rounded-xl" />
            </div>

            <template v-else-if="!inviteValid">
                <div class="text-center space-y-2">
                    <i class="pi pi-lock text-3xl text-muted-color"></i>
                    <h1 class="text-2xl font-bold tracking-tight">Lien invalide</h1>
                    <p class="text-sm text-muted-color">
                        Ce lien d'invitation n'est pas (ou plus) valide. Rapprochez-vous des mariés.
                    </p>
                </div>
                <router-link to="/gallery/login" class="block text-center text-sm text-indigo-600 dark:text-indigo-400 font-medium">
                    Déjà inscrit·e ? Se connecter
                </router-link>
            </template>

            <template v-else>
                <div class="text-center space-y-2">
                    <i class="pi pi-camera text-3xl text-indigo-600 dark:text-indigo-400"></i>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Mariage de {{ wedding?.spouse_1_name }} & {{ wedding?.spouse_2_name }}
                    </h1>
                    <p class="text-sm text-muted-color">
                        Partagez vos photos du mariage avec les mariés et les autres invités !
                    </p>
                </div>

                <Message v-if="!registrationsOpen" severity="warn" variant="simple" size="small" class="w-full">
                    Les inscriptions sont fermées. Si vous avez déjà un compte, connectez-vous ci-dessous.
                </Message>

                <form v-else @submit.prevent="handleRegister" class="space-y-4">
                    <Message v-if="errorMessage" severity="error" variant="simple" size="small" class="w-full">
                        {{ errorMessage }}
                    </Message>

                    <div class="flex flex-col gap-1.5">
                        <label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Votre
                            prénom</label>
                        <InputText id="name" v-model.trim="name" placeholder="Ex: Camille" class="w-full !rounded-xl"
                            :class="{ 'p-invalid': submitted && !name }" autofocus />
                        <small class="text-red-500 font-medium text-xs" v-if="submitted && !name">Ce champ est
                            obligatoire.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="pin" class="text-xs font-bold uppercase tracking-wider text-muted-color">Code PIN
                            (4 à 6 chiffres)</label>
                        <InputText id="pin" v-model.trim="pin" inputmode="numeric" maxlength="6" placeholder="Ex: 4821"
                            class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !pinValid }" />
                        <small class="text-xs text-muted-color">Il vous permettra de vous reconnecter depuis un autre
                            appareil — retenez-le bien !</small>
                        <small class="text-red-500 font-medium text-xs" v-if="submitted && !pinValid">4 à 6 chiffres
                            requis.</small>
                    </div>

                    <Button type="submit" label="Rejoindre la galerie" :loading="loading"
                        class="w-full !rounded-xl !py-3 font-semibold mt-2" />
                </form>

                <router-link to="/gallery/login" class="block text-center text-sm text-indigo-600 dark:text-indigo-400 font-medium">
                    Déjà inscrit·e ? Se connecter
                </router-link>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import galleryClient, { getGalleryToken, setGalleryToken } from '@/api/galleryClient'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import axios from 'axios'

interface WeddingInfo {
    spouse_1_name: string
    spouse_2_name: string
    date: string
}

const route = useRoute()
const router = useRouter()

const checking = ref(true)
const inviteValid = ref(false)
const registrationsOpen = ref(false)
const wedding = ref<WeddingInfo | null>(null)

const name = ref('')
const pin = ref('')
const submitted = ref(false)
const loading = ref(false)
const errorMessage = ref('')

const pinValid = computed(() => /^\d{4,6}$/.test(pin.value))

const inviteToken = computed(() => String(route.params.token ?? ''))

onMounted(async () => {
    if (getGalleryToken()) {
        router.replace('/gallery')
        return
    }

    try {
        const { data } = await galleryClient.get(`/gallery/invite/${inviteToken.value}`)
        inviteValid.value = true
        registrationsOpen.value = data.registrations_open
        wedding.value = data.wedding
    } catch {
        inviteValid.value = false
    } finally {
        checking.value = false
    }
})

const handleRegister = async () => {
    submitted.value = true
    errorMessage.value = ''

    if (!name.value || !pinValid.value) return

    loading.value = true

    try {
        const { data } = await galleryClient.post('/gallery/register', {
            token: inviteToken.value,
            name: name.value,
            pin: pin.value,
        })

        setGalleryToken(data.token)
        router.push('/gallery')
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const data = error.response.data as { message?: string }
            errorMessage.value = data.message || 'Inscription impossible.'
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
