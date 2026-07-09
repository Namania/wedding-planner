<template>
    <div class="space-y-5 pb-12">
        <ConfirmPopup class="mx-4" />

        <div class="flex justify-between items-center px-1 py-2 gap-4">
            <h1 class="text-xl font-bold ms-2">Comparer les <span class="text-indigo-600 dark:text-indigo-400">animations</span></h1>
            <Button icon="pi pi-plus" class="!rounded-xl shrink-0 shadow-sm" @click="openNew"
                aria-label="Ajouter une animation" />
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
            <div v-if="!animations || animations.length === 0"
                class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <i class="pi pi-video text-4xl text-muted-color mb-3 block"></i>
                <p class="text-sm text-muted-color font-medium">Aucune animation pour le moment. Commencez par en
                    ajouter une !</p>
            </div>

            <div v-for="animation in sortedAnimations" :key="animation.id" @click="openEdit(animation)"
                class="relative bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl shadow-sm flex items-center justify-between gap-3 cursor-pointer active:scale-[0.99] transition-all duration-150"
                :class="isSelected(animation) ? 'border-2 border-amber-400' : 'border border-surface-200 dark:border-surface-800'">

                <div class="absolute -top-2 -right-2 flex items-center gap-1.5 z-10">
                    <QuoteStatusBadge :status="animation.quote_status" @update:status="updateQuoteStatus(animation, $event)" />

                    <button v-if="simulationsStore.active" type="button" @click.stop="toggleSelected(animation.id)"
                        class="w-7 h-7 rounded-full bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 shadow-sm flex items-center justify-center"
                        title="Sélectionner pour la simulation active" aria-label="Sélectionner pour la simulation active">
                        <i v-if="isSelected(animation)" class="pi pi-star-fill text-amber-400 text-sm"></i>
                        <i v-else class="pi pi-star text-muted-color text-sm"></i>
                    </button>
                </div>

                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center shrink-0 overflow-hidden"
                        :class="showFavicon(animation.id, animation.website) ? 'bg-surface-100 dark:bg-surface-800 p-2' : 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400'">
                        <img v-if="showFavicon(animation.id, animation.website)" :src="getFaviconUrl(animation.website)!"
                            @error="onFaviconError(animation.id)" class="w-full h-full object-contain" alt="" />
                        <i v-else class="pi pi-video"></i>
                    </div>

                    <div class="flex flex-col min-w-0">
                        <span class="font-bold text-color truncate">{{ animation.name }}</span>

                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                            <span
                                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0 bg-surface-100 dark:bg-surface-800 text-muted-color">
                                {{ formatAmount(animation.price) }}
                            </span>
                            <span v-if="animation.type" :class="getTypeClass(animation.type)"
                                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0">
                                {{ getTypeLabel(animation.type) }}
                            </span>
                            <i v-if="animation.note" class="pi pi-clipboard text-muted-color text-xs shrink-0" :title="animation.note"></i>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1 shrink-0">
                    <a v-if="animation.phone" :href="`tel:${animation.phone}`" @click.stop
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-label="Appeler">
                        <i class="pi pi-phone text-sm"></i>
                    </a>
                    <a v-if="animation.website" :href="animation.website" target="_blank" rel="noopener" @click.stop
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-label="Voir le site web">
                        <i class="pi pi-globe text-sm"></i>
                    </a>
                    <i class="pi pi-chevron-right text-xs text-muted-color/60 ms-1"></i>
                </div>
            </div>
        </div>

        <Dialog
            v-model:visible="animationDialog"
            :header="isEditMode ? 'Modifier l\'animation' : 'Ajouter une animation'"
            modal
            dismissableMask
            class="w-[90vw] max-w-md !rounded-2xl"
            :draggable="false"
        >
            <div class="flex flex-col gap-4 pt-2">

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Nom de
                        l'animation</label>
                    <InputText id="name" v-model.trim="formAnimation.name" autofocus placeholder="Ex: DJ Max"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !formAnimation.name }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !formAnimation.name">Le nom
                        est obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="price" class="text-xs font-bold uppercase tracking-wider text-muted-color">Prix
                        total</label>
                    <InputNumber id="price" v-model="formAnimation.price" mode="currency" currency="EUR" locale="fr-FR"
                        :min="0" placeholder="0 €" class="w-full" :inputClass="'w-full !rounded-xl'"
                        :class="{ 'p-invalid': submitted && formAnimation.price === null }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && formAnimation.price === null">Le
                        prix est obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="type" class="text-xs font-bold uppercase tracking-wider text-muted-color">Type
                        d'animation</label>
                    <Select id="type" v-model="formAnimation.type" :options="typeOptions" optionLabel="label"
                        optionValue="value" placeholder="Sélectionnez un type" class="w-full !rounded-xl" showClear />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="phone" class="text-xs font-bold uppercase tracking-wider text-muted-color">Téléphone</label>
                    <InputText id="phone" v-model.trim="formAnimation.phone" placeholder="06 12 34 56 78"
                        class="w-full !rounded-xl" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="website" class="text-xs font-bold uppercase tracking-wider text-muted-color">Site
                        web</label>
                    <InputText id="website" v-model.trim="formAnimation.website" placeholder="https://..."
                        class="w-full !rounded-xl" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="note" class="text-xs font-bold uppercase tracking-wider text-muted-color">Note</label>
                    <Textarea id="note" v-model.trim="formAnimation.note" rows="3" autoResize
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

