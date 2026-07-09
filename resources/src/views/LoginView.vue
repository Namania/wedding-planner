<template>
    <div class="h-screen flex flex-col justify-center items-center px-4">
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
                    <label for="username" class="text-xs font-bold uppercase tracking-wider text-muted-color">Nom
                        d'utilisateur</label>
                    <InputText id="username" v-model.trim="username" placeholder="username" class="w-full !rounded-xl"
                        :class="{ 'p-invalid': submitted && !username }" autofocus />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password" class="text-xs font-bold uppercase tracking-wider text-muted-color">Mot de
                        passe</label>
                    <Password id="password" v-model="password" placeholder="••••••••••••" class="w-full"
                        :class="{ 'p-invalid': submitted && !password }" :feedback="false" toggleMask :fluid="true"
                        :inputStyle="{ borderRadius: '0.75rem' }" />
                </div>

                <Button type="submit" label="Se connecter" class="w-full !rounded-xl !py-3 font-semibold mt-2" />
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Message from 'primevue/message'

const router = useRouter()

const username = ref('')
const password = ref('')
const submitted = ref(false)
const errorMessage = ref('')

const handleLogin = () => {
    submitted.value = true
    errorMessage.value = ''

    if (!username.value || !password.value) return

    const success = true;

    if (success) {
        router.push({ name: 'dashboard' })
    } else {
        errorMessage.value = 'Nom d\'utilisateur ou mot de passe incorrect.'
    }
}
</script>
