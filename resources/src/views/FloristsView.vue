<template>
    <div class="space-y-5 pb-12">
        <ConfirmPopup class="mx-4" />

        <div class="flex justify-between items-center px-1 py-2 gap-4">
            <h1 class="text-xl font-bold ms-2">Comparer les <span class="text-indigo-600 dark:text-indigo-400">fleuristes</span></h1>
            <Button icon="pi pi-plus" class="!rounded-xl shrink-0 shadow-sm" @click="openNew"
                aria-label="Ajouter un fleuriste" />
        </div>

        <div v-if="isLoading" class="space-y-3">
            <div v-for="n in 4" :key="n"
                class="bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm flex items-center gap-4">
                <Skeleton shape="circle" size="3rem" class="shrink-0" />
                <div class="flex flex-col gap-2 flex-1">
                    <Skeleton width="50%" height="1rem" />
                    <Skeleton width="30%" height="0.75rem" />
                </div>
            </div>
        </div>

        <div v-else class="space-y-3">
            <div v-if="!florists || florists.length === 0"
                class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <i class="pi pi-sun text-4xl text-muted-color mb-3 block"></i>
                <p class="text-sm text-muted-color font-medium">Aucun fleuriste pour le moment. Commencez par en
                    ajouter un !</p>
            </div>

            <div v-for="florist in sortedFlorists" :key="florist.id" @click="openEdit(florist)"
                class="relative bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl shadow-sm flex items-center justify-between gap-3 cursor-pointer active:scale-[0.99] transition-all duration-150"
                :class="isSelected(florist) ? 'border-2 border-amber-400' : 'border border-surface-200 dark:border-surface-800'">

                <div class="absolute -top-2 -right-2 flex items-center gap-1.5 z-10">
                    <QuoteStatusBadge :status="florist.quote_status" @update:status="updateQuoteStatus(florist, $event)" />

                    <button v-if="simulationsStore.active" type="button" @click.stop="toggleSelected(florist.id)"
                        class="w-7 h-7 rounded-full bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 shadow-sm flex items-center justify-center"
                        title="Sélectionner pour la simulation active" aria-label="Sélectionner pour la simulation active">
                        <i v-if="isSelected(florist)" class="pi pi-star-fill text-amber-400 text-sm"></i>
                        <i v-else class="pi pi-star text-muted-color text-sm"></i>
                    </button>
                </div>

                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center shrink-0 overflow-hidden"
                        :class="showFavicon(florist.id, florist.website) ? 'bg-surface-100 dark:bg-surface-800 p-2' : 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400'">
                        <img v-if="showFavicon(florist.id, florist.website)" :src="getFaviconUrl(florist.website)!"
                            @error="onFaviconError(florist.id)" class="w-full h-full object-contain" alt="" />
                        <i v-else class="pi pi-sun"></i>
                    </div>

                    <div class="flex flex-col min-w-0">
                        <span class="font-bold text-color truncate">{{ florist.name }}</span>

                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                            <span
                                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0 bg-surface-100 dark:bg-surface-800 text-muted-color">
                                {{ formatAmount(florist.price) }}
                            </span>
                            <span v-if="florist.style" :class="getStyleClass(florist.style)"
                                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0">
                                {{ getStyleLabel(florist.style) }}
                            </span>
                            <i v-if="florist.note" class="pi pi-clipboard text-muted-color text-xs shrink-0" :title="florist.note"></i>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1 shrink-0">
                    <a v-if="florist.phone" :href="`tel:${florist.phone}`" @click.stop
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-label="Appeler">
                        <i class="pi pi-phone text-sm"></i>
                    </a>
                    <a v-if="florist.website" :href="florist.website" target="_blank" rel="noopener" @click.stop
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-label="Voir le site web">
                        <i class="pi pi-globe text-sm"></i>
                    </a>
                    <i class="pi pi-chevron-right text-xs text-muted-color/60 ms-1"></i>
                </div>
            </div>
        </div>

        <Dialog
            v-model:visible="floristDialog"
            :header="isEditMode ? 'Modifier le fleuriste' : 'Ajouter un fleuriste'"
            modal
            dismissableMask
            class="w-[90vw] max-w-md !rounded-2xl"
            :draggable="false"
        >
            <div class="flex flex-col gap-4 pt-2">

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Nom du
                        fleuriste</label>
                    <InputText id="name" v-model.trim="formFlorist.name" autofocus placeholder="Ex: Fleurs de Margaux"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !formFlorist.name }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !formFlorist.name">Le nom est
                        obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="price" class="text-xs font-bold uppercase tracking-wider text-muted-color">Devis
                        total</label>
                    <InputNumber id="price" v-model="formFlorist.price" mode="currency" currency="EUR" locale="fr-FR"
                        :min="0" placeholder="0 €" class="w-full" :inputClass="'w-full !rounded-xl'"
                        :class="{ 'p-invalid': submitted && formFlorist.price === null }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && formFlorist.price === null">Le
                        prix est obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="style" class="text-xs font-bold uppercase tracking-wider text-muted-color">Style
                        floral</label>
                    <Select id="style" v-model="formFlorist.style" :options="styleOptions" optionLabel="label"
                        optionValue="value" placeholder="Sélectionnez un style" class="w-full !rounded-xl" showClear />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="phone" class="text-xs font-bold uppercase tracking-wider text-muted-color">Téléphone</label>
                    <InputText id="phone" v-model.trim="formFlorist.phone" placeholder="06 12 34 56 78"
                        class="w-full !rounded-xl" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="website" class="text-xs font-bold uppercase tracking-wider text-muted-color">Site
                        web</label>
                    <InputText id="website" v-model.trim="formFlorist.website" placeholder="https://..."
                        class="w-full !rounded-xl" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="note" class="text-xs font-bold uppercase tracking-wider text-muted-color">Note</label>
                    <Textarea id="note" v-model.trim="formFlorist.note" rows="3" autoResize
                        placeholder="Vos impressions, points positifs/négatifs..." class="w-full !rounded-xl" />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end w-full mt-4 gap-2">
                    <Button v-if="isEditMode" label="Supprimer" icon="pi pi-trash" severity="danger" variant="outlined"
                        class="!rounded-xl" @click="confirmDelete($event)" />
                    <Button label="Enregistrer" icon="pi pi-save" class="!rounded-xl px-4" @click="handleSubmit" />
                </div>
            </template>
        </Dialog>

    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ConfirmPopup from 'primevue/confirmpopup'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/api/client'
