<template>
    <div class="flex flex-col items-center gap-4 w-full">
        <div class="relative shrink-0" :style="{ width: `${size}px`, height: `${size}px` }">
            <svg :viewBox="`0 0 ${size} ${size}`" class="-rotate-90">
                <circle :cx="size / 2" :cy="size / 2" :r="radius" fill="none" :stroke-width="strokeWidth"
                    class="stroke-surface-200 dark:stroke-surface-700" />

                <circle v-for="segment in drawnSegments" :key="segment.key" :cx="size / 2" :cy="size / 2" :r="radius"
                    fill="none" :stroke-width="strokeWidth" stroke-linecap="round"
                    :stroke-dasharray="`${segment.dashLength} ${circumference - segment.dashLength}`"
                    :stroke-dashoffset="circumference - segment.startOffset" :class="segment.strokeClass"
                    class="transition-all duration-500" />
            </svg>

            <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-2">
                <span class="text-xl font-extrabold tracking-tight text-color leading-tight">{{ centerValue }}</span>
                <span class="text-[11px] text-muted-color font-medium mt-0.5">{{ centerLabel }}</span>
            </div>
        </div>

        <div class="flex flex-col items-center gap-1">
            <span class="text-sm font-bold uppercase tracking-wider text-muted-color">{{ title }}</span>
            <span v-if="caption" class="text-xs font-semibold" :class="captionClass">{{ caption }}</span>
        </div>

        <div v-if="segments.length > 0" class="w-full space-y-1.5">
            <div v-for="segment in segments" :key="segment.key" class="flex items-center gap-2 text-xs">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="segment.dotClass"></span>
                <span class="text-muted-color font-medium flex-1 truncate">{{ segment.label }}</span>
                <span class="font-semibold text-color shrink-0">{{ formatAmount(segment.amount) }}</span>
            </div>
        </div>

        <p v-if="refusedAmount > 0" class="text-[11px] text-muted-color text-center">
            + {{ formatAmount(refusedAmount) }} en devis refusés (non comptabilisés)
        </p>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatAmount } from '@/utils/currency'

export interface BudgetMeterSegment {
    key: string
    label: string
    amount: number
    dotClass: string
    strokeClass: string
}

interface Props {
    budgetTotal: number
    segments: BudgetMeterSegment[]
    centerValue: string
    centerLabel: string
    title: string
    caption?: string
    captionClass?: string
    refusedAmount?: number
    size?: number
}

const props = withDefaults(defineProps<Props>(), {
    caption: undefined,
    captionClass: 'text-muted-color',
    refusedAmount: 0,
    size: 160,
})

const strokeWidth = 14
const radius = computed(() => props.size / 2 - strokeWidth)
const circumference = computed(() => 2 * Math.PI * radius.value)

// Petit espace visuel entre deux segments adjacents, pour qu'ils ne se
// confondent pas visuellement même quand les couleurs sont proches.
const SEGMENT_GAP = 3

// Chaque segment occupe, sur le cercle complet, une longueur proportionnelle
// à sa part du budget total (pas seulement du montant utilisé) : l'anneau
// représente donc bien "budget utilisé / budget total", fractionné par statut.
const drawnSegments = computed(() => {
    let cumulativeLength = 0
    return props.segments.map((segment) => {
        const rawLength = props.budgetTotal > 0
            ? circumference.value * (segment.amount / props.budgetTotal)
            : 0
        const startOffset = cumulativeLength
        cumulativeLength += rawLength

        return {
            key: segment.key,
            strokeClass: segment.strokeClass,
            startOffset,
            dashLength: Math.max(rawLength - SEGMENT_GAP, 0),
        }
    })
})
</script>
