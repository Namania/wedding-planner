<template>
    <div class="px-3 mb-2">
        <span class="text-xs font-bold uppercase tracking-wider text-muted-color px-3 mb-1 block">Simulation budgétaire</span>

        <button type="button" @click="toggle"
            class="flex items-center justify-between w-full gap-2 px-3 py-2.5 rounded-xl bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors">
            <span class="flex items-center gap-2 min-w-0">
                <i class="pi pi-sliders-h text-muted-color shrink-0"></i>
                <span class="font-bold truncate">{{ simulationsStore.active?.name ?? '—' }}</span>
            </span>
            <i class="pi pi-sort text-xs text-muted-color shrink-0"></i>
        </button>
    </div>

    <Popover ref="popover" class="!w-72">
        <span class="text-xs font-semibold uppercase tracking-wider text-muted-color px-2 block mb-1">
            Simulations
        </span>

        <div v-if="simulationsStore.simulations.length === 0" class="px-2 py-3 text-sm text-muted-color">
            Aucune simulation pour le moment.
        </div>

        <div v-for="sim in simulationsStore.simulations" :key="sim.id" @click="activate(sim.id)"
            class="flex items-center justify-between gap-2 px-2 py-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 cursor-pointer">
            <span class="flex items-center gap-2 min-w-0 flex-1">
                <i v-if="sim.is_active" class="pi pi-star-fill text-amber-400 text-sm shrink-0"></i>
                <i v-else class="pi pi-star text-muted-color text-sm shrink-0"></i>
                <span class="font-medium truncate">{{ sim.name }}</span>
            </span>

            <button type="button" @click.stop="openEdit(sim)"
                class="w-6 h-6 flex items-center justify-center text-muted-color hover:text-color shrink-0"
                aria-label="Modifier">
                <i class="pi pi-pencil text-xs"></i>
            </button>
        </div>

        <div class="border-t border-surface-200 dark:border-surface-700 mt-1 pt-1">
            <button type="button" @click="openNew"
                class="flex items-center gap-2 w-full px-2 py-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 text-left text-muted-color">
                <span class="w-6 h-6 rounded-md bg-surface-900 dark:bg-black flex items-center justify-center shrink-0">
                    <i class="pi pi-plus text-xs text-white"></i>
                </span>
                <span class="font-medium">Nouvelle simulation</span>
            </button>
        </div>
    </Popover>

    <Dialog v-model:visible="simulationDialog" :header="isEditMode ? 'Modifier la simulation' : 'Nouvelle simulation'"
        modal dismissableMask class="w-[90vw] max-w-md !rounded-2xl" :draggable="false">
        <div class="flex flex-col gap-1.5 pt-2">
            <label for="sim-name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Nom</label>
            <InputText id="sim-name" v-model.trim="formName" autofocus placeholder="Ex: Option romantique"
                class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !formName }" />
            <small class="text-red-500 font-medium text-xs" v-if="submitted && !formName">Le nom est obligatoire.</small>
        </div>

        <template #footer>
            <div class="flex items-center justify-end w-full mt-4 gap-2">
                <Button v-if="isEditMode" label="Supprimer"
                    icon="pi pi-trash" severity="danger" variant="outlined" class="!rounded-xl"
                    @click="confirmDelete($event)" />
                <Button label="Enregistrer" icon="pi pi-save" class="!rounded-xl px-4" @click="handleSubmit" />
            </div>
        </template>
    </Dialog>

    <ConfirmPopup class="mx-3" />
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Popover from 'primevue/popover'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import ConfirmPopup from 'primevue/confirmpopup'
import { useConfirm } from 'primevue/useconfirm'
import { useSimulationsStore, type Simulation } from '@/stores/simulations'

const simulationsStore = useSimulationsStore()
const confirm = useConfirm()

const popover = ref()
const toggle = (event: Event) => popover.value.toggle(event)

const simulationDialog = ref<boolean>(false)
const isEditMode = ref<boolean>(false)
const submitted = ref<boolean>(false)
const formName = ref<string>('')
const editingId = ref<number | undefined>(undefined)

const activate = async (id: number) => {
    await simulationsStore.activateSimulation(id)
}

const openNew = () => {
    popover.value?.hide()
    isEditMode.value = false
    submitted.value = false
    formName.value = ''
    editingId.value = undefined
    simulationDialog.value = true
}

const openEdit = (sim: Simulation) => {
    popover.value?.hide()
    isEditMode.value = true
    submitted.value = false
    formName.value = sim.name
    editingId.value = sim.id
    simulationDialog.value = true
}

const confirmDelete = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Voulez-vous vraiment supprimer cette simulation ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: async () => {
            if (editingId.value === undefined) return
            await simulationsStore.deleteSimulation(editingId.value)
            simulationDialog.value = false
        }
    })
}

const handleSubmit = async () => {
    submitted.value = true
    if (!formName.value.trim()) return

    if (isEditMode.value && editingId.value !== undefined) {
        await simulationsStore.renameSimulation(editingId.value, formName.value)
    } else {
        await simulationsStore.createSimulation(formName.value)
    }
    simulationDialog.value = false
}

onMounted(() => simulationsStore.ensureLoaded())
</script>
