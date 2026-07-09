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

                <BudgetSegmentedMeter :budget-total="budgetTotal" :segments="budgetSegments"
                    :center-value="formatAmount(usedTotal)" center-label="utilisé" title="Budget utilisé"
                    :caption="usedCaption" :caption-class="usedCaptionClass" :refused-amount="refusedTotal" />
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
                    lieu, un traiteur, un fleuriste, une animation ou une tenue dans la simulation active.</p>
            </div>

            <div v-for="expense in expenses" :key="expense.id"
                class="bg-surface-0 dark:bg-surface-900 p-4 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm flex items-center justify-between gap-3"
                :class="{ 'opacity-60': expense.quote_status === 'refused' }">

                <div class="flex flex-col min-w-0">
                    <span class="font-bold text-color truncate">{{ expense.name }}</span>
                    <div class="flex flex-wrap items-center gap-1.5 mt-1">
                        <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full w-fit bg-surface-100 dark:bg-surface-800 text-muted-color">
                            {{ expense.category }}
                        </span>
                        <span v-if="expense.quote_status === 'refused'"
                            class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full w-fit bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400">
                            Refusé
                        </span>
                    </div>
                </div>

                <span class="font-bold text-color shrink-0" :class="{ 'line-through': expense.quote_status === 'refused' }">
                    {{ formatAmount(expense.amount) }}
                </span>
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
import BudgetSegmentedMeter, { type BudgetMeterSegment } from '@/components/BudgetSegmentedMeter.vue'
import RadialMeter from '@/components/RadialMeter.vue'
import { type QuoteStatus } from '@/components/QuoteStatusBadge.vue'
import { useSimulationsStore } from '@/stores/simulations'
import { useWeddingStore } from '@/stores/wedding'
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
    quote_status: QuoteStatus
}

interface Budget {
    id: number
    total: number
}

const simulationsStore = useSimulationsStore()
const weddingStore = useWeddingStore()

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
        items.push({ id: `venue-${sim.venue.id}`, name: sim.venue.name, category: 'Lieu', amount: sim.venue.price, quote_status: sim.venue.quote_status })
    }
    if (sim.caterer) {
        items.push({
            id: `caterer-${sim.caterer.id}`,
            name: sim.caterer.name,
            category: 'Traiteur',
            amount: sim.caterer.price_per_person * guestsTotal.value,
            quote_status: sim.caterer.quote_status,
        })
    }
    if (sim.florist) {
        items.push({ id: `florist-${sim.florist.id}`, name: sim.florist.name, category: 'Fleuriste', amount: sim.florist.price, quote_status: sim.florist.quote_status })
    }
    for (const animation of sim.animations) {
        items.push({ id: `animation-${animation.id}`, name: animation.name, category: 'Animation', amount: animation.price, quote_status: animation.quote_status })
    }
    for (const outfit of sim.outfits) {
        const spouseName = outfit.spouse === 'spouse_1' ? weddingStore.spouse1Name : weddingStore.spouse2Name
        items.push({ id: `outfit-${outfit.id}`, name: outfit.name, category: `Tenue ${spouseName}`, amount: outfit.price, quote_status: outfit.quote_status })
    }

    return items
})

// Un devis refusé ne compte plus dans le budget utilisé (on ne va pas retenir
// ce prestataire), mais reste visible dans la liste pour ne rien cacher.
const countedExpenses = computed(() => expenses.value.filter(e => e.quote_status !== 'refused'))
const usedTotal = computed(() => countedExpenses.value.reduce((sum, expense) => sum + expense.amount, 0))
const refusedTotal = computed(() => expenses.value
    .filter(e => e.quote_status === 'refused')
    .reduce((sum, expense) => sum + expense.amount, 0))

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

const usedCaptionClass = computed(() => {
    switch (usedStatus.value) {
        case 'critical': return 'text-red-600 dark:text-red-400'
        case 'warning': return 'text-amber-600 dark:text-amber-400'
        default: return 'text-green-600 dark:text-green-400'
    }
})

// Le cercle "budget utilisé" est fractionné par statut de devis : accepté,
// demandé, et sans statut (les refusés sont exclus, cf. countedExpenses).
const budgetSegments = computed<BudgetMeterSegment[]>(() => {
    const byStatus = (status: QuoteStatus) => countedExpenses.value
        .filter(e => e.quote_status === status)
        .reduce((sum, e) => sum + e.amount, 0)

    const definitions: { key: string; status: QuoteStatus; label: string; dotClass: string; strokeClass: string }[] = [
        { key: 'accepted', status: 'accepted', label: 'Accepté', dotClass: 'bg-green-500', strokeClass: 'stroke-green-500' },
        { key: 'requested', status: 'requested', label: 'Demandé', dotClass: 'bg-amber-500', strokeClass: 'stroke-amber-500' },
        { key: 'none', status: null, label: 'Sans statut', dotClass: 'bg-surface-400 dark:bg-surface-500', strokeClass: 'stroke-surface-400 dark:stroke-surface-500' },
    ]

    return definitions
        .map(d => ({ key: d.key, label: d.label, dotClass: d.dotClass, strokeClass: d.strokeClass, amount: byStatus(d.status) }))
        .filter(segment => segment.amount > 0)
})

const fetchBudget = async () => {
    isLoading.value = true
    try {
        // On force un rechargement (pas ensureLoaded) : les prix des prestataires
        // retenus peuvent avoir changé ailleurs depuis le dernier chargement du
        // store, et cette page doit toujours refléter les montants à jour.
        const [budgetRes, guestsRes] = await Promise.all([
            apiClient.get<Budget>('/budget'),
            apiClient.get<{ confirmed: boolean | null }[]>('/guests'),
            simulationsStore.fetchSimulations(),
        ])
        budgetTotal.value = budgetRes.data.total
        // Le traiteur est facturé au nombre d'invités qui viendront réellement :
        // on exclut ceux ayant décliné, mais on garde ceux en attente de réponse.
        guestsTotal.value = guestsRes.data.filter(g => g.confirmed !== false).length
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
