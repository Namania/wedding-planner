<template>
    <div
        class="min-h-screen bg-surface-50 dark:bg-surface-950 pb-20 transition-colors duration-200">

        <header
            class="h-14 border-b border-surface-200 dark:border-surface-800 flex items-center justify-between px-4 bg-surface-50 dark:bg-surface-900 sticky top-0 z-40">
            <div class="flex items-center gap-1">
                <MenuToogle />
                <h1 class="text-xl font-bold">Wedding Planner</h1>
            </div>
            <ThemeToggle />
        </header>

        <main class="p-4 max-w-md mx-auto">
            <RouterView />
        </main>

    </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useWeddingStore } from '@/stores/wedding'

const weddingStore = useWeddingStore()

onMounted(() => {
    weddingStore.fetchWedding()
})
</script>
