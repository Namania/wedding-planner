<template>
    <div class="space-y-5 pb-12">
        <ConfirmPopup class="mx-4" />

        <div class="flex justify-between items-center px-1 py-2 gap-4">
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold ms-2">La <span class="text-indigo-600 dark:text-indigo-400">checklist</span></h1>
                <span
                    class="bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 text-xs font-bold px-2.5 py-0.5 rounded-full border border-surface-200 dark:border-surface-700">
                    {{ totalTasks }}
                </span>
            </div>
            <Button icon="pi pi-plus" class="!rounded-xl shrink-0 shadow-sm" @click="openNew"
                aria-label="Ajouter une tâche" />
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
            <div v-if="!tasks || tasks.length === 0"
                class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <i class="pi pi-list-check text-4xl text-muted-color mb-3 block"></i>
                <p class="text-sm text-muted-color font-medium">Aucune tâche pour le moment. Commencez par en ajouter
                    une !</p>
            </div>

            <div v-for="task in tasks" :key="task.id" @click="openEdit(task)"
                class="bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm flex items-center justify-between gap-3 cursor-pointer active:scale-[0.99] transition-all duration-150">

                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center shrink-0"
                        :class="getStatusIconClass(task.status)">
                        <i :class="getStatusIcon(task.status)"></i>
                    </div>

                    <div class="flex flex-col min-w-0">
                        <span class="font-bold text-color truncate"
                            :class="{ 'line-through text-muted-color font-medium': task.status === 'done' }">
                            {{ task.title }}
                        </span>

                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                            <span v-if="task.category" :class="getCategoryClass(task.category)"
                                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0">
                                {{ getCategoryLabel(task.category) }}
                            </span>
                            <span v-if="task.due_date"
                                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0"
                                :class="isOverdue(task) ? 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/50' : 'bg-surface-100 dark:bg-surface-800 text-muted-color'">
                                {{ formatDate(task.due_date) }}
                            </span>
                        </div>
                    </div>
                </div>

                <i class="pi pi-chevron-right text-xs text-muted-color/60 shrink-0"></i>
            </div>
        </div>

        <Dialog
            v-model:visible="taskDialog"
            :header="isEditMode ? 'Modifier la tâche' : 'Ajouter une tâche'"
            modal
            dismissableMask
            class="w-[90vw] max-w-md !rounded-2xl"
            :draggable="false"
        >
            <div class="flex flex-col gap-4 pt-2">

                <div class="flex flex-col gap-1.5">
                    <label for="title" class="text-xs font-bold uppercase tracking-wider text-muted-color">Titre</label>
                    <InputText id="title" v-model.trim="formTask.title" autofocus placeholder="Ex: Réserver la salle"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !formTask.title }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !formTask.title">Le titre est
                        obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-wider text-muted-color">Statut</label>
                    <SelectButton v-model="formTask.status" :options="statusOptions" optionLabel="label"
                        optionValue="value" :allowEmpty="false" class="w-full text-sm"
                        :pt="{ pcButton: { class: 'flex-1 justify-center' } }" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="category" class="text-xs font-bold uppercase tracking-wider text-muted-color">Catégorie</label>
                    <Select id="category" v-model="formTask.category" :options="categoryOptions" optionLabel="label"
                        optionValue="value" placeholder="Sélectionnez une catégorie" class="w-full !rounded-xl"
                        showClear />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="due_date" class="text-xs font-bold uppercase tracking-wider text-muted-color">Échéance</label>
                    <DatePicker id="due_date" v-model="formTask.due_date" dateFormat="dd/mm/yy" showIcon
                        iconDisplay="input" showButtonBar placeholder="Sélectionnez une date" class="w-full"
                        :inputClass="'w-full !rounded-xl'" panelClass="mx-4" />
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
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import DatePicker from 'primevue/datepicker'
import ConfirmPopup from 'primevue/confirmpopup'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/api/client'
import { useWeddingStore } from '@/stores/wedding'
import { parseDateOnly, toDateInputValue } from '@/utils/date'
import { useRealtimeResource } from '@/composables/useRealtimeResource'

type TaskStatus = 'todo' | 'in_progress' | 'done'
type TaskCategory = 'administratif' | 'prestataires' | 'tenues' | 'deco' | 'invitations' | 'beaute' | 'logistique' | 'autre'

interface Task {
    id: number
    title: string
    category: TaskCategory | null
    status: TaskStatus
    due_date: string | null
}

// Forme du formulaire : due_date est manipulée comme un objet Date par le DatePicker,
// alors que l'API attend/renvoie une chaîne 'YYYY-MM-DD'.
interface TaskForm {
    id?: number
    title: string
    category: TaskCategory | null
    status: TaskStatus
    due_date: Date | null
}

const confirm = useConfirm()
const weddingStore = useWeddingStore()

const statusOptions = [
    { label: 'À faire', value: 'todo' },
    { label: 'En cours', value: 'in_progress' },
    { label: 'Terminé', value: 'done' },
]

