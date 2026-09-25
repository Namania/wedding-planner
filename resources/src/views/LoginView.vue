<template>
    <div class="min-h-screen flex flex-col justify-center items-center px-4 py-8">
        <div
            class="w-full max-w-md bg-surface-0 dark:bg-surface-900 p-6 sm:p-8 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-md space-y-6">

            <!-- Étape 1 : identifiants -->
            <template v-if="!authStore.twoFactorState">
                <div class="text-center space-y-2">
                    <h1 class="text-2xl font-bold tracking-tight">Content de vous revoir !</h1>
                    <p class="text-sm text-muted-color">Connectez-vous pour gérer vos préparatifs.</p>
                </div>

                <form @submit.prevent="handleLogin" class="space-y-4">
                    <Message v-if="errorMessage" severity="error" variant="simple" size="small" class="w-full">
                        {{ errorMessage }}
                    </Message>

                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="text-xs font-bold uppercase tracking-wider text-muted-color">Nom
                            d'utilisateur</label>
                        <InputText id="email" type="email" v-model.trim="email" placeholder="email"
                            class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !email }" autofocus />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-xs font-bold uppercase tracking-wider text-muted-color">Mot de
                            passe</label>
                        <Password id="password" v-model="password" placeholder="••••••••••••" class="w-full"
                            :class="{ 'p-invalid': submitted && !password }" :feedback="false" toggleMask :fluid="true"
                            :inputStyle="{ borderRadius: '0.75rem' }" />
                    </div>

                    <Button type="submit" label="Se connecter" :loading="loading"
                        class="w-full !rounded-xl !py-3 font-semibold mt-2" />
                </form>
            </template>

            <!-- Étape 2 : second facteur (enrôlement ou code) -->
            <template v-else>
                <div class="text-center space-y-2">
                    <i class="pi pi-shield text-3xl text-indigo-600 dark:text-indigo-400"></i>
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ isSetup ? 'Sécurisez votre compte' : 'Vérification en deux étapes' }}
                    </h1>
                    <p class="text-sm text-muted-color">
                        {{ isSetup
                            ? "Scannez ce QR code avec votre application d'authentification, puis saisissez le code affiché."
                            : "Saisissez le code à 6 chiffres de votre application d'authentification." }}
                    </p>
                </div>

                <div v-if="isSetup" class="flex flex-col items-center gap-3">
                    <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR code de configuration"
                        class="w-48 h-48 max-w-full rounded-xl border border-surface-200 dark:border-surface-800 bg-white p-2" />
                    <Skeleton v-else class="!w-48 !h-48 !rounded-xl" />

                    <div class="w-full flex items-center gap-2">
                        <InputText :modelValue="authStore.secret ?? ''" readonly
                            class="w-full min-w-0 !rounded-xl !text-xs" />
                        <Button :icon="copied ? 'pi pi-check' : 'pi pi-copy'" variant="outlined"
                            class="!rounded-xl shrink-0" @click="copySecret" />
                    </div>
                    <p class="text-xs text-muted-color text-center">
                        Pas de QR code ? Saisissez cette clé manuellement dans votre application.
                    </p>
                </div>

                <form @submit.prevent="handleTwoFactor" class="space-y-4">
                    <Message v-if="errorMessage" severity="error" variant="simple" size="small" class="w-full">
                        {{ errorMessage }}
                    </Message>

                    <div class="flex flex-col gap-1.5 items-center">
                        <label for="code" class="text-xs font-bold uppercase tracking-wider text-muted-color self-start">
                            Code de vérification
                        </label>
                        <InputOtp id="code" v-model="code" :length="6" integerOnly autofocus />
                    </div>

                    <Button type="submit" :label="isSetup ? 'Activer et se connecter' : 'Vérifier'" :loading="loading"
                        class="w-full !rounded-xl !py-3 font-semibold mt-2" />

                    <Button label="Revenir à la connexion" variant="text" severity="secondary" size="small"
                        class="w-full !rounded-xl" @click="backToCredentials" />
                </form>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import InputText from 'primevue/inputtext'
import InputOtp from 'primevue/inputotp'
import Password from 'primevue/password'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import QRCode from 'qrcode'
import axios from 'axios'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const code = ref('')
const submitted = ref(false)
const loading = ref(false)
const errorMessage = ref('')
const qrDataUrl = ref('')
const copied = ref(false)

const isSetup = computed(() => authStore.twoFactorState === 'setup')

watch(() => authStore.otpauthUri, async (uri) => {
    qrDataUrl.value = uri ? await QRCode.toDataURL(uri, { width: 512, margin: 2 }) : ''
}, { immediate: true })

const reportError = (error: unknown, fallback: string) => {
    if (axios.isAxiosError(error)) {
        if (error.response?.status === 422) {
            const data = error.response.data as { message?: string }
            errorMessage.value = data.message || fallback
        } else if (error.response?.status === 429) {
            errorMessage.value = 'Trop de tentatives, réessayez dans quelques minutes.'
        } else {
            errorMessage.value = fallback
        }
    } else {
        errorMessage.value = 'Une erreur inattendue est survenue.'
    }
}

const handleLogin = async () => {
    submitted.value = true
    errorMessage.value = ''

    if (!email.value || !password.value) return

    loading.value = true

    try {
        await authStore.login({ email: email.value, password: password.value })
    } catch (error: unknown) {
        reportError(error, 'Identifiants incorrects ou problème serveur.')
    } finally {
        loading.value = false
    }
}

const handleTwoFactor = async () => {
    errorMessage.value = ''

    if (code.value.length !== 6) {
        errorMessage.value = 'Le code doit comporter 6 chiffres.'
        return
    }

    loading.value = true

    try {
        await authStore.submitTwoFactor(code.value)
        router.push({ name: 'dashboard' })
    } catch (error: unknown) {
        code.value = ''
        reportError(error, 'Ce code est incorrect ou a expiré.')
    } finally {
        loading.value = false
    }
}

const copySecret = async () => {
    await navigator.clipboard.writeText(authStore.secret ?? '')
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
}

const backToCredentials = () => {
    authStore.clearSession()
    code.value = ''
    password.value = ''
    errorMessage.value = ''
}
</script>
