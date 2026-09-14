<template>
    <div class="space-y-6">

        <div class="px-4">
            <h1 class="text-xl font-bold">Résumé de l'organisation du <span class="text-indigo-600 dark:text-indigo-400">Mariage</span>.</h1>
        </div>

        <div v-if="isLoading" class="grid grid-cols-1 gap-4">
            <div v-for="n in 6" :key="n"
                class="bg-surface-0 dark:bg-surface-900 p-5 rounded-2xl border border-surface shadow-sm flex items-center gap-4">
                <Skeleton shape="circle" size="3rem" class="shrink-0" />
                <div class="flex flex-col gap-2">
                    <Skeleton width="8rem" height="0.75rem" />
                    <Skeleton width="5rem" height="1.5rem" />
                </div>
            </div>
        </div>

        <div v-else class="grid grid-cols-1 gap-4">

            <div
                class="bg-surface-0 dark:bg-surface-900 p-5 rounded-2xl border border-surface shadow-sm flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shrink-0">
                    <i class="pi pi-calendar"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-muted-color font-medium uppercase tracking-wider">Jours restants</span>
                    <span class="text-2xl font-extrabold tracking-tight mt-0.5">{{ daysRemaining }}</span>
                </div>
            </div>

            <div
                class="bg-surface-0 dark:bg-surface-900 p-5 rounded-2xl border border-surface shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0"
                    :class="metrics.tasks.overdue > 0 ? 'bg-red-500/10 text-red-600 dark:text-red-400' : 'bg-green-500/10 text-green-600 dark:text-green-400'">
                    <i class="pi pi-exclamation-triangle"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-muted-color font-medium uppercase tracking-wider">Tâches en retard</span>
                    <span class="text-2xl font-extrabold tracking-tight mt-0.5">{{ metrics.tasks.overdue }}</span>
                </div>
            </div>

            <div
                class="bg-surface-0 dark:bg-surface-900 p-5 rounded-2xl border border-surface shadow-sm flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xl shrink-0">
                    <i class="pi pi-list-check"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-muted-color font-medium uppercase tracking-wider">Checklist</span>
                    <span class="text-2xl font-extrabold tracking-tight mt-0.5">
                        {{ metrics.tasks.done }} / {{ metrics.tasks.total }}
                    </span>
                </div>
            </div>

            <div
                class="bg-surface-0 dark:bg-surface-900 p-5 rounded-2xl border border-surface shadow-sm flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-xl shrink-0">
                    <i class="pi pi-users"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-muted-color font-medium uppercase tracking-wider">Invités confirmés</span>
                    <span class="text-2xl font-extrabold tracking-tight mt-0.5">
                        {{ metrics.guests.confirmed }} / {{ metrics.guests.total }}
                    </span>
                </div>
            </div>

            <div
                class="bg-surface-0 dark:bg-surface-900 p-5 rounded-2xl border border-surface shadow-sm flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0"
                    :class="metrics.budget.max - metrics.budget.current > 0 ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'"
                >
                    <i class="pi pi-money-bill"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-muted-color font-medium uppercase tracking-wider">Budget engagé</span>
                    <span class="text-2xl font-extrabold tracking-tight mt-0.5">
                        {{ formatAmount(metrics.budget.current) }} / {{ formatAmount(metrics.budget.max) }}
                    </span>
                </div>
            </div>

            <div
                class="bg-surface-0 dark:bg-surface-900 p-5 rounded-2xl border border-surface shadow-sm flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl shrink-0">
                    <i class="pi pi-flag"></i>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs text-muted-color font-medium uppercase tracking-wider">Prochain événement</span>
                    <span v-if="metrics.next_event" class="text-lg font-extrabold tracking-tight mt-0.5 truncate">
                        {{ metrics.next_event.title }} · {{ formatEventDate(metrics.next_event.starts_at) }}
                    </span>
                    <span v-else class="text-sm font-semibold text-muted-color mt-1">Aucun événement à venir</span>
                </div>
            </div>

        </div>

    </div>
</template>

<script setup lang="ts">
import apiClient from '@/api/client';
import { useWeddingStore } from '@/stores/wedding';
import { formatAmount } from '@/utils/currency';
import { computed, onMounted, ref } from 'vue';
import Skeleton from 'primevue/skeleton';

interface Guest {
    confirmed: number
    total: number
}

interface Budget {
    current: number
    max: number
}

interface Tasks {
    done: number
    total: number
    overdue: number
}

interface NextEvent {
    title: string
    starts_at: string
}

interface Metric {
    guests: Guest
    budget: Budget
    tasks: Tasks
    next_event: NextEvent | null
}

const weddingStore = useWeddingStore()

const metrics = ref<Metric>({
    guests: {
        confirmed: 0,
        total: 0
    },
    budget: {
        current: 0,
        max: 0
    },
    tasks: {
        done: 0,
        total: 0,
        overdue: 0
    },
    next_event: null
});
const isLoading = ref<boolean>(true)

const daysRemaining = computed(() => {
    const diff = weddingStore.date.getTime() - Date.now()
    return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)))
})

const formatEventDate = (isoDate: string): string =>
    new Date(isoDate).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })

const fetchMetrics = async () => {
    isLoading.value = true
    try {
        const { data } = await apiClient.get<Metric>('/metrics')
        metrics.value = data
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchMetrics)
</script>
