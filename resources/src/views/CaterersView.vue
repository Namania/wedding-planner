<template>
    <div class="space-y-5 pb-12">
        <ConfirmPopup class="mx-4" />

        <div class="flex justify-between items-center px-1 py-2 gap-4">
            <h1 class="text-xl font-bold ms-2">Comparer les <span class="text-indigo-600 dark:text-indigo-400">traiteurs</span></h1>
            <Button icon="pi pi-plus" class="!rounded-xl shrink-0 shadow-sm" @click="openNew"
                aria-label="Ajouter un traiteur" />
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
            <div v-if="!caterers || caterers.length === 0"
                class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <i class="pi pi-shop text-4xl text-muted-color mb-3 block"></i>
                <p class="text-sm text-muted-color font-medium">Aucun traiteur pour le moment. Commencez par en ajouter
                    un !</p>
            </div>

            <div v-for="caterer in caterers" :key="caterer.id" @click="openEdit(caterer)"
                class="bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm flex items-center justify-between gap-3 cursor-pointer active:scale-[0.99] transition-all duration-150">

                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center shrink-0 overflow-hidden"
                        :class="showFavicon(caterer.id, caterer.website) ? 'bg-surface-100 dark:bg-surface-800 p-2' : 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400'">
                        <img v-if="showFavicon(caterer.id, caterer.website)" :src="getFaviconUrl(caterer.website)!"
                            @error="onFaviconError(caterer.id)" class="w-full h-full object-contain" alt="" />
                        <i v-else class="pi pi-shop"></i>
                    </div>

                    <div class="flex flex-col min-w-0">
                        <span class="font-bold text-color truncate">{{ caterer.name }}</span>

                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                            <span
                                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0 bg-surface-100 dark:bg-surface-800 text-muted-color">
                                {{ formatAmount(caterer.price_per_person) }} / pers.
                            </span>
                            <span v-if="caterer.service_type" :class="getServiceTypeClass(caterer.service_type)"
                                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0">
                                {{ getServiceTypeLabel(caterer.service_type) }}
                            </span>
                            <i v-if="caterer.note" class="pi pi-clipboard text-muted-color text-xs shrink-0" :title="caterer.note"></i>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1 shrink-0">
                    <a v-if="caterer.phone" :href="`tel:${caterer.phone}`" @click.stop
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-label="Appeler">
                        <i class="pi pi-phone text-sm"></i>
                    </a>
                    <a v-if="caterer.website" :href="caterer.website" target="_blank" rel="noopener" @click.stop
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-label="Voir le site web">
                        <i class="pi pi-globe text-sm"></i>
                    </a>
                    <i class="pi pi-chevron-right text-xs text-muted-color/60 ms-1"></i>
                </div>
            </div>
        </div>

        <Dialog
            v-model:visible="catererDialog"
            :header="isEditMode ? 'Modifier le traiteur' : 'Ajouter un traiteur'"
            modal
            dismissableMask
            class="w-[90vw] max-w-md !rounded-2xl"
            :draggable="false"
        >
            <div class="flex flex-col gap-4 pt-2">

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Nom du
                        traiteur</label>
                    <InputText id="name" v-model.trim="formCaterer.name" autofocus placeholder="Ex: Saveurs & Co"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !formCaterer.name }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !formCaterer.name">Le nom est
                        obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="price" class="text-xs font-bold uppercase tracking-wider text-muted-color">Prix par
                        personne</label>
                    <InputNumber id="price" v-model="formCaterer.price_per_person" mode="currency" currency="EUR"
                        locale="fr-FR" :min="0" placeholder="0 €" class="w-full" :inputClass="'w-full !rounded-xl'"
                        :class="{ 'p-invalid': submitted && formCaterer.price_per_person === null }" />
                    <small class="text-red-500 font-medium text-xs"
                        v-if="submitted && formCaterer.price_per_person === null">Le prix est obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="service_type" class="text-xs font-bold uppercase tracking-wider text-muted-color">Type
                        de prestation</label>
                    <Select id="service_type" v-model="formCaterer.service_type" :options="serviceTypeOptions"
                        optionLabel="label" optionValue="value" placeholder="Sélectionnez un type"
                        class="w-full !rounded-xl" showClear />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="phone" class="text-xs font-bold uppercase tracking-wider text-muted-color">Téléphone</label>
                    <InputText id="phone" v-model.trim="formCaterer.phone" placeholder="06 12 34 56 78"
                        class="w-full !rounded-xl" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="website" class="text-xs font-bold uppercase tracking-wider text-muted-color">Site
                        web</label>
                    <InputText id="website" v-model.trim="formCaterer.website" placeholder="https://..."
                        class="w-full !rounded-xl" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="note" class="text-xs font-bold uppercase tracking-wider text-muted-color">Note</label>
                    <Textarea id="note" v-model.trim="formCaterer.note" rows="3" autoResize
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
import { ref, onMounted } from 'vue'
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

