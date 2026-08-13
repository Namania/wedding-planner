<template>
    <div class="space-y-5 pb-12">
        <ConfirmPopup class="mx-4" />

        <div class="flex justify-between items-center px-1 py-2 gap-4">
            <h1 class="text-xl font-bold ms-2">Comparer les <span class="text-indigo-600 dark:text-indigo-400">tenues</span></h1>
            <Button icon="pi pi-plus" class="!rounded-xl shrink-0 shadow-sm" @click="openNew"
                aria-label="Ajouter une tenue" />
        </div>

        <div class="flex gap-2 px-1">
            <button type="button" @click="activeSpouse = 'spouse_1'"
                class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all"
                :class="activeSpouse === 'spouse_1'
                    ? 'bg-indigo-600 text-white shadow-sm'
                    : 'bg-surface-100 dark:bg-surface-800 text-muted-color'">
                {{ weddingStore.spouse1Name }}
            </button>
            <button type="button" @click="activeSpouse = 'spouse_2'"
                class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all"
                :class="activeSpouse === 'spouse_2'
                    ? 'bg-indigo-600 text-white shadow-sm'
                    : 'bg-surface-100 dark:bg-surface-800 text-muted-color'">
                {{ weddingStore.spouse2Name }}
            </button>
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
            <div v-if="filteredOutfits.length === 0"
                class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <i class="pi pi-tag text-4xl text-muted-color mb-3 block"></i>
                <p class="text-sm text-muted-color font-medium">Aucune tenue pour {{ activeSpouseName }} pour le
                    moment. Commencez par en ajouter une !</p>
            </div>

            <div v-for="outfit in sortedOutfits" :key="outfit.id" @click="openEdit(outfit)"
                class="relative bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl shadow-sm flex items-center justify-between gap-3 cursor-pointer active:scale-[0.99] transition-all duration-150"
                :class="isSelected(outfit) ? 'border-2 border-amber-400' : 'border border-surface-200 dark:border-surface-800'">

                <div class="absolute -top-2 -right-2 flex items-center gap-1.5 z-10">
                    <QuoteStatusBadge :status="outfit.quote_status" @update:status="updateQuoteStatus(outfit, $event)" />

                    <button v-if="simulationsStore.active" type="button" @click.stop="toggleSelected(outfit.id)"
                        class="w-7 h-7 rounded-full bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 shadow-sm flex items-center justify-center"
                        title="Retenir pour la simulation active" aria-label="Retenir pour la simulation active">
                        <i v-if="isSelected(outfit)" class="pi pi-star-fill text-amber-400 text-sm"></i>
                        <i v-else class="pi pi-star text-muted-color text-sm"></i>
                    </button>
                </div>

                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center shrink-0 overflow-hidden"
                        :class="outfit.image_url || showFavicon(outfit.id, outfit.website) ? 'bg-surface-100 dark:bg-surface-800' : 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400'">
                        <img v-if="outfit.image_url" :src="outfit.image_url" class="w-full h-full object-cover" alt="" />
                        <img v-else-if="showFavicon(outfit.id, outfit.website)" :src="getFaviconUrl(outfit.website)!"
                            @error="onFaviconError(outfit.id)" class="w-full h-full object-contain p-2" alt="" />
                        <i v-else class="pi pi-tag"></i>
                    </div>

                    <div class="flex flex-col min-w-0">
                        <span class="font-bold text-color truncate">{{ outfit.name }}</span>

                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                            <span
                                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0 bg-surface-100 dark:bg-surface-800 text-muted-color">
                                {{ formatAmount(outfit.price) }}
                            </span>
                            <i v-if="outfit.note" class="pi pi-clipboard text-muted-color text-xs shrink-0" :title="outfit.note"></i>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1 shrink-0">
                    <a v-if="outfit.phone" :href="`tel:${outfit.phone}`" @click.stop
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-label="Appeler">
                        <i class="pi pi-phone text-sm"></i>
                    </a>
                    <a v-if="outfit.website" :href="outfit.website" target="_blank" rel="noopener" @click.stop
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-label="Voir le site web">
                        <i class="pi pi-globe text-sm"></i>
                    </a>
                    <i class="pi pi-chevron-right text-xs text-muted-color/60 ms-1"></i>
                </div>
            </div>
        </div>

        <Dialog
            v-model:visible="outfitDialog"
            :header="isEditMode ? 'Modifier la tenue' : 'Ajouter une tenue'"
            modal
            dismissableMask
            class="w-[90vw] max-w-md !rounded-2xl"
            :draggable="false"
        >
            <div class="flex flex-col gap-4 pt-2">

                <div class="flex flex-col items-center gap-2">
                    <button type="button" @click="fileInput?.click()"
                        class="w-24 h-24 rounded-2xl overflow-hidden bg-surface-100 dark:bg-surface-800 border border-surface-200 dark:border-surface-800 flex items-center justify-center text-muted-color"
                        aria-label="Choisir une image">
                        <img v-if="imagePreview" :src="imagePreview" class="w-full h-full object-cover" alt="" />
                        <i v-else class="pi pi-camera text-2xl"></i>
                    </button>
                    <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onImageSelected" />
                    <button v-if="imagePreview" type="button" @click="clearImage"
                        class="text-xs font-semibold text-red-500">Supprimer l'image</button>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Nom de la
                        tenue</label>
                    <InputText id="name" v-model.trim="formOutfit.name" autofocus placeholder="Ex: Robe champêtre - Maison Rose"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !formOutfit.name }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !formOutfit.name">Le nom est
                        obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="price" class="text-xs font-bold uppercase tracking-wider text-muted-color">Prix
                        total</label>
                    <InputNumber id="price" v-model="formOutfit.price" mode="currency" currency="EUR" locale="fr-FR"
                        :min="0" placeholder="0 €" class="w-full" :inputClass="'w-full !rounded-xl'"
                        :class="{ 'p-invalid': submitted && formOutfit.price === null }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && formOutfit.price === null">Le
                        prix est obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="phone" class="text-xs font-bold uppercase tracking-wider text-muted-color">Téléphone</label>
                    <InputText id="phone" v-model.trim="formOutfit.phone" placeholder="06 12 34 56 78"
                        class="w-full !rounded-xl" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="website" class="text-xs font-bold uppercase tracking-wider text-muted-color">Site
                        web</label>
                    <InputText id="website" v-model.trim="formOutfit.website" placeholder="https://..."
                        class="w-full !rounded-xl" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="note" class="text-xs font-bold uppercase tracking-wider text-muted-color">Note</label>
                    <Textarea id="note" v-model.trim="formOutfit.note" rows="3" autoResize
                        placeholder="Vos impressions, points positifs/négatifs..." class="w-full !rounded-xl" />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end w-full mt-4 gap-2">
                    <Button v-if="isEditMode" label="Supprimer" icon="pi pi-trash" severity="danger" variant="outlined"
                        class="!rounded-xl" @click="confirmDelete($event)" />
                    <Button label="Enregistrer" icon="pi pi-save" class="!rounded-xl px-4" :loading="saving"
                        @click="handleSubmit" />
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
import Textarea from 'primevue/textarea'
import ConfirmPopup from 'primevue/confirmpopup'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/api/client'
import { formatAmount } from '@/utils/currency'
import { useFavicon } from '@/composables/useFavicon'
import { useSimulationsStore } from '@/stores/simulations'
import { useWeddingStore } from '@/stores/wedding'
import QuoteStatusBadge, { type QuoteStatus } from '@/components/QuoteStatusBadge.vue'
import { useRealtimeResource } from '@/composables/useRealtimeResource'

