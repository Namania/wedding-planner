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
                            nom</label>
                        <InputText id="name" v-model.trim="name" placeholder="Ex: Camille" class="w-full !rounded-xl"
                            :class="{ 'p-invalid': submitted && !nameValid }" autofocus />
                        <small class="text-red-500 font-medium text-xs" v-if="submitted && !nameValid">2 à 40
                            caractères requis.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="text-xs font-bold uppercase tracking-wider text-muted-color">Votre
                            email</label>
                        <InputText id="email" type="email" v-model.trim="email" placeholder="Ex: camille@exemple.com"
                            class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !email }" />
                        <small class="text-red-500 font-medium text-xs" v-if="submitted && !email">Ce champ est
                            obligatoire.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-xs font-bold uppercase tracking-wider text-muted-color">Votre
                            mot de passe</label>
                        <Password id="password" v-model="password" placeholder="••••••••" :feedback="false" toggleMask
                            :fluid="true" :inputStyle="{ borderRadius: '0.75rem' }"
                            :class="{ 'p-invalid': submitted && !passwordValid }" />
                        <small class="text-xs text-muted-color">8 caractères minimum. Il vous permettra de vous
                            reconnecter depuis un autre appareil.</small>
                        <small class="text-red-500 font-medium text-xs" v-if="submitted && !passwordValid">8 caractères
                            minimum.</small>
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
import galleryClient from '@/api/galleryClient'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
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
const email = ref('')
const password = ref('')
const submitted = ref(false)
const loading = ref(false)
const errorMessage = ref('')

// Alignée sur la validation du back (min:2, max:40).
const nameValid = computed(() => name.value.length >= 2 && name.value.length <= 40)
const passwordValid = computed(() => password.value.length >= 8)

const inviteToken = computed(() => String(route.params.token ?? ''))

onMounted(async () => {
    // Plus de jeton à consulter : c'est le serveur qui sait si une session est
    // ouverte, via le cookie envoyé avec la requête. Un 401 ici est la réponse
    // normale en l'absence de session — galleryClient ne redirige donc pas
    // pour cette route, et on reste bien sur le formulaire d'inscription.
    try {
        await galleryClient.get('/gallery/me')
        router.replace('/gallery')
        return
    } catch {
        // Pas de session : on reste sur le formulaire.
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

    if (!nameValid.value || !email.value || !passwordValid.value) return

    loading.value = true

    try {
        await galleryClient.post('/gallery/register', {
            token: inviteToken.value,
            name: name.value,
            email: email.value,
            password: password.value,
        })

        // La session est déjà ouverte côté serveur : il n'y a plus de jeton à
        // stocker, seulement à rediriger.
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
