<template>
    <div class="space-y-5 pb-12">
        <ConfirmPopup class="mx-4" />

        <div class="flex justify-between items-center px-1 py-2 gap-4">
            <h1 class="text-xl font-bold ms-2">Les <span class="text-indigo-600 dark:text-indigo-400">dépenses</span>
            </h1>
            <Button icon="pi pi-plus" class="!rounded-xl shrink-0 shadow-sm" @click="openNew"
                aria-label="Ajouter une dépense" />
        </div>

        <div v-if="isLoading" class="bg-surface-0 dark:bg-surface-900 p-6 rounded-2xl border border-surface shadow-sm space-y-4">
            <Skeleton height="10rem" />
            <Skeleton height="2.5rem" />
            <Skeleton height="2.5rem" />
        </div>

        <template v-else>
            <div class="bg-surface-0 dark:bg-surface-900 p-6 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm flex justify-center">
                <BudgetSegmentedMeter :budget-total="budgetTotal" :segments="payerSegments"
                    :center-value="formatAmount(paidTotal)" center-label="payé" title="Dépenses réalisées"
                    :caption="paidCaption" :caption-class="paidCaptionClass" />
            </div>

            <div v-if="expenses.length === 0"
                class="text-center py-12 bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <i class="pi pi-receipt text-4xl text-muted-color mb-3 block"></i>
                <p class="text-sm text-muted-color font-medium">Aucune dépense enregistrée pour le moment.</p>
            </div>

            <div v-else
                class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm overflow-hidden">
                <div
                    class="flex justify-between px-4 py-2.5 text-[11px] font-bold uppercase tracking-wider text-muted-color border-b border-surface-200 dark:border-surface-800">
                    <span>Note · payé par</span>
                    <span>Prix</span>
                </div>

                <ul class="divide-y divide-surface-200 dark:divide-surface-800">
                    <li v-for="expense in expenses" :key="expense.id" @click="openEdit(expense)"
                        class="flex items-center justify-between gap-4 px-4 py-3 cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800/50 active:scale-[0.99] transition-all duration-150">
                        <div class="flex flex-col min-w-0 gap-1">
                            <span class="font-semibold text-color break-words">{{ expense.note }}</span>
                            <span class="self-start text-[11px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap"
                                :class="payerPillClass(expense.paid_by)">
                                {{ expense.paid_by }}
                            </span>
                        </div>
                        <span class="font-bold text-color whitespace-nowrap tabular-nums">{{ formatAmount(expense.amount) }}</span>
                    </li>
                </ul>
            </div>
        </template>

        <Dialog v-model:visible="expenseDialog" :header="isEditMode ? 'Modifier la dépense' : 'Ajouter une dépense'"
            modal dismissableMask class="w-[90vw] max-w-md !rounded-2xl" :draggable="false">
            <div class="flex flex-col gap-4 pt-2">
                <div class="flex flex-col gap-1.5">
                    <label for="amount" class="text-xs font-bold uppercase tracking-wider text-muted-color">Prix</label>
                    <InputNumber inputId="amount" v-model="form.amount" mode="currency" currency="EUR" locale="fr-FR"
                        :min="0" fluid :inputClass="'!rounded-xl'" :class="{ 'p-invalid': submitted && !amountValid }" />
                    <small v-if="submitted && !amountValid" class="text-red-500 font-medium text-xs">Le prix doit
                        être supérieur à zéro.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="note" class="text-xs font-bold uppercase tracking-wider text-muted-color">Note</label>
                    <InputText id="note" v-model.trim="form.note" maxlength="255" placeholder="Ex: Acompte traiteur"
                        class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !form.note }" />
                    <small v-if="submitted && !form.note" class="text-red-500 font-medium text-xs">La note est
                        obligatoire.</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="paid_by" class="text-xs font-bold uppercase tracking-wider text-muted-color">Payé
                        par</label>
                    <InputText id="paid_by" v-model="form.paid_by" maxlength="80" autocomplete="off"
                        placeholder="Ex: un des mariés, un parent…" class="w-full !rounded-xl"
                        :class="{ 'p-invalid': submitted && !form.paid_by.trim() }" />
                    <!-- Les mariés et les payeurs déjà saisis sont proposés, mais
                         tout autre nom est accepté : un parent ou un témoin peut
                         aussi avoir payé. Rien n'est affiché sans correspondance. -->
                    <div v-if="payerSuggestions.length" class="flex flex-wrap gap-1.5 pt-0.5">
                        <button v-for="name in payerSuggestions" :key="name" type="button"
                            class="text-xs font-semibold px-3 py-1 rounded-full transition-colors"
                            :class="payerPillClass(name)" @click="form.paid_by = name">
                            {{ name }}
                        </button>
                    </div>
                    <small v-if="submitted && !form.paid_by.trim()" class="text-red-500 font-medium text-xs">Indiquez
                        qui a payé.</small>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end w-full mt-4 gap-2">
                    <Button v-if="isEditMode" label="Supprimer" icon="pi pi-trash" severity="danger" variant="outlined"
                        class="!rounded-xl" @click="confirmDelete($event)" />
                    <Button label="Enregistrer" icon="pi pi-save" class="!rounded-xl px-4" :loading="saving"
                        @click="handleSubmit" />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import ConfirmPopup from 'primevue/confirmpopup'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/api/client'