const categoryOptions = [
    { label: 'Administratif', value: 'administratif' },
    { label: 'Prestataires', value: 'prestataires' },
    { label: 'Tenues', value: 'tenues' },
    { label: 'Décoration', value: 'deco' },
    { label: 'Invitations', value: 'invitations' },
    { label: 'Beauté', value: 'beaute' },
    { label: 'Logistique', value: 'logistique' },
    { label: 'Autre', value: 'autre' },
]

const getCategoryLabel = (category: TaskCategory): string => {
    const matched = categoryOptions.find(option => option.value === category)
    return matched ? matched.label : category
}

const getCategoryClass = (category: TaskCategory) => {
    switch (category) {
        case 'administratif':
            return 'bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700'
        case 'prestataires':
            return 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50'
        case 'tenues':
            return 'bg-violet-50 dark:bg-violet-950/40 text-violet-600 dark:text-violet-400 border border-violet-200 dark:border-violet-900/50'
        case 'deco':
            return 'bg-pink-50 dark:bg-pink-950/40 text-pink-600 dark:text-pink-400 border border-pink-200 dark:border-pink-900/50'
        case 'invitations':
            return 'bg-cyan-50 dark:bg-cyan-950/40 text-cyan-600 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-900/50'
        case 'beaute':
            return 'bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50'
        case 'logistique':
            return 'bg-orange-50 dark:bg-orange-950/40 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-900/50'
        default:
            return 'bg-surface-100 dark:bg-surface-800 text-muted-color'
    }
}

const getStatusIcon = (status: TaskStatus): string => {
    switch (status) {
        case 'done':
            return 'pi pi-check'
        case 'in_progress':
            return 'pi pi-hourglass'
        default:
            return 'pi pi-clock'
    }
}

const getStatusIconClass = (status: TaskStatus): string => {
    switch (status) {
        case 'done':
            return 'bg-green-100 dark:bg-green-950/60 text-green-600 dark:text-green-400'
        case 'in_progress':
            return 'bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400'
        default:
            return 'bg-surface-100 dark:bg-surface-800 text-muted-color'
    }
}

const isOverdue = (task: Task): boolean => {
    if (!task.due_date || task.status === 'done') return false
    return parseDateOnly(task.due_date) < new Date(new Date().toDateString())
}

const formatDate = (date: string): string =>
    parseDateOnly(date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })

const tasks = ref<Task[]>([])
const isLoading = ref<boolean>(true)

const totalTasks = computed(() => tasks.value.length)

// Met à jour la liste en local avec la ressource renvoyée par l'API plutôt
// que de tout recharger, pour ne pas faire remonter la page en haut. Une
// tâche modifiée garde sa place ; seule une tâche créée est insérée triée
// (même ordre que l'API : échéance puis titre).
const upsertTask = (task: Task) => {
    const index = tasks.value.findIndex(t => t.id === task.id)
    if (index !== -1) {
        tasks.value[index] = task
        return
    }
    tasks.value.push(task)
    tasks.value.sort((a, b) => {
        if (a.due_date !== b.due_date) {
            if (a.due_date === null) return 1
            if (b.due_date === null) return -1
            return a.due_date < b.due_date ? -1 : 1
        }
        return a.title.localeCompare(b.title)
    })
}

const fetchTasks = async () => {
    isLoading.value = true
    try {
        const { data } = await apiClient.get<Task[]>('/tasks')
        tasks.value = data
    } finally {
        isLoading.value = false
    }
}

const taskDialog = ref<boolean>(false)
const isEditMode = ref<boolean>(false)
const submitted = ref<boolean>(false)

const emptyTask = (): TaskForm => ({ title: '', category: null, status: 'todo', due_date: new Date(weddingStore.date) })

const formTask = ref<TaskForm>(emptyTask())

const openNew = () => {
    isEditMode.value = false
    submitted.value = false
    formTask.value = emptyTask()
    taskDialog.value = true
}

const openEdit = (task: Task) => {
    isEditMode.value = true
    submitted.value = false
    formTask.value = { ...task, due_date: task.due_date ? parseDateOnly(task.due_date) : null }
    taskDialog.value = true
}

const confirmDelete = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Voulez-vous vraiment supprimer cette tâche ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: () => {
            onDelete(formTask.value.id)
            taskDialog.value = false
        }
    })
}

const toPayload = (task: TaskForm) => ({
    ...task,
    due_date: task.due_date ? toDateInputValue(task.due_date) : null,
})

const handleSubmit = async () => {
    submitted.value = true
    if (!formTask.value.title.trim()) return

    if (isEditMode.value) {
        const { data } = await apiClient.put(`/tasks/${formTask.value.id}`, toPayload(formTask.value))
        upsertTask(data)
    } else {
        const { data } = await apiClient.post('/tasks', toPayload(formTask.value))
        upsertTask(data)
    }
    taskDialog.value = false
}

const onDelete = async (id: number | string | undefined) => {
    if (id === undefined) return
    await apiClient.delete(`/tasks/${id}`)
    tasks.value = tasks.value.filter(t => t.id !== id)
}

onMounted(fetchTasks)

useRealtimeResource<Task>('task', {
    onCreatedOrUpdated: upsertTask,
    onDeleted: (task) => {
        tasks.value = tasks.value.filter(t => t.id !== task.id)
    },
})
</script>