type Spouse = 'spouse_1' | 'spouse_2'

interface Outfit {
    id: number
    name: string
    price: number
    spouse: Spouse
    phone: string | null
    website: string | null
    image_url: string | null
    note: string | null
    quote_status: QuoteStatus
}

// Forme du formulaire : le prix peut être vide (non encore saisi) tant que la
// tenue n'est pas enregistrée, contrairement à `Outfit` où il est toujours défini.
interface OutfitForm {
    id?: number
    name: string
    price: number | null
    spouse: Spouse
    phone: string | null
    website: string | null
    note: string | null
}

const confirm = useConfirm()
const { getFaviconUrl, showFavicon, onFaviconError } = useFavicon()
const simulationsStore = useSimulationsStore()
const weddingStore = useWeddingStore()

const activeSpouse = ref<Spouse>('spouse_1')
const activeSpouseName = computed(() => activeSpouse.value === 'spouse_1' ? weddingStore.spouse1Name : weddingStore.spouse2Name)

const outfits = ref<Outfit[]>([])
const isLoading = ref<boolean>(true)

const filteredOutfits = computed(() => outfits.value.filter(o => o.spouse === activeSpouse.value))

// La tenue choisie dans la simulation active remonte en tête de liste.
const sortedOutfits = computed(() => {
    const selectedIds = simulationsStore.active?.outfit_ids ?? []
    if (selectedIds.length === 0) return filteredOutfits.value
    return [...filteredOutfits.value].sort((a, b) => Number(selectedIds.includes(b.id)) - Number(selectedIds.includes(a.id)))
})

const isSelected = (outfit: Outfit): boolean => !!simulationsStore.active?.outfit_ids.includes(outfit.id)

