<template>
    <div class="space-y-6 pb-12">

        <div class="px-4">
            <h1 class="text-xl font-bold">Réglages du <span class="text-indigo-600 dark:text-indigo-400">mariage</span></h1>
        </div>

        <div v-if="isLoading" class="bg-surface-0 dark:bg-surface-900 p-6 rounded-2xl border border-surface shadow-sm space-y-4">
            <Skeleton height="2.5rem" />
            <Skeleton height="2.5rem" />
            <Skeleton height="2.5rem" />
        </div>

        <div v-else class="bg-surface-0 dark:bg-surface-900 p-6 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm">
            <div class="flex flex-col gap-4">

                <div class="flex flex-col gap-1.5">
                    <label for="spouse_1_name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Premier·ère marié·e</label>
                    <InputText id="spouse_1_name" v-model.trim="form.spouse_1_name" placeholder="Ex: Margaux"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !form.spouse_1_name }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !form.spouse_1_name">Ce
                        champ est obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="spouse_2_name" class="text-xs font-bold uppercase tracking-wider text-muted-color">Second·e marié·e</label>
                    <InputText id="spouse_2_name" v-model.trim="form.spouse_2_name" placeholder="Ex: Mael"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !form.spouse_2_name }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !form.spouse_2_name">Ce
                        champ est obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="date" class="text-xs font-bold uppercase tracking-wider text-muted-color">Date du mariage</label>
                    <DatePicker id="date" v-model="form.date" dateFormat="dd/mm/yy" showIcon iconDisplay="input"
                        placeholder="Sélectionnez une date" class="w-full" :inputClass="'w-full !rounded-xl'"
                        panelClass="mx-4" :class="{ 'p-invalid': submitted && !form.date }" />
                    <small class="text-red-500 font-medium text-xs" v-if="submitted && !form.date">Ce champ est
                        obligatoire.</small>
                </div>

                <div class="flex items-center justify-end gap-2 mt-2">
                    <span v-if="saved" class="text-xs font-semibold text-green-600 dark:text-green-400 mr-auto">Enregistré !</span>
                    <Button label="Enregistrer" icon="pi pi-save" class="!rounded-xl px-4" :loading="saving" @click="handleSubmit" />
                </div>
            </div>
        </div>

    </div>
</template>

<script setup lang="ts">
import { useWeddingStore } from '@/stores/wedding'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Skeleton from 'primevue/skeleton'
import { onMounted, ref } from 'vue'

interface WeddingForm {
    spouse_1_name: string
    spouse_2_name: string
    date: Date | null
}

const weddingStore = useWeddingStore()

const isLoading = ref<boolean>(true)
const submitted = ref<boolean>(false)
const saving = ref<boolean>(false)
const saved = ref<boolean>(false)

const form = ref<WeddingForm>({
    spouse_1_name: '',
    spouse_2_name: '',
    date: null,
})

const fetchWedding = async () => {
    isLoading.value = true
    try {
        await weddingStore.fetchWedding()
        form.value = {
            spouse_1_name: weddingStore.spouse1Name,
            spouse_2_name: weddingStore.spouse2Name,
            date: new Date(weddingStore.date),
        }
    } finally {
        isLoading.value = false
    }
}

const handleSubmit = async () => {
    submitted.value = true
    saved.value = false
    if (!form.value.spouse_1_name || !form.value.spouse_2_name || !form.value.date) return

    saving.value = true
    try {
        await weddingStore.updateWedding({
            spouse_1_name: form.value.spouse_1_name,
            spouse_2_name: form.value.spouse_2_name,
            date: form.value.date.toISOString().slice(0, 10),
        })
        saved.value = true
    } finally {
        saving.value = false
    }
}

onMounted(fetchWedding)
</script>
