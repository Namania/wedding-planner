<template>
    <div class="space-y-6 pb-12">

        <div class="flex items-center justify-between px-4">
            <h1 class="text-xl font-bold">Suivi du <span class="text-indigo-600 dark:text-indigo-400">Budget</span></h1>
            <Button icon="pi pi-pencil" variant="text" severity="secondary" class="!w-9 !h-9" rounded
                aria-label="Modifier le budget" @click="openEdit" />
        </div>

        <div v-if="isLoading" class="bg-surface-0 dark:bg-surface-900 p-6 rounded-2xl border border-surface shadow-sm">
            <div class="flex flex-col items-center gap-6">
                <Skeleton shape="circle" size="160px" />
                <Skeleton shape="circle" size="160px" />
            </div>
        </div>

        <div v-else class="bg-surface-0 dark:bg-surface-900 p-6 rounded-2xl border border-surface shadow-sm">
            <div class="flex flex-col items-center gap-8">
                <RadialMeter :percentage="100" :center-value="formatAmount(budgetTotal)" center-label="prévu"
                    title="Budget total" status="primary" />

                <RadialMeter :percentage="usedPercentage" :center-value="formatAmount(usedTotal)" center-label="utilisé"
                    title="Budget utilisé" :status="usedStatus" :caption="usedCaption" />
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h2 class="font-bold text-color">Dépenses de la simulation active</h2>
                <span class="text-sm font-semibold text-muted-color">{{ formatAmount(usedTotal) }}</span>
            </div>

            <div v-if="!isLoading && !simulationsStore.active"
                class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <i class="pi pi-sliders-h text-4xl text-muted-color mb-3 block"></i>
                <p class="text-sm text-muted-color font-medium">Aucune simulation active pour le moment.</p>
            </div>

            <div v-else-if="!isLoading && expenses.length === 0"
                class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <i class="pi pi-receipt text-4xl text-muted-color mb-3 block"></i>
                <p class="text-sm text-muted-color font-medium">Aucune dépense prévue pour le moment. Sélectionnez un
                    lieu, un traiteur ou un fleuriste dans la simulation active.</p>
            </div>

            <div v-for="expense in expenses" :key="expense.id"
                class="bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm flex items-center justify-between gap-3">

                <div class="flex flex-col min-w-0">
                    <span class="font-bold text-color truncate">{{ expense.name }}</span>
                    <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full w-fit mt-1 bg-surface-100 dark:bg-surface-800 text-muted-color">
                        {{ expense.category }}
                    </span>
                </div>

                <span class="font-bold text-color shrink-0">{{ formatAmount(expense.amount) }}</span>
            </div>
        </div>

        <Dialog v-model:visible="budgetDialog" header="Modifier le budget" modal dismissableMask
            class="w-[90vw] max-w-md !rounded-2xl" :draggable="false">
            <div class="flex flex-col gap-1.5 pt-2">
                <label for="total" class="text-xs font-bold uppercase tracking-wider text-muted-color">Budget
                    total</label>
                <InputNumber id="total" v-model="formTotal" mode="currency" currency="EUR" locale="fr-FR" :min="0"
                    placeholder="0 €" class="w-full" :inputClass="'w-full !rounded-xl'"
                    :class="{ 'p-invalid': submitted && formTotal === null }" />
                <small class="text-red-500 font-medium text-xs" v-if="submitted && formTotal === null">Le budget
                    est obligatoire.</small>
            </div>

            <template #footer>
                <div class="flex items-center justify-end w-full mt-4 gap-2">
                    <Button label="Enregistrer" icon="pi pi-save" class="!rounded-xl px-4" :loading="saving"
                        @click="handleSubmit" />
                </div>
            </template>
        </Dialog>

    </div>
</template>

<script setup lang="ts">
import apiClient from '@/api/client'
import RadialMeter from '@/components/RadialMeter.vue'
import { useSimulationsStore } from '@/stores/simulations'
import { formatAmount } from '@/utils/currency'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import Skeleton from 'primevue/skeleton'
import { computed, onMounted, ref } from 'vue'

interface Expense {
    id: string
    name: string
    category: string
    amount: number
}

interface Budget {
    id: number
    total: number
}

const simulationsStore = useSimulationsStore()

const isLoading = ref<boolean>(true)
const budgetTotal = ref<number>(0)
const guestsTotal = ref<number>(0)

const budgetDialog = ref<boolean>(false)
const submitted = ref<boolean>(false)
const saving = ref<boolean>(false)
const formTotal = ref<number | null>(null)

const expenses = computed<Expense[]>(() => {
    const sim = simulationsStore.active
    if (!sim) return []

    const items: Expense[] = []

    if (sim.venue) {
        items.push({ id: `venue-${sim.venue.id}`, name: sim.venue.name, category: 'Lieu', amount: sim.venue.price })
    }
    if (sim.caterer) {
        items.push({
            id: `caterer-${sim.caterer.id}`,
            name: sim.caterer.name,
            category: 'Traiteur',
            amount: sim.caterer.price_per_person * guestsTotal.value,
        })
    }
    if (sim.florist) {
        items.push({ id: `florist-${sim.florist.id}`, name: sim.florist.name, category: 'Fleuriste', amount: sim.florist.price })
    }

    return items
})

const usedTotal = computed(() => expenses.value.reduce((sum, expense) => sum + expense.amount, 0))

const usedPercentage = computed(() => budgetTotal.value > 0 ? (usedTotal.value / budgetTotal.value) * 100 : 0)

const usedStatus = computed(() => {
    if (usedPercentage.value > 100) return 'critical'
    if (usedPercentage.value >= 70) return 'warning'
    return 'good'
})

const usedCaption = computed(() => {
    const pct = Math.round(usedPercentage.value)
    if (usedStatus.value === 'critical') return `Budget dépassé (${pct} %)`
    if (usedStatus.value === 'warning') return `Attention, ${pct} % utilisé`
    return `En bonne voie (${pct} %)`
})

const fetchBudget = async () => {
    isLoading.value = true
    try {
        const [budgetRes, guestsRes] = await Promise.all([
            apiClient.get<Budget>('/budget'),
            apiClient.get<unknown[]>('/guests'),
            simulationsStore.ensureLoaded(),
        ])
        budgetTotal.value = budgetRes.data.total
        guestsTotal.value = guestsRes.data.length
    } finally {
        isLoading.value = false
    }
}

const openEdit = () => {
    submitted.value = false
    formTotal.value = budgetTotal.value
    budgetDialog.value = true
}

const handleSubmit = async () => {
    submitted.value = true
    if (formTotal.value === null) return

    saving.value = true
    try {
        const { data } = await apiClient.put<Budget>('/budget', { total: formTotal.value })
        budgetTotal.value = data.total
        budgetDialog.value = false
    } finally {
        saving.value = false
    }
}

onMounted(fetchBudget)
</script>
