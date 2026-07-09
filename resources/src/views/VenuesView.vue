<template>
    <div class="space-y-5 pb-12">
        <ConfirmPopup class="mx-4" />

        <div class="flex justify-between items-center px-1 py-2 gap-4">
            <h1 class="text-xl font-bold ms-2">Comparer les <span class="text-indigo-600 dark:text-indigo-400">lieux</span></h1>
            <Button icon="pi pi-plus" class="!rounded-xl shrink-0 shadow-sm" @click="openNew"
                aria-label="Ajouter un lieu" />
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
            <div v-if="!venues || venues.length === 0"
                class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <i class="pi pi-map-marker text-4xl text-muted-color mb-3 block"></i>
                <p class="text-sm text-muted-color font-medium">Aucun lieu pour le moment. Commencez par en ajouter un
                    !</p>
            </div>

            <div v-for="venue in sortedVenues" :key="venue.id" @click="openEdit(venue)"
                class="relative bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl shadow-sm flex items-center justify-between gap-3 cursor-pointer active:scale-[0.99] transition-all duration-150"
                :class="isSelected(venue) ? 'border-2 border-amber-400' : 'border border-surface-200 dark:border-surface-800'">

                <button v-if="simulationsStore.active" type="button" @click.stop="toggleSelected(venue.id)"
                    class="absolute -top-2 -right-2 w-7 h-7 rounded-full bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 shadow-sm flex items-center justify-center z-10"
                    title="Sélectionner pour la simulation active" aria-label="Sélectionner pour la simulation active">
                    <i v-if="isSelected(venue)" class="pi pi-star-fill text-amber-400 text-sm"></i>
                    <i v-else class="pi pi-star text-muted-color text-sm"></i>
                </button>

                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center shrink-0 overflow-hidden"
                        :class="showFavicon(venue.id, venue.website) ? 'bg-surface-100 dark:bg-surface-800 p-2' : 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400'">
                        <img v-if="showFavicon(venue.id, venue.website)" :src="getFaviconUrl(venue.website)!"
                            @error="onFaviconError(venue.id)" class="w-full h-full object-contain" alt="" />
                        <i v-else class="pi pi-map-marker"></i>
                    </div>

                    <div class="flex flex-col min-w-0">
                        <div class="flex flex items-center gap-2">
                            <span class="font-bold text-color truncate min-w-0">{{ venue.name }}</span>
                            <span
                                v-if="venue.note"
                                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0 bg-surface-100 dark:bg-surface-800 text-muted-color">
                                {{ formatAmount(venue.price) }}
                            </span>
                        </div>

                        <span v-if="venue.note" class="text-xs text-muted-color mt-1 truncate">{{ venue.note }}</span>
                        <span
                            v-else
                            class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0 bg-surface-100 dark:bg-surface-800 text-muted-color">
                            {{ formatAmount(venue.price) }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-1 shrink-0">
                    <a v-if="venue.website" :href="venue.website" target="_blank" rel="noopener" @click.stop
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-label="Voir le site web">
                        <i class="pi pi-globe text-sm"></i>
                    </a>
                    <a v-if="venue.maps_url" :href="venue.maps_url" target="_blank" rel="noopener" @click.stop
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-label="Voir sur Google Maps">
                        <i class="pi pi-map text-sm"></i>
                    </a>
                    <i class="pi pi-chevron-right text-xs text-muted-color/60 ms-1"></i>
                </div>
            </div>
        </div>

        <Dialog
            v-model:visible="venueDialog"
            :header="isEditMode ? 'Modifier le lieu' : 'Ajouter un lieu'"
            modal
            dismissableMask
            class="w-[90vw] max-w-md !rounded-2xl"
            :draggable="false"
        >
            <div class="flex flex-col gap-4 pt-2">

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Nom du
                        lieu</label>
                    <InputText id="name" v-model.trim="formVenue.name" autofocus placeholder="Ex: Domaine des Roses"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !formVenue.name }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !formVenue.name">Le nom est
                        obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="price" class="text-xs font-bold uppercase tracking-wider text-muted-color">Prix</label>
                    <InputNumber id="price" v-model="formVenue.price" mode="currency" currency="EUR" locale="fr-FR"
                        :min="0" placeholder="0 €" class="w-full" :inputClass="'w-full !rounded-xl'"
                        :class="{ 'p-invalid': submitted && formVenue.price === null }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && formVenue.price === null">Le
                        prix est obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="website" class="text-xs font-bold uppercase tracking-wider text-muted-color">Site
                        web</label>
                    <InputText id="website" v-model.trim="formVenue.website" placeholder="https://..."
                        class="w-full !rounded-xl" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="maps_url" class="text-xs font-bold uppercase tracking-wider text-muted-color">Localisation
                        (lien Google Maps)</label>
                    <InputText id="maps_url" v-model.trim="formVenue.maps_url" placeholder="https://maps.google.com/..."
                        class="w-full !rounded-xl" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="note" class="text-xs font-bold uppercase tracking-wider text-muted-color">Note</label>
                    <Textarea id="note" v-model.trim="formVenue.note" rows="3" autoResize
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
import Textarea from 'primevue/textarea'
import ConfirmPopup from 'primevue/confirmpopup'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/api/client'
import { formatAmount } from '@/utils/currency'
import { useFavicon } from '@/composables/useFavicon'
import { useSimulationsStore } from '@/stores/simulations'

