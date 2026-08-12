<template>
    <div class="space-y-5 pb-12">
        <ConfirmPopup class="mx-4" />

        <div class="flex justify-between items-center px-1 py-2 gap-4">
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold ms-2">Plan de <span class="text-indigo-600 dark:text-indigo-400">table</span></h1>
                <span
                    class="bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 text-xs font-bold px-2.5 py-0.5 rounded-full border border-surface-200 dark:border-surface-700">
                    {{ totalTables }}
                </span>
            </div>
            <Button icon="pi pi-plus" class="!rounded-xl shrink-0 shadow-sm" @click="openNewTable"
                aria-label="Ajouter une table" />
        </div>

        <div v-if="isLoading" class="space-y-3">
            <div v-for="n in 3" :key="n"
                class="bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm space-y-2">
                <Skeleton width="40%" height="1.25rem" />
                <Skeleton width="60%" height="0.75rem" />
            </div>
        </div>

        <template v-else>
            <div class="space-y-2">
                <div class="flex items-center justify-between px-1">
                    <h2 class="font-bold text-color">Non assignés</h2>
                    <span class="text-sm font-semibold text-muted-color">{{ unassignedGuests.length }}</span>
                </div>

                <div v-if="unassignedGuests.length === 0"
                    class="text-center py-6 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 text-sm text-muted-color">
                    Tout le monde est placé !
                </div>

                <div v-else class="bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm flex flex-wrap gap-2">
                    <span v-for="guest in unassignedGuests" :key="guest.id"
                        class="text-xs font-semibold px-2.5 py-1 rounded-full whitespace-nowrap bg-surface-100 dark:bg-surface-800 text-muted-color">
                        {{ guest.name }}
                    </span>
                </div>
            </div>

            <div class="space-y-3">
                <div v-if="!tables || tables.length === 0"
                    class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                    <i class="pi pi-th-large text-4xl text-muted-color mb-3 block"></i>
                    <p class="text-sm text-muted-color font-medium">Aucune table pour le moment. Commencez par en
                        ajouter une !</p>
                </div>

                <div v-for="table in tables" :key="table.id"
                    class="bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm space-y-3">

                    <div class="flex items-center justify-between gap-3 cursor-pointer" @click="openEditTable(table)">
                        <span class="font-bold text-color truncate">{{ table.name }}</span>
                        <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0"
                            :class="table.guests.length > table.capacity ? 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/50' : 'bg-surface-100 dark:bg-surface-800 text-muted-color'">
                            {{ table.guests.length }} / {{ table.capacity }} places
                        </span>
                    </div>

                    <div v-if="table.guests.length > 0" class="flex flex-col gap-1.5">
                        <div v-for="guest in table.guests" :key="guest.id"
                            class="flex items-center justify-between gap-2 px-3 py-1.5 rounded-xl bg-surface-50 dark:bg-surface-800/60">
                            <span class="text-sm text-color truncate">{{ guest.name }}</span>
                            <button type="button" class="text-muted-color hover:text-red-500 shrink-0"
                                @click="unassignGuest(guest.id)" aria-label="Retirer de la table">
                                <i class="pi pi-times text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <Select :modelValue="null" @update:modelValue="(guestId) => assignGuest(table.id, guestId)"
                        :options="unassignedGuests" optionLabel="name" optionValue="id"
                        placeholder="+ Ajouter un invité" class="w-full !rounded-xl"
                        :disabled="unassignedGuests.length === 0" />
                </div>
            </div>
        </template>

        <Dialog
            v-model:visible="tableDialog"
            :header="isEditMode ? 'Modifier la table' : 'Ajouter une table'"
            modal
            dismissableMask
            class="w-[90vw] max-w-md !rounded-2xl"
            :draggable="false"
        >
            <div class="flex flex-col gap-4 pt-2">

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Nom de la
                        table</label>
                    <InputText id="name" v-model.trim="formTable.name" autofocus placeholder="Ex: Table 1"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !formTable.name }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !formTable.name">Le nom est
                        obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="capacity" class="text-xs font-bold uppercase tracking-wider text-muted-color">Capacité
                        (places)</label>
                    <InputNumber id="capacity" v-model="formTable.capacity" :min="1" placeholder="8"
                        class="w-full" :inputClass="'w-full !rounded-xl'"
                        :class="{ 'p-invalid': submitted && !formTable.capacity }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !formTable.capacity">La
                        capacité est obligatoire.</small>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end w-full mt-4 gap-2">
                    <Button v-if="isEditMode" label="Supprimer" icon="pi pi-trash" severity="danger" variant="outlined"
                        class="!rounded-xl" @click="confirmDeleteTable($event)" />
                    <Button label="Enregistrer" icon="pi pi-save" class="!rounded-xl px-4" @click="handleSubmitTable" />
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
import ConfirmPopup from 'primevue/confirmpopup'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/api/client'

