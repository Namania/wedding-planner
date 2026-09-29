<template>
    <div
        class="bg-surface-0 dark:bg-surface-900 p-6 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm space-y-4">

        <ConfirmPopup class="mx-4" />

        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-bold">Appareils de confiance</h2>
                <p class="text-sm text-muted-color">
                    Ces appareils ne demandent pas de code de vérification pendant trente jours. Le mot de passe
                    reste exigé. Révoquer un appareil lui redemandera ce code à sa prochaine connexion ; la session
                    déjà ouverte dessus n'est pas coupée pour autant.
                </p>
            </div>
            <Button v-if="hasDevices" label="Tout révoquer" severity="danger" variant="outlined" size="small"
                class="!rounded-xl shrink-0" :loading="revoking === 'all'" @click="confirmRevokeAll" />
        </div>

        <p v-if="hasDevices" class="text-xs text-amber-600 dark:text-amber-500 flex items-start gap-1.5">
            <i class="pi pi-exclamation-triangle mt-0.5 shrink-0"></i>
            <span>« Tout révoquer » ferme les sessions ouvertes sur les autres appareils et retire la confiance
                à tous, celui-ci compris. Vous restez connecté·e ici, mais un code de vérification vous sera
                redemandé à votre prochaine connexion.</span>
        </p>

        <div v-if="isLoading" class="space-y-2">
            <Skeleton height="3.5rem" />
            <Skeleton height="3.5rem" />
        </div>

        <template v-else-if="loadError">
            <Message severity="error" size="small">{{ loadError }}</Message>
            <Button label="Réessayer" size="small" variant="outlined" class="!rounded-xl" @click="fetchDevices" />
        </template>

        <template v-else>
            <Message v-if="actionError" severity="error" size="small">{{ actionError }}</Message>

            <p v-if="devices.length === 0" class="text-sm text-muted-color">
                Aucun appareil retenu. Cochez « Se souvenir de cet appareil » à la prochaine connexion.
            </p>

            <ul v-else class="divide-y divide-surface-200 dark:divide-surface-800">
                <li v-for="device in devices" :key="device.id" class="py-3 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-medium truncate">
                            {{ device.name }}
                            <Tag v-if="device.is_current" value="Cet appareil" severity="info"
                                class="ml-1 align-middle" />
                        </p>
                        <p class="text-xs text-muted-color">
                            {{ device.ip_address ?? 'IP inconnue' }} — vu {{ formatDate(device.last_used_at) }},
                            expire {{ formatDate(device.expires_at) }}
                        </p>
                    </div>
                    <Button icon="pi pi-trash" severity="danger" variant="text" class="!rounded-xl shrink-0"
                        :loading="revoking === device.id" @click="revoke(device.id)" />
                </li>
            </ul>
        </template>
    </div>
</template>

<script setup lang="ts">
import apiClient from '@/api/client'
import axios from 'axios'
import Button from 'primevue/button'
import ConfirmPopup from 'primevue/confirmpopup'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { computed, onMounted, ref } from 'vue'

interface TrustedDevice {
    id: number
    name: string
    ip_address: string | null
    last_used_at: string | null
    expires_at: string
    is_current: boolean
}

const confirm = useConfirm()

const devices = ref<TrustedDevice[]>([])
const isLoading = ref(true)
const revoking = ref<number | 'all' | null>(null)
// Erreur de chargement : bloque l'affichage de la liste, avec un bouton de reprise.
const loadError = ref('')
// Erreur d'une révocation : affichée au-dessus de la liste, qui reste consultable.
const actionError = ref('')

// Un seul appareil suffit pour justifier « Tout révoquer » : c'est la seule
// action qui ferme aussi les sessions ouvertes ailleurs, y compris quand il
// n'y a qu'une ligne à révoquer (ex : on a perdu son unique appareil de
// confiance et on se reconnecte depuis ailleurs pour couper l'accès). Un seul
// calcul pour le bouton et le bandeau d'avertissement, afin qu'ils ne puissent
// pas se désynchroniser.
const hasDevices = computed(() => devices.value.length > 0)

const formatDate = (value: string | null): string => {
    if (!value) return 'jamais'

    return new Date(value).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
}

const describeError = (error: unknown, fallback: string): string => {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as { message?: string } | undefined
        return data?.message || fallback
    }

    return fallback
}

const fetchDevices = async () => {
    isLoading.value = true
    loadError.value = ''
    try {
        const { data } = await apiClient.get<TrustedDevice[]>('/two-factor/devices')
        devices.value = data
    } catch (error: unknown) {
        loadError.value = describeError(error, "Impossible de charger la liste des appareils de confiance.")
    } finally {
        isLoading.value = false
    }
}

const revoke = async (id: number) => {
    actionError.value = ''
    revoking.value = id
    try {
        await apiClient.delete(`/two-factor/devices/${id}`)
        await fetchDevices()
    } catch (error: unknown) {
        actionError.value = describeError(error, "La révocation de cet appareil a échoué. Réessayez.")
    } finally {
        revoking.value = null
    }
}

const revokeAll = async () => {
    actionError.value = ''
    revoking.value = 'all'
    try {
        await apiClient.delete('/two-factor/devices')
        await fetchDevices()
    } catch (error: unknown) {
        actionError.value = describeError(error, "La révocation de tous les appareils a échoué. Réessayez.")
    } finally {
        revoking.value = null
    }
}

// « Tout révoquer » ferme côté serveur les sessions des autres appareils et
// fait tourner le jeton de reconnexion, ce qui invalide leurs cookies. La
// session courante, elle, est préservée : c'est le comportement standard
// « déconnecter les autres appareils », et la personne qui vient de perdre son
// téléphone n'a pas à se punir elle-même. On confirme quand même : l'action
// retire la confiance à tous les appareils, celui-ci compris.
const confirmRevokeAll = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message:
            'Les autres appareils seront déconnectés et tous perdront leur statut de confiance, celui-ci compris. Vous restez connecté·e ici, avec un code de vérification à saisir à votre prochaine connexion. Continuer ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Tout révoquer', severity: 'danger' },
        accept: () => revokeAll(),
    })
}

onMounted(fetchDevices)
</script>