interface Venue {
    id: number
    name: string
    website: string | null
    maps_url: string | null
    price: number
    note: string | null
}

// Forme du formulaire : le prix peut être vide (non encore saisi) tant que le
// lieu n'est pas enregistré, contrairement à `Venue` où il est toujours défini.
interface VenueForm {
    id?: number
    name: string
    website: string | null
    maps_url: string | null
    price: number | null
    note: string | null
}

const confirm = useConfirm()
const { getFaviconUrl, showFavicon, onFaviconError } = useFavicon()
const simulationsStore = useSimulationsStore()

const venues = ref<Venue[]>([])
const isLoading = ref<boolean>(true)

// Le lieu choisi dans la simulation active remonte en tête de liste.
const sortedVenues = computed(() => {
    const selectedId = simulationsStore.active?.venue_id
    if (!selectedId) return venues.value
    return [...venues.value].sort((a, b) => (a.id === selectedId ? -1 : b.id === selectedId ? 1 : 0))
})

const isSelected = (venue: Venue): boolean => simulationsStore.active?.venue_id === venue.id

const toggleSelected = async (venueId: number) => {
    await simulationsStore.toggleSelection('venue_id', venueId)
}

const fetchVenues = async () => {
    isLoading.value = true
    try {
        const { data } = await apiClient.get<Venue[]>('/venues')
        venues.value = data
    } finally {
        isLoading.value = false
    }
}

const venueDialog = ref<boolean>(false)
const isEditMode = ref<boolean>(false)
const submitted = ref<boolean>(false)

const emptyVenue = (): VenueForm => ({ name: '', website: null, maps_url: null, price: null, note: null })

const formVenue = ref<VenueForm>(emptyVenue())

const openNew = () => {
    isEditMode.value = false
    submitted.value = false
    formVenue.value = emptyVenue()
    venueDialog.value = true
}

const openEdit = (venue: Venue) => {
    isEditMode.value = true
    submitted.value = false
    formVenue.value = { ...venue }
    venueDialog.value = true
}

const confirmDelete = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Voulez-vous vraiment supprimer ce lieu ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: () => {
            onDelete(formVenue.value.id)
            venueDialog.value = false
        }
    })
}

const handleSubmit = async () => {
    submitted.value = true
    if (!formVenue.value.name.trim() || formVenue.value.price === null) return

    if (isEditMode.value) {
        await apiClient.put(`/venues/${formVenue.value.id}`, formVenue.value)
    } else {
        await apiClient.post('/venues', formVenue.value)
    }
    venueDialog.value = false
    await fetchVenues()
}

const onDelete = async (id: number | string | undefined) => {
    if (id === undefined) return
    await apiClient.delete(`/venues/${id}`)
    await fetchVenues()
}

onMounted(() => {
    fetchVenues()
    simulationsStore.ensureLoaded()
})
</script>
