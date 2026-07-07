<template>
    <Button icon="pi pi-bars" variant="text" severity="secondary" @click="visible = true" class="!w-10 !h-10" />

    <Drawer v-model:visible="visible" header="Menu" position="left" class="!w-72">
        <div class="flex flex-col justify-between h-full pb-4">

            <nav class="flex flex-col gap-1.5 mt-4">
                <span class="text-xs font-bold uppercase tracking-wider text-muted-color px-3 mb-2 block">
                    Mon Espace
                </span>

                <router-link to="/" @click="visible = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800 font-medium transition-all"
                    exact-active-class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 !font-semibold">
                    <i class="pi pi-th-large"></i> <span>Tableau de bord</span>
                </router-link>

                <router-link to="/guests" @click="visible = false"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-color hover:bg-surface-100 dark:hover:bg-surface-800 font-medium transition-all"
                    exact-active-class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 !font-semibold">
                    <i class="pi pi-users"></i> <span>Liste d'invités</span>
                </router-link>
            </nav>

            <div
                class="px-3 border-t border-surface-200 dark:border-surface-800 pt-4 flex items-center justify-center gap-3">
                <Avatar label="M" shape="circle" class="bg-indigo-600 text-white font-bold" />
                <div class="flex flex-col">
                    <span class="text-sm font-semibold">Margaux & Mael</span>
                    <span class="text-xs text-muted-color">01 Janvier 2000</span>
                </div>
            </div>

        </div>
    </Drawer>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Drawer from 'primevue/drawer'

const visible = ref<boolean>(false)
</script>
