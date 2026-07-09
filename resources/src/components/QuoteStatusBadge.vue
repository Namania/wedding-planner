<template>
    <button type="button" @click.stop="toggle"
        class="w-7 h-7 rounded-full bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 shadow-sm flex items-center justify-center z-10"
        :title="`Statut du devis : ${currentOption.label}`" :aria-label="`Statut du devis : ${currentOption.label}`">
        <i :class="[currentOption.icon, currentOption.colorClass]" class="text-sm"></i>
    </button>

    <Popover ref="popover" class="!w-48">
        <span class="text-xs font-semibold uppercase tracking-wider text-muted-color px-2 block mb-1">
            Statut du devis
        </span>

        <div v-for="option in OPTIONS" :key="option.value ?? 'none'" @click="select(option.value)"
            class="flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 cursor-pointer">
            <i :class="[option.icon, option.colorClass]" class="text-sm w-4 text-center"></i>
            <span class="flex-1 font-medium">{{ option.label }}</span>
            <i v-if="option.value === status" class="pi pi-check text-xs text-indigo-600 dark:text-indigo-400"></i>
        </div>
    </Popover>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import Popover from 'primevue/popover'

export type QuoteStatus = 'requested' | 'accepted' | 'refused' | null

const props = defineProps<{ status: QuoteStatus }>()
const emit = defineEmits<{ 'update:status': [value: QuoteStatus] }>()

const OPTIONS: { value: QuoteStatus; label: string; icon: string; colorClass: string }[] = [
    { value: null, label: 'Aucun', icon: 'pi pi-file', colorClass: 'text-muted-color' },
    { value: 'requested', label: 'Demandé', icon: 'pi pi-file-edit', colorClass: 'text-amber-600 dark:text-amber-400' },
    { value: 'accepted', label: 'Accepté', icon: 'pi pi-file-check', colorClass: 'text-green-600 dark:text-green-400' },
    { value: 'refused', label: 'Refusé', icon: 'pi pi-file', colorClass: 'text-red-600 dark:text-red-400' },
]

const currentOption = computed(() => OPTIONS.find(o => o.value === props.status) ?? OPTIONS[0]!)

const popover = ref()
const toggle = (event: Event) => popover.value.toggle(event)

const select = (value: QuoteStatus) => {
    popover.value?.hide()
    if (value !== props.status) emit('update:status', value)
}
</script>