const toggleSelected = async (outfitId: number) => {
    await simulationsStore.toggleOutfit(outfitId)
}

// Met à jour la liste en local avec la ressource renvoyée par l'API plutôt
// que de tout recharger, pour ne pas faire remonter la page en haut.
const upsertOutfit = (outfit: Outfit) => {
    const index = outfits.value.findIndex(o => o.id === outfit.id)
    if (index !== -1) {
        outfits.value[index] = outfit
        return
    }
    outfits.value.push(outfit)
    outfits.value.sort((a, b) => a.name.localeCompare(b.name))
}

const updateQuoteStatus = async (outfit: Outfit, quote_status: QuoteStatus) => {
    const { data } = await apiClient.put(`/outfits/${outfit.id}`, {
        name: outfit.name,
        price: outfit.price,
        spouse: outfit.spouse,
        phone: outfit.phone,
        website: outfit.website,
        note: outfit.note,
        quote_status,
    })
    upsertOutfit(data)
}

const fetchOutfits = async () => {
    isLoading.value = true
    try {
        const { data } = await apiClient.get<Outfit[]>('/outfits')
        outfits.value = data
    } finally {
        isLoading.value = false
    }
}

const outfitDialog = ref<boolean>(false)
const isEditMode = ref<boolean>(false)
const submitted = ref<boolean>(false)
const saving = ref<boolean>(false)

const fileInput = ref<HTMLInputElement>()
const imageFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)
const imageRemoved = ref<boolean>(false)

const emptyOutfit = (): OutfitForm => ({
    name: '',
    price: null,
    spouse: activeSpouse.value,
    phone: null,
    website: null,
    note: null,
})

const formOutfit = ref<OutfitForm>(emptyOutfit())

const onImageSelected = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0]
    if (!file) return
    imageFile.value = file
    imageRemoved.value = false
    imagePreview.value = URL.createObjectURL(file)
}

const clearImage = () => {
    imageFile.value = null
    imagePreview.value = null
    imageRemoved.value = true
    if (fileInput.value) fileInput.value.value = ''
}

const openNew = () => {
    isEditMode.value = false
    submitted.value = false
    formOutfit.value = emptyOutfit()
    imageFile.value = null
    imagePreview.value = null
    imageRemoved.value = false
    outfitDialog.value = true
}

const openEdit = (outfit: Outfit) => {
    isEditMode.value = true
    submitted.value = false
    formOutfit.value = { ...outfit }
    imageFile.value = null
    imagePreview.value = outfit.image_url
    imageRemoved.value = false
    outfitDialog.value = true
}

const confirmDelete = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Voulez-vous vraiment supprimer cette tenue ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: () => {
            onDelete(formOutfit.value.id)
            outfitDialog.value = false
        }
    })
}

const toFormData = (): FormData => {
    const data = new FormData()
    data.append('name', formOutfit.value.name)
    data.append('price', String(formOutfit.value.price))
    data.append('spouse', formOutfit.value.spouse)
    if (formOutfit.value.phone) data.append('phone', formOutfit.value.phone)
    if (formOutfit.value.website) data.append('website', formOutfit.value.website)
    if (formOutfit.value.note) data.append('note', formOutfit.value.note)
    if (imageFile.value) {
        data.append('image', imageFile.value)
    } else if (imageRemoved.value) {
        data.append('remove_image', '1')
    }
    return data
}

const handleSubmit = async () => {
    submitted.value = true
    if (!formOutfit.value.name.trim() || formOutfit.value.price === null) return

    saving.value = true
    try {
        const data = toFormData()
        // Le navigateur doit fixer lui-même le Content-Type (avec sa boundary
        // multipart) : on efface celui par défaut (application/json) du client.
        const config = { headers: { 'Content-Type': undefined } }

        if (isEditMode.value) {
            data.append('_method', 'PUT')
            const { data: updated } = await apiClient.post(`/outfits/${formOutfit.value.id}`, data, config)
            upsertOutfit(updated)
        } else {
            const { data: created } = await apiClient.post('/outfits', data, config)
            upsertOutfit(created)
        }
        outfitDialog.value = false
    } finally {
        saving.value = false
    }
}

const onDelete = async (id: number | string | undefined) => {
    if (id === undefined) return
    await apiClient.delete(`/outfits/${id}`)
    outfits.value = outfits.value.filter(o => o.id !== id)
}

onMounted(() => {
    fetchOutfits()
    simulationsStore.ensureLoaded()
})

useRealtimeResource<Outfit>('outfit', {
    onCreatedOrUpdated: upsertOutfit,
    onDeleted: (outfit) => {
        outfits.value = outfits.value.filter(o => o.id !== outfit.id)
    },
})
</script>
