<template>
    <Button icon="pi pi-bars" variant="text" severity="secondary" @click="visible = true" class="!w-10 !h-10" />

    <Drawer
        v-model:visible="visible"
        header="Menu"
        position="left"
        class="!w-72"
    >
        <div class="flex flex-col h-full">

            <nav class="flex flex-col gap-1.5 mt-4 flex-1 min-h-0 overflow-y-auto">
                <router-link to="/" @click="visible = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800 font-medium transition-all"
                    exact-active-class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 !font-semibold">
                    <i class="pi pi-th-large"></i> <span>Tableau de bord</span>
                </router-link>

                <span class="text-xs font-bold uppercase tracking-wider text-muted-color px-3 mb-1 mt-3 block">
                    Invités
                </span>

                <router-link to="/guests" @click="visible = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800 font-medium transition-all"
                    exact-active-class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 !font-semibold">
                    <i class="pi pi-users"></i> <span>Liste d'invités</span>
                </router-link>

                <router-link to="/seating" @click="visible = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800 font-medium transition-all"
                    exact-active-class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 !font-semibold">
                    <i class="pi pi-objects-column"></i> <span>Plan de table</span>
                </router-link>

                <span class="text-xs font-bold uppercase tracking-wider text-muted-color px-3 mb-1 mt-3 block">
                    Prestataires
                </span>

                <router-link to="/venues" @click="visible = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800 font-medium transition-all"
                    exact-active-class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 !font-semibold">
                    <i class="pi pi-map-marker"></i> <span>Lieux</span>
                </router-link>

                <router-link to="/caterers" @click="visible = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800 font-medium transition-all"
                    exact-active-class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 !font-semibold">
                    <i class="pi pi-shop"></i> <span>Traiteurs</span>
                </router-link>

                <router-link to="/florists" @click="visible = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800 font-medium transition-all"
                    exact-active-class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 !font-semibold">
                    <i class="pi pi-sun"></i> <span>Fleuristes</span>
                </router-link>

                <span class="text-xs font-bold uppercase tracking-wider text-muted-color px-3 mb-1 mt-3 block">
                    Organisation
                </span>

                <router-link to="/budget" @click="visible = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800 font-medium transition-all"
                    exact-active-class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 !font-semibold">
                    <i class="pi pi-wallet"></i> <span>Budget</span>
                </router-link>

                <router-link to="/tasks" @click="visible = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800 font-medium transition-all"
                    exact-active-class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 !font-semibold">
                    <i class="pi pi-list-check"></i> <span>Checklist</span>
                </router-link>

                <router-link to="/timeline" @click="visible = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800 font-medium transition-all"
                    exact-active-class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 !font-semibold">
                    <i class="pi pi-calendar"></i> <span>Planning</span>
                </router-link>
            </nav>

            <div>
                <div class="px-3 my-3 border-t border-surface-200 dark:border-surface-800"></div>

                <SimulationSwitcher />

                <div
                    class="px-3 mt-3 border-t border-surface-200 dark:border-surface-800 pt-4 flex items-center justify-center gap-3">
                    <Avatar label="M" shape="circle" class="bg-indigo-600 text-white font-bold" />
                    <div class="flex flex-col">
                        <span class="text-sm font-semibold">Margaux & Mael</span>
                        <span class="text-xs text-muted-color">{{ weddingStore.formattedDate }}</span>
                    </div>
                </div>

                <LogoutButton />
            </div>

        </div>
    </Drawer>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Drawer from 'primevue/drawer'
import { useWeddingStore } from '@/stores/wedding'

const visible = ref<boolean>(false)
const weddingStore = useWeddingStore()
</script>
