<template>
    <div class="flex flex-col items-center gap-3">
        <div class="relative shrink-0" :style="{ width: `${size}px`, height: `${size}px` }">
            <svg :viewBox="`0 0 ${size} ${size}`" class="-rotate-90">
                <circle :cx="size / 2" :cy="size / 2" :r="radius" fill="none" :stroke-width="strokeWidth"
                    :class="colors.track" />
                <circle :cx="size / 2" :cy="size / 2" :r="radius" fill="none" :stroke-width="strokeWidth"
                    stroke-linecap="round" :stroke-dasharray="circumference"
                    :stroke-dashoffset="dashOffset" :class="colors.fill" class="transition-all duration-500" />
            </svg>

            <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-2">
                <span class="text-xl font-extrabold tracking-tight text-color leading-tight">{{ centerValue }}</span>
                <span class="text-[11px] text-muted-color font-medium mt-0.5">{{ centerLabel }}</span>
            </div>
        </div>

        <div class="flex flex-col items-center gap-1">
            <span class="text-sm font-bold uppercase tracking-wider text-muted-color">{{ title }}</span>
            <span v-if="caption" class="text-xs font-semibold" :class="colors.caption">{{ caption }}</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
    percentage: number
    centerValue: string
    centerLabel: string
    title: string
    caption?: string
    status?: 'primary' | 'good' | 'warning' | 'critical'
    size?: number
}

const props = withDefaults(defineProps<Props>(), {
    status: 'primary',
    size: 160,
    caption: undefined,
})

// Le trait et son fond utilisent toujours la même teinte (fond clair / trait plein)
// pour que le statut se lise sur l'ensemble de l'anneau, pas seulement sur le trait.
const COLOR_MAP = {
    primary: { fill: 'stroke-primary', track: 'stroke-primary/15', caption: 'text-primary' },
    good: { fill: 'stroke-green-500', track: 'stroke-green-500/15', caption: 'text-green-600 dark:text-green-400' },
    warning: { fill: 'stroke-amber-500', track: 'stroke-amber-500/15', caption: 'text-amber-600 dark:text-amber-400' },
    critical: { fill: 'stroke-red-500', track: 'stroke-red-500/15', caption: 'text-red-600 dark:text-red-400' },
} as const

const colors = computed(() => COLOR_MAP[props.status])

const strokeWidth = 14
const radius = computed(() => props.size / 2 - strokeWidth)
const circumference = computed(() => 2 * Math.PI * radius.value)
const clampedPercentage = computed(() => Math.min(100, Math.max(0, props.percentage)))
const dashOffset = computed(() => circumference.value * (1 - clampedPercentage.value / 100))
</script>