type ServiceType = 'cocktail' | 'cocktail_dinatoire' | 'seated' | 'buffet' | 'food_truck' | 'brunch' | 'live_cooking'

interface Caterer {
    id: number
    name: string
    price_per_person: number
    phone: string | null
    website: string | null
    service_type: ServiceType | null
    note: string | null
}

// Forme du formulaire : le prix peut être vide (non encore saisi) tant que le
// traiteur n'est pas enregistré, contrairement à `Caterer` où il est toujours défini.
interface CatererForm {
    id?: number
    name: string
    price_per_person: number | null
    phone: string | null
    website: string | null
    service_type: ServiceType | null
    note: string | null
}

const confirm = useConfirm()
const { getFaviconUrl, showFavicon, onFaviconError } = useFavicon()

const serviceTypeOptions = [
    { label: 'Vin d\'honneur', value: 'cocktail' },
    { label: 'Cocktail dînatoire', value: 'cocktail_dinatoire' },
    { label: 'Repas assis', value: 'seated' },
    { label: 'Buffet', value: 'buffet' },
    { label: 'Food truck', value: 'food_truck' },
    { label: 'Brunch', value: 'brunch' },
    { label: 'Live cooking', value: 'live_cooking' },
]

const getServiceTypeLabel = (type: ServiceType): string => {
    const matched = serviceTypeOptions.find(option => option.value === type)
    return matched ? matched.label : type
}

const getServiceTypeClass = (type: ServiceType) => {
    switch (type) {
        case 'cocktail':
            return 'bg-teal-50 dark:bg-teal-950/40 text-teal-600 dark:text-teal-400 border border-teal-200 dark:border-teal-900/50'
        case 'cocktail_dinatoire':
            return 'bg-cyan-50 dark:bg-cyan-950/40 text-cyan-600 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-900/50'
        case 'seated':
            return 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50'
        case 'buffet':
            return 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50'
        case 'food_truck':
            return 'bg-orange-50 dark:bg-orange-950/40 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-900/50'
        case 'brunch':
            return 'bg-pink-50 dark:bg-pink-950/40 text-pink-600 dark:text-pink-400 border border-pink-200 dark:border-pink-900/50'
        case 'live_cooking':
            return 'bg-violet-50 dark:bg-violet-950/40 text-violet-600 dark:text-violet-400 border border-violet-200 dark:border-violet-900/50'
        default:
            return 'bg-surface-100 dark:bg-surface-800 text-muted-color'
    }
}

const caterers = ref<Caterer[]>([])
const isLoading = ref<boolean>(true)

const fetchCaterers = async () => {
    isLoading.value = true
    try {
        const { data } = await apiClient.get<Caterer[]>('/caterers')
        caterers.value = data
    } finally {
        isLoading.value = false
    }
}

const catererDialog = ref<boolean>(false)
const isEditMode = ref<boolean>(false)
const submitted = ref<boolean>(false)

const emptyCaterer = (): CatererForm => ({
    name: '',
    price_per_person: null,
    phone: null,
    website: null,
    service_type: null,
    note: null,
})

const formCaterer = ref<CatererForm>(emptyCaterer())

const openNew = () => {
    isEditMode.value = false
    submitted.value = false
    formCaterer.value = emptyCaterer()
    catererDialog.value = true
}

const openEdit = (caterer: Caterer) => {
    isEditMode.value = true
    submitted.value = false
    formCaterer.value = { ...caterer }
    catererDialog.value = true
}

const confirmDelete = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Voulez-vous vraiment supprimer ce traiteur ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: () => {
            onDelete(formCaterer.value.id)
            catererDialog.value = false
        }
    })
}

const handleSubmit = async () => {
    submitted.value = true
    if (!formCaterer.value.name.trim() || formCaterer.value.price_per_person === null) return

    if (isEditMode.value) {
        await apiClient.put(`/caterers/${formCaterer.value.id}`, formCaterer.value)
    } else {
        await apiClient.post('/caterers', formCaterer.value)
    }
    catererDialog.value = false
    await fetchCaterers()
}

const onDelete = async (id: number | string | undefined) => {
    if (id === undefined) return
    await apiClient.delete(`/caterers/${id}`)
    await fetchCaterers()
}

onMounted(fetchCaterers)
</script>