import { formatAmount } from '@/utils/currency'
import { useFavicon } from '@/composables/useFavicon'
import { useSimulationsStore } from '@/stores/simulations'
import QuoteStatusBadge, { type QuoteStatus } from '@/components/QuoteStatusBadge.vue'
import { useRealtimeResource } from '@/composables/useRealtimeResource'

type FloralStyle = 'champetre' | 'romantique' | 'moderne' | 'exotique' | 'boheme' | 'classique' | 'luxueux'

interface Florist {
    id: number
    name: string
    price: number
    phone: string | null
    website: string | null
    style: FloralStyle | null
    note: string | null
    quote_status: QuoteStatus
}

// Forme du formulaire : le prix peut être vide (non encore saisi) tant que le
// fleuriste n'est pas enregistré, contrairement à `Florist` où il est toujours défini.
interface FloristForm {
    id?: number
    name: string
    price: number | null
    phone: string | null
    website: string | null
    style: FloralStyle | null
    note: string | null
}

const confirm = useConfirm()
const { getFaviconUrl, showFavicon, onFaviconError } = useFavicon()

const styleOptions = [
    { label: 'Champêtre', value: 'champetre' },
    { label: 'Romantique', value: 'romantique' },
    { label: 'Moderne', value: 'moderne' },
    { label: 'Exotique', value: 'exotique' },
    { label: 'Bohème', value: 'boheme' },
    { label: 'Classique', value: 'classique' },
    { label: 'Luxueux', value: 'luxueux' },
]

const getStyleLabel = (style: FloralStyle): string => {
    const matched = styleOptions.find(option => option.value === style)
    return matched ? matched.label : style
}