interface Guest {
    id: number
    name: string
    role: string | null
    confirmed: boolean | null
    attendance: string[] | null
    seating_table_id: number | null
}

interface TableGuest {
    id: number
    name: string
    confirmed: boolean | null
}

interface SeatingTable {
    id: number
    name: string
    capacity: number
    guests: TableGuest[]
}

interface SeatingTableForm {
    id?: number
    name: string
    capacity: number | null
}

const confirm = useConfirm()

const tables = ref<SeatingTable[]>([])
const allGuests = ref<Guest[]>([])
const isLoading = ref<boolean>(true)

const totalTables = computed(() => tables.value.length)
// Le plan de table ne concerne que le repas : on exclut les invités qui ne
// viennent pas du tout, ou qui ne restent pas pour le repas.
const attendsDinner = (guest: Guest) => guest.confirmed !== false && (guest.attendance ?? []).includes('dinner')

const unassignedGuests = computed(() => allGuests.value.filter(g => g.seating_table_id === null && attendsDinner(g)))

const fetchAll = async () => {
    isLoading.value = true
    try {
        const [tablesRes, guestsRes] = await Promise.all([
            apiClient.get<SeatingTable[]>('/seating-tables'),
            apiClient.get<Guest[]>('/guests'),
        ])
        tables.value = tablesRes.data
        allGuests.value = guestsRes.data
    } finally {
        isLoading.value = false
    }
}

const assignGuest = async (tableId: number, guestId: number | null) => {
    if (guestId === null) return
    const guest = allGuests.value.find(g => g.id === guestId)
    if (!guest) return
    await apiClient.put(`/guests/${guestId}`, { ...guest, seating_table_id: tableId })
    await fetchAll()
}

const unassignGuest = async (guestId: number) => {
    const guest = allGuests.value.find(g => g.id === guestId)
    if (!guest) return
    await apiClient.put(`/guests/${guestId}`, { ...guest, seating_table_id: null })
    await fetchAll()
}

const tableDialog = ref<boolean>(false)
const isEditMode = ref<boolean>(false)
const submitted = ref<boolean>(false)

const emptyTable = (): SeatingTableForm => ({ name: '', capacity: null })

const formTable = ref<SeatingTableForm>(emptyTable())

const openNewTable = () => {
    isEditMode.value = false
    submitted.value = false
    formTable.value = emptyTable()
    tableDialog.value = true
}

const openEditTable = (table: SeatingTable) => {
    isEditMode.value = true
    submitted.value = false
    formTable.value = { id: table.id, name: table.name, capacity: table.capacity }
    tableDialog.value = true
}

const confirmDeleteTable = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Voulez-vous vraiment supprimer cette table ? Les invités assignés redeviendront non assignés.',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: () => {
            onDeleteTable(formTable.value.id)
            tableDialog.value = false
        }
    })
}

const handleSubmitTable = async () => {
    submitted.value = true
    if (!formTable.value.name.trim() || !formTable.value.capacity) return

    if (isEditMode.value) {
        await apiClient.put(`/seating-tables/${formTable.value.id}`, formTable.value)
    } else {
        await apiClient.post('/seating-tables', formTable.value)
    }
    tableDialog.value = false
    await fetchAll()
}

const onDeleteTable = async (id: number | string | undefined) => {
    if (id === undefined) return
    await apiClient.delete(`/seating-tables/${id}`)
    await fetchAll()
}

onMounted(fetchAll)
</script>
