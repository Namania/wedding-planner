<template>
    <div class="space-y-5 pb-12">
        <ConfirmPopup />

        <div class="flex justify-between items-center px-1 py-2 gap-4">
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold ms-2">Nos invités</h1>
                <span
                    class="bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 text-xs font-bold px-2.5 py-0.5 rounded-full border border-surface-200 dark:border-surface-700">
                    {{ totalGuests }}
                </span>
            </div>
            <Button icon="pi pi-plus" class="!rounded-xl shrink-0 shadow-sm" @click="openNew"
                aria-label="Ajouter un invité" />
        </div>

        <div class="space-y-3">
            <div v-if="!guests || guests.length === 0"
                class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <i class="pi pi-users text-4xl text-muted-color mb-3 block"></i>
                <p class="text-sm text-muted-color font-medium">Aucun invité pour le moment. Commencez par en ajouter un
                    !</p>
            </div>

            <div v-for="guest in guests" :key="guest.id" @click="openEdit(guest)"
                class="bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm flex items-center justify-between gap-3 cursor-pointer active:scale-[0.99] transition-all duration-150">

                <div class="flex items-center gap-4 min-w-0">
                    <Avatar :label="getInitials(guest.name)" shape="circle" size="large"
                        class="bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold shrink-0" />

                    <div class="flex flex-col min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-color truncate">{{ guest.name }}</span>
                            <i :class="getStatusIcon(guest.confirmed)" class="text-xs"></i>
                        </div>

                        <span v-if="guest.role" :class="getRoleClass(guest.role)"
                            class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full w-fit mt-1">
                            {{ getRoleLabel(guest.role) }}
                        </span>
                    </div>
                </div>

                <i class="pi pi-chevron-right text-xs text-muted-color/60 shrink-0"></i>
            </div>
        </div>

        <Dialog v-model:visible="guestDialog" :header="isEditMode ? 'Modifier l\'invité' : 'Ajouter un invité'"
            :modal="true" class="w-[90vw] max-w-md !rounded-2xl" :draggable="false">
            <div class="flex flex-col gap-4 pt-2">

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Nom
                        complet</label>
                    <InputText id="name" v-model.trim="formGuest.name" autofocus placeholder="Ex: Marie Courtois"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !formGuest.name }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !formGuest.name">Le nom est
                        obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="role" class="text-xs font-bold uppercase tracking-wider text-muted-color">Rôle de
                        l'invité</label>
                    <Select id="role" v-model="formGuest.role" :options="roleOptions" optionLabel="label"
                        optionValue="value" placeholder="Sélectionnez un rôle" class="w-full !rounded-xl" showClear />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-wider text-muted-color">Présence</label>
                    <SelectButton v-model="formGuest.confirmed" :options="presenceOptions" optionValue="value"
                        :allowEmpty="false" class="w-full text-sm"
                        :pt="{ pcButton: { class: 'flex-1 justify-center' } }">
                        <template #option="{ option }">
                            <i :class="option.icon" />
                        </template>
                    </SelectButton>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-between w-full mt-4 gap-2">
                    <Button v-if="isEditMode" label="Supprimer" icon="pi pi-trash" severity="danger" variant="outlined"
                        class="!rounded-xl" @click="confirmDelete($event)" />
                    <Button label="Enregistrer" icon="pi pi-save" class="!rounded-xl px-4" @click="handleSubmit" />
                </div>
            </template>
        </Dialog>

    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import ConfirmPopup from 'primevue/confirmpopup'
import { useConfirm } from 'primevue/useconfirm'

interface Guest {
    id?: number | string
    name: string
    role: string | null
    confirmed: boolean | null
}

const confirm = useConfirm()

const getStatusIcon = (confirmed: boolean | null) => {
    if (confirmed === true) return 'pi pi-check-circle text-green-500'
    if (confirmed === false) return 'pi pi-times-circle text-red-500'
    return 'pi pi-clock text-amber-500'
}

const presenceOptions = ref([
    { icon: 'pi pi-clock text-amber-500', value: null },
    { icon: 'pi pi-check-circle text-green-500', value: true },
    { icon: 'pi pi-times-circle text-red-500', value: false }
])

const roleOptions = [
    { label: 'Témoin', value: 'witness' },
    { label: "Garçon d'honneur", value: 'groomsman' },
    { label: "Demoiselle d'honneur", value: 'bridesmaid' }
]

const guests = ref<Guest[]>([
    { id: 1, name: 'Pierre Laurent', role: 'witness', confirmed: true },
    { id: 2, name: 'Sophie Martin', role: 'bridesmaid', confirmed: null },
    { id: 3, name: 'Lucas Bernard', role: 'groomsman', confirmed: false },
    { id: 4, name: 'Julien Petit', role: null, confirmed: true }
])

const totalGuests = computed(() => guests.value.length)

const guestDialog = ref<boolean>(false)
const isEditMode = ref<boolean>(false)
const submitted = ref<boolean>(false)

const formGuest = ref<Guest>({ name: '', role: null, confirmed: null })

const openNew = () => {
    isEditMode.value = false
    submitted.value = false
    formGuest.value = { name: '', role: null, confirmed: null }
    guestDialog.value = true
}

const openEdit = (guest: Guest) => {
    isEditMode.value = true
    submitted.value = false
    formGuest.value = { ...guest }
    guestDialog.value = true
}

const confirmDelete = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Voulez-vous vraiment supprimer cet invité ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: () => {
            onDelete(formGuest.value.id)
            guestDialog.value = false
        }
    })
}

const handleSubmit = () => {
    submitted.value = true
    if (!formGuest.value.name.trim()) return

    if (isEditMode.value) {
        console.log('API PUT:', formGuest.value)
    } else {
        console.log('API POST:', formGuest.value)
    }
    guestDialog.value = false
}

const onDelete = (id: number | string | undefined) => {
    if (id === undefined) return
    console.log('API DELETE pour l\'id:', id)
}

const getInitials = (name: string): string => {
    if (!name || !name.trim()) return ''
    const parts = name.trim().split(/\s+/).filter(Boolean)
    return parts.length > 1 ? ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase() : (parts[0]?.[0] || '').toUpperCase()
}

const getRoleLabel = (role: string): string => {
    const matched = roleOptions.find(option => option.value === role)
    return matched ? matched.label : role
}

const getRoleClass = (role: string) => {
    switch (role) {
        case 'witness':
            return 'bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-900/50'
        case 'groomsman':
            return 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50'
        case 'bridesmaid':
            return 'bg-pink-50 dark:bg-pink-950/40 text-pink-600 dark:text-pink-400 border border-pink-200 dark:border-pink-900/50'
        default:
            return 'bg-surface-100 dark:bg-surface-800 text-muted-color'
    }
}
</script>