type AnimationType = 'dj' | 'live_band' | 'photobooth' | 'fireworks' | 'magician' | 'show' | 'casino' | 'video_mapping'

interface Animation {
    id: number
    name: string
    price: number
    phone: string | null
    website: string | null
    type: AnimationType | null
    note: string | null
    quote_status: QuoteStatus
}

// Forme du formulaire : le prix peut être vide (non encore saisi) tant que
// l'animation n'est pas enregistrée, contrairement à `Animation` où il est toujours défini.
interface AnimationForm {
    id?: number
    name: string
    price: number | null
    phone: string | null
    website: string | null
    type: AnimationType | null
    note: string | null
}

const confirm = useConfirm()
const { getFaviconUrl, showFavicon, onFaviconError } = useFavicon()

const typeOptions = [
    { label: 'DJ', value: 'dj' },
    { label: 'Groupe / orchestre', value: 'live_band' },
    { label: 'Photobooth', value: 'photobooth' },
    { label: "Feu d'artifice", value: 'fireworks' },
    { label: 'Magicien', value: 'magician' },
    { label: 'Spectacle', value: 'show' },
    { label: 'Animation casino', value: 'casino' },
    { label: 'Mapping vidéo', value: 'video_mapping' },
]

const getTypeLabel = (type: AnimationType): string => {
    const matched = typeOptions.find(option => option.value === type)
    return matched ? matched.label : type
}

const getTypeClass = (type: AnimationType) => {
    switch (type) {
        case 'dj':
            return 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-900/50'
        case 'live_band':
            return 'bg-violet-50 dark:bg-violet-950/40 text-violet-600 dark:text-violet-400 border border-violet-200 dark:border-violet-900/50'
        case 'photobooth':
            return 'bg-pink-50 dark:bg-pink-950/40 text-pink-600 dark:text-pink-400 border border-pink-200 dark:border-pink-900/50'
        case 'fireworks':
            return 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/50'
        case 'magician':
            return 'bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-900/50'
        case 'show':
            return 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50'
        case 'casino':
            return 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50'
        case 'video_mapping':
            return 'bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-900/50'
        default:
            return 'bg-surface-100 dark:bg-surface-800 text-muted-color'
    }
}

const simulationsStore = useSimulationsStore()

const animations = ref<Animation[]>([])
const isLoading = ref<boolean>(true)

// Les animations choisies dans la simulation active (il peut y en avoir
// plusieurs, contrairement au lieu/traiteur/fleuriste) remontent en tête de liste.
const sortedAnimations = computed(() => {
    const selectedIds = simulationsStore.active?.animation_ids ?? []
    if (selectedIds.length === 0) return animations.value
    return [...animations.value].sort((a, b) => Number(selectedIds.includes(b.id)) - Number(selectedIds.includes(a.id)))
})

const isSelected = (animation: Animation): boolean => !!simulationsStore.active?.animation_ids.includes(animation.id)

const toggleSelected = async (animationId: number) => {
    await simulationsStore.toggleAnimation(animationId)
}

const updateQuoteStatus = async (animation: Animation, quote_status: QuoteStatus) => {
    await apiClient.put(`/animations/${animation.id}`, { ...animation, quote_status })
    await fetchAnimations()
}

const fetchAnimations = async () => {
    isLoading.value = true
    try {
        const { data } = await apiClient.get<Animation[]>('/animations')
        animations.value = data
    } finally {
        isLoading.value = false
    }
}

const animationDialog = ref<boolean>(false)
const isEditMode = ref<boolean>(false)
const submitted = ref<boolean>(false)

const emptyAnimation = (): AnimationForm => ({
    name: '',
    price: null,
    phone: null,
    website: null,
    type: null,
    note: null,
})

const formAnimation = ref<AnimationForm>(emptyAnimation())

const openNew = () => {
    isEditMode.value = false
    submitted.value = false
    formAnimation.value = emptyAnimation()
    animationDialog.value = true
}

const openEdit = (animation: Animation) => {
    isEditMode.value = true
    submitted.value = false
    formAnimation.value = { ...animation }
    animationDialog.value = true
}

const confirmDelete = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Voulez-vous vraiment supprimer cette animation ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: () => {
            onDelete(formAnimation.value.id)
            animationDialog.value = false
        }
    })
}

const handleSubmit = async () => {
    submitted.value = true
    if (!formAnimation.value.name.trim() || formAnimation.value.price === null) return

    if (isEditMode.value) {
        await apiClient.put(`/animations/${formAnimation.value.id}`, formAnimation.value)
    } else {
        await apiClient.post('/animations', formAnimation.value)
    }
    animationDialog.value = false
    await fetchAnimations()
}

const onDelete = async (id: number | string | undefined) => {
    if (id === undefined) return
    await apiClient.delete(`/animations/${id}`)
    await fetchAnimations()
}

onMounted(() => {
    fetchAnimations()
    simulationsStore.ensureLoaded()
})
</script>
