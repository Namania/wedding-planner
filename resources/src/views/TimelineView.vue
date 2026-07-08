<template>
    <div class="space-y-5 pb-12">
        <ConfirmPopup class="mx-4" />

        <div class="flex justify-between items-center px-1 py-2 gap-4">
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold ms-2">Notre <span class="text-indigo-600 dark:text-indigo-400">planning</span></h1>
                <span
                    class="bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 text-xs font-bold px-2.5 py-0.5 rounded-full border border-surface-200 dark:border-surface-700">
                    {{ totalEvents }}
                </span>
            </div>
            <Button icon="pi pi-plus" class="!rounded-xl shrink-0 shadow-sm" @click="openNew"
                aria-label="Ajouter un événement" />
        </div>

        <div v-if="isLoading" class="space-y-3">
            <div v-for="n in 4" :key="n"
                class="bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm flex items-center gap-4">
                <Skeleton width="3rem" height="1rem" class="shrink-0" />
                <Skeleton width="60%" height="1rem" />
            </div>
        </div>

        <div v-else-if="!events || events.length === 0"
            class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
            <i class="pi pi-calendar text-4xl text-muted-color mb-3 block"></i>
            <p class="text-sm text-muted-color font-medium">Aucun événement pour le moment. Commencez par en ajouter
                un !</p>
        </div>

        <div v-else class="space-y-6">
            <div v-for="day in groupedEvents" :key="day.dateKey" class="space-y-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-muted-color px-1">{{ day.dateLabel }}</h2>

                <div class="space-y-2">
                    <div v-for="event in day.events" :key="event.id" @click="openEdit(event)"
                        class="bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm flex items-start gap-4 cursor-pointer active:scale-[0.99] transition-all duration-150">

                        <span class="text-sm font-bold text-primary shrink-0 pt-0.5 tabular-nums">{{ formatTime(event.starts_at) }}</span>

                        <div class="flex flex-col min-w-0">
                            <span class="font-bold text-color truncate">{{ event.title }}</span>
                            <span v-if="event.note" class="text-xs text-muted-color mt-0.5 truncate">{{ event.note }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog
            v-model:visible="eventDialog"
            :header="isEditMode ? 'Modifier l\'événement' : 'Ajouter un événement'"
            modal
            dismissableMask
            class="w-[90vw] max-w-md !rounded-2xl"
            :draggable="false"
        >
            <div class="flex flex-col gap-4 pt-2">

                <div class="flex flex-col gap-1.5">
                    <label for="title" class="text-xs font-bold uppercase tracking-wider text-muted-color">Titre</label>
                    <InputText id="title" v-model.trim="formEvent.title" autofocus placeholder="Ex: Cérémonie"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !formEvent.title }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !formEvent.title">Le titre est
                        obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="starts_at" class="text-xs font-bold uppercase tracking-wider text-muted-color">Date et
                        heure</label>
                    <DatePicker id="starts_at" v-model="formEvent.starts_at" showTime hourFormat="24"
                        dateFormat="dd/mm/yy" showIcon iconDisplay="input" placeholder="Sélectionnez une date et une heure"
                        class="w-full" :inputClass="'w-full !rounded-xl'" panelClass="mx-2"
                        :class="{ 'p-invalid': submitted && !formEvent.starts_at }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !formEvent.starts_at">La date
                        est obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="note" class="text-xs font-bold uppercase tracking-wider text-muted-color">Note</label>
                    <Textarea id="note" v-model.trim="formEvent.note" rows="3" autoResize
                        placeholder="Ex: Prévoir les alliances" class="w-full !rounded-xl" />
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
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import ConfirmPopup from 'primevue/confirmpopup'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/api/client'
import { useWeddingStore } from '@/stores/wedding'

interface TimelineEvent {
    id: number
    title: string
    starts_at: string
    note: string | null
}

// Forme du formulaire : starts_at est manipulée comme un objet Date par le DatePicker,
// alors que l'API attend/renvoie une chaîne ISO 8601.
interface TimelineEventForm {
    id?: number
    title: string
    starts_at: Date | null
    note: string | null
}

const confirm = useConfirm()
const weddingStore = useWeddingStore()

const events = ref<TimelineEvent[]>([])
const isLoading = ref<boolean>(true)

const totalEvents = computed(() => events.value.length)

const groupedEvents = computed(() => {
    const groups = new Map<string, TimelineEvent[]>()

    for (const event of events.value) {
        const dateKey = event.starts_at.slice(0, 10)
        if (!groups.has(dateKey)) groups.set(dateKey, [])
        groups.get(dateKey)!.push(event)
    }

    return Array.from(groups.entries()).map(([dateKey, dayEvents]) => ({
        dateKey,
        dateLabel: formatDayLabel(dateKey),
        events: dayEvents,
    }))
})

const formatDayLabel = (dateKey: string): string => {
    const label = new Date(dateKey).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    return label.charAt(0).toUpperCase() + label.slice(1)
}

const formatTime = (isoDate: string): string =>
    new Date(isoDate).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })

const fetchEvents = async () => {
    isLoading.value = true
    try {
        const { data } = await apiClient.get<TimelineEvent[]>('/timeline-events')
        events.value = data
    } finally {
        isLoading.value = false
    }
}

const eventDialog = ref<boolean>(false)
const isEditMode = ref<boolean>(false)
const submitted = ref<boolean>(false)

const emptyEvent = (): TimelineEventForm => ({ title: '', starts_at: new Date(weddingStore.date), note: null })

const formEvent = ref<TimelineEventForm>(emptyEvent())

const openNew = () => {
    isEditMode.value = false
    submitted.value = false
    formEvent.value = emptyEvent()
    eventDialog.value = true
}

const openEdit = (event: TimelineEvent) => {
    isEditMode.value = true
    submitted.value = false
    formEvent.value = { ...event, starts_at: new Date(event.starts_at) }
    eventDialog.value = true
}

const confirmDelete = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Voulez-vous vraiment supprimer cet événement ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: () => {
            onDelete(formEvent.value.id)
            eventDialog.value = false
        }
    })
}

const handleSubmit = async () => {
    submitted.value = true
    if (!formEvent.value.title.trim() || !formEvent.value.starts_at) return

    const payload = { ...formEvent.value, starts_at: formEvent.value.starts_at.toISOString() }

    if (isEditMode.value) {
        await apiClient.put(`/timeline-events/${formEvent.value.id}`, payload)
    } else {
        await apiClient.post('/timeline-events', payload)
    }
    eventDialog.value = false
    await fetchEvents()
}

const onDelete = async (id: number | string | undefined) => {
    if (id === undefined) return
    await apiClient.delete(`/timeline-events/${id}`)
    await fetchEvents()
}

onMounted(fetchEvents)
</script>