const getStyleClass = (style: FloralStyle) => {
    switch (style) {
        case 'champetre':
            return 'bg-green-50 dark:bg-green-950/40 text-green-600 dark:text-green-400 border border-green-200 dark:border-green-900/50'
        case 'romantique':
            return 'bg-pink-50 dark:bg-pink-950/40 text-pink-600 dark:text-pink-400 border border-pink-200 dark:border-pink-900/50'
        case 'moderne':
            return 'bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700'
        case 'exotique':
            return 'bg-orange-50 dark:bg-orange-950/40 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-900/50'
        case 'boheme':
            return 'bg-violet-50 dark:bg-violet-950/40 text-violet-600 dark:text-violet-400 border border-violet-200 dark:border-violet-900/50'
        case 'classique':
            return 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50'
        case 'luxueux':
            return 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50'
        default:
            return 'bg-surface-100 dark:bg-surface-800 text-muted-color'
    }
}

const simulationsStore = useSimulationsStore()

const florists = ref<Florist[]>([])
const isLoading = ref<boolean>(true)

// Le fleuriste choisi dans la simulation active remonte en tête de liste.
const sortedFlorists = computed(() => {
    const selectedId = simulationsStore.active?.florist_id
    if (!selectedId) return florists.value
    return [...florists.value].sort((a, b) => (a.id === selectedId ? -1 : b.id === selectedId ? 1 : 0))
})

const isSelected = (florist: Florist): boolean => simulationsStore.active?.florist_id === florist.id

const toggleSelected = async (floristId: number) => {
    await simulationsStore.toggleSelection('florist_id', floristId)
}

// Met à jour la liste en local avec la ressource renvoyée par l'API plutôt
// que de tout recharger, pour ne pas faire remonter la page en haut.
const upsertFlorist = (florist: Florist) => {
    const index = florists.value.findIndex(f => f.id === florist.id)
    if (index !== -1) {
        florists.value[index] = florist
        return
    }
    florists.value.push(florist)
    florists.value.sort((a, b) => a.name.localeCompare(b.name))
}

const updateQuoteStatus = async (florist: Florist, quote_status: QuoteStatus) => {
    const { data } = await apiClient.put(`/florists/${florist.id}`, { ...florist, quote_status })
    upsertFlorist(data)
}

const fetchFlorists = async () => {
    isLoading.value = true
    try {
        const { data } = await apiClient.get<Florist[]>('/florists')
        florists.value = data
    } finally {
        isLoading.value = false
    }
}

const floristDialog = ref<boolean>(false)
const isEditMode = ref<boolean>(false)
const submitted = ref<boolean>(false)

const emptyFlorist = (): FloristForm => ({
    name: '',
    price: null,
    phone: null,
    website: null,
    style: null,
    note: null,
})

const formFlorist = ref<FloristForm>(emptyFlorist())

const openNew = () => {
    isEditMode.value = false
    submitted.value = false
    formFlorist.value = emptyFlorist()
    floristDialog.value = true
}

const openEdit = (florist: Florist) => {
    isEditMode.value = true
    submitted.value = false
    formFlorist.value = { ...florist }
    floristDialog.value = true
}

const confirmDelete = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Voulez-vous vraiment supprimer ce fleuriste ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: () => {
            onDelete(formFlorist.value.id)
            floristDialog.value = false
        }
    })
}

const handleSubmit = async () => {
    submitted.value = true
    if (!formFlorist.value.name.trim() || formFlorist.value.price === null) return

    if (isEditMode.value) {
        const { data } = await apiClient.put(`/florists/${formFlorist.value.id}`, formFlorist.value)
        upsertFlorist(data)
    } else {
        const { data } = await apiClient.post('/florists', formFlorist.value)
        upsertFlorist(data)
    }
    floristDialog.value = false
}

const onDelete = async (id: number | string | undefined) => {
    if (id === undefined) return
    await apiClient.delete(`/florists/${id}`)
    florists.value = florists.value.filter(f => f.id !== id)
}

onMounted(() => {
    fetchFlorists()
    simulationsStore.ensureLoaded()
})

useRealtimeResource<Florist>('florist', {
    onCreatedOrUpdated: upsertFlorist,
    onDeleted: (florist) => {
        florists.value = florists.value.filter(f => f.id !== florist.id)
    },
})
</script>