import BudgetSegmentedMeter, { type BudgetMeterSegment } from '@/components/BudgetSegmentedMeter.vue'
import { useRealtimeResource } from '@/composables/useRealtimeResource'
import { useWeddingStore } from '@/stores/wedding'
import { formatAmount } from '@/utils/currency'

interface Expense {
    id: number
    amount: number
    note: string
    paid_by: string
    created_at: string
}

interface ExpenseForm {
    id?: number
    amount: number | null
    note: string
    paid_by: string
}

const confirm = useConfirm()
const weddingStore = useWeddingStore()

const expenses = ref<Expense[]>([])
const budgetTotal = ref<number>(0)
const isLoading = ref<boolean>(true)

const paidTotal = computed(() => expenses.value.reduce((sum, e) => sum + e.amount, 0))
const paidPercentage = computed(() => budgetTotal.value > 0 ? (paidTotal.value / budgetTotal.value) * 100 : 0)

// Mêmes seuils que la jauge « Budget utilisé » de la page Budget, pour que
// les deux pages parlent le même langage.
const paidStatus = computed(() => {
    if (paidPercentage.value > 100) return 'critical'
    if (paidPercentage.value >= 70) return 'warning'
    return 'good'
})

const paidCaption = computed(() => {
    if (budgetTotal.value <= 0) return 'Aucun budget défini'
    const pct = Math.round(paidPercentage.value)
    if (paidStatus.value === 'critical') return `Budget dépassé (${pct} %)`
    return `${pct} % du budget payé`
})

const paidCaptionClass = computed(() => {
    if (budgetTotal.value <= 0) return 'text-muted-color'
    switch (paidStatus.value) {
        case 'critical': return 'text-red-600 dark:text-red-400'
        case 'warning': return 'text-amber-600 dark:text-amber-400'
        default: return 'text-green-600 dark:text-green-400'
    }
})

const normalize = (name: string) => name.trim().toLocaleLowerCase('fr-FR')

const spouseNames = computed(() =>
    [weddingStore.spouse1Name, weddingStore.spouse2Name].filter((name) => name.trim() !== ''))

// Un segment par marié, et un dernier pour tous les autres payeurs : au-delà,
// la jauge deviendrait illisible et la palette n'a pas assez de couleurs.
const payerSegments = computed<BudgetMeterSegment[]>(() => {
    const [first, second] = [weddingStore.spouse1Name, weddingStore.spouse2Name]
    const sumFor = (predicate: (payer: string) => boolean) => expenses.value
        .filter((e) => predicate(normalize(e.paid_by)))
        .reduce((sum, e) => sum + e.amount, 0)

    const isSpouse = (payer: string) => spouseNames.value.some((name) => normalize(name) === payer)

    const others: BudgetMeterSegment = { key: 'others', label: 'Autres', amount: sumFor((p) => !isSpouse(p)), dotClass: 'bg-amber-500', strokeClass: 'stroke-amber-500' }

    // « Autres » n'apparaît que si quelqu'un d'autre que les mariés a payé.
    return [
        { key: 'spouse-1', label: first || 'Marié·e 1', amount: first ? sumFor((p) => p === normalize(first)) : 0, dotClass: 'bg-indigo-500', strokeClass: 'stroke-indigo-500' },
        { key: 'spouse-2', label: second || 'Marié·e 2', amount: second ? sumFor((p) => p === normalize(second)) : 0, dotClass: 'bg-pink-500', strokeClass: 'stroke-pink-500' },
        ...(others.amount > 0 ? [others] : []),
    ]
})

