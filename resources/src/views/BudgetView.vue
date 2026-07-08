<template>
    <div class="space-y-6 pb-12">

        <div class="px-4">
            <h1 class="text-xl font-bold">Suivi du <span class="text-indigo-600 dark:text-indigo-400">Budget</span></h1>
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
                <h2 class="font-bold text-color">Dépenses prévues</h2>
                <span class="text-sm font-semibold text-muted-color">{{ formatAmount(usedTotal) }}</span>
            </div>

            <div v-if="!expenses || expenses.length === 0"
                class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <i class="pi pi-receipt text-4xl text-muted-color mb-3 block"></i>
                <p class="text-sm text-muted-color font-medium">Aucune dépense prévue pour le moment.</p>
            </div>

            <!-- TODO: liste statique en attendant un vrai backend Expense (cf. futures sélections sur le 2ème chart) -->
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

    </div>
</template>

<script setup lang="ts">
import apiClient from '@/api/client'
import RadialMeter from '@/components/RadialMeter.vue'
import { formatAmount } from '@/utils/currency'
import Skeleton from 'primevue/skeleton'
import { computed, onMounted, ref } from 'vue'

interface Expense {
    id: number
    name: string
    category: string
    amount: number
}

interface Budget {
    id: number
    total: number
}

const isLoading = ref<boolean>(true)
const budgetTotal = ref<number>(0)

const expenses = ref<Expense[]>([
    { id: 1, name: 'Salle de réception', category: 'Lieu', amount: 6000 },
    { id: 2, name: 'Traiteur', category: 'Restauration', amount: 4500 },
    { id: 3, name: 'Photographe', category: 'Prestataires', amount: 1800 },
    { id: 4, name: 'Robe & costume', category: 'Tenues', amount: 2200 },
    { id: 5, name: 'Fleuriste', category: 'Décoration', amount: 900 },
])

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
        const { data } = await apiClient.get<Budget>('/budget')
        budgetTotal.value = data.total
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchBudget)
</script>