// Les mariés d'abord, puis les autres payeurs déjà saisis, filtrés par ce qui
// est tapé : on évite d'écrire « Tante Hélène » de trois façons différentes.
// Le nom déjà saisi en entier n'est pas reproposé.
const payerSuggestions = computed(() => {
    const query = normalize(form.value.paid_by)
    const known = [...spouseNames.value, ...expenses.value.map((e) => e.paid_by.trim())]
    const unique = known.filter((name, i) => known.findIndex((n) => normalize(n) === normalize(name)) === i)
    return unique.filter((name) => normalize(name).includes(query) && normalize(name) !== query)
})

// Même code couleur que la jauge : indigo pour le premier marié, rose pour
// le second, ambre pour tous les autres payeurs.
const payerPillClass = (payer: string): string => {
    const p = normalize(payer)
    if (weddingStore.spouse1Name && p === normalize(weddingStore.spouse1Name)) {
        return 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-900/50'
    }
    if (weddingStore.spouse2Name && p === normalize(weddingStore.spouse2Name)) {
        return 'bg-pink-50 dark:bg-pink-950/40 text-pink-600 dark:text-pink-400 border border-pink-200 dark:border-pink-900/50'
    }
    return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50'
}

// Met à jour la liste en local plutôt que de tout recharger ; une dépense
// créée arrive en tête, comme le tri de l'API (plus récente d'abord).
const upsertExpense = (expense: Expense) => {
    const index = expenses.value.findIndex((e) => e.id === expense.id)
    if (index !== -1) {
        expenses.value[index] = expense
        return
    }
    expenses.value.unshift(expense)
}

const fetchAll = async () => {
    isLoading.value = true
    try {
        const [expensesRes, budgetRes] = await Promise.all([
            apiClient.get<Expense[]>('/expenses'),
            apiClient.get<{ total: number }>('/budget'),
        ])
        expenses.value = expensesRes.data
        budgetTotal.value = Number(budgetRes.data.total)
    } finally {
        isLoading.value = false
    }
}

const expenseDialog = ref<boolean>(false)
const isEditMode = ref<boolean>(false)
const submitted = ref<boolean>(false)
const saving = ref<boolean>(false)

const emptyForm = (): ExpenseForm => ({ amount: null, note: '', paid_by: '' })
const form = ref<ExpenseForm>(emptyForm())

const amountValid = computed(() => form.value.amount !== null && form.value.amount > 0)

const openNew = () => {
    isEditMode.value = false
    submitted.value = false
    form.value = emptyForm()
    expenseDialog.value = true
}

const openEdit = (expense: Expense) => {
    isEditMode.value = true
    submitted.value = false
    form.value = { id: expense.id, amount: expense.amount, note: expense.note, paid_by: expense.paid_by }
    expenseDialog.value = true
}

const handleSubmit = async () => {
    submitted.value = true
    if (!amountValid.value || !form.value.note || !form.value.paid_by.trim()) return

    const payload = { amount: form.value.amount, note: form.value.note, paid_by: form.value.paid_by.trim() }

    saving.value = true
    try {
        const { data } = isEditMode.value
            ? await apiClient.put<Expense>(`/expenses/${form.value.id}`, payload)
            : await apiClient.post<Expense>('/expenses', payload)
        upsertExpense(data)
        expenseDialog.value = false
    } finally {
        saving.value = false
    }
}

const confirmDelete = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Voulez-vous vraiment supprimer cette dépense ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: async () => {
            const id = form.value.id
            if (id === undefined) return
            await apiClient.delete(`/expenses/${id}`)
            expenses.value = expenses.value.filter((e) => e.id !== id)
            expenseDialog.value = false
        },
    })
}

onMounted(fetchAll)

useRealtimeResource<Expense>('expense', {
    onCreatedOrUpdated: upsertExpense,
    onDeleted: (expense) => {
        expenses.value = expenses.value.filter((e) => e.id !== expense.id)
    },
})
</script>
