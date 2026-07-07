<template>
    <div class="space-y-6">

        <div class="px-4">
            <h1 class="text-xl font-bold">Résumé de l'organisation du <span class="text-indigo-600 dark:text-indigo-400">Mariage</span>.</h1>
        </div>

        <div class="grid grid-cols-1 gap-4">

            <div
                class="bg-surface-0 dark:bg-surface-900 p-5 rounded-2xl border border-surface shadow-sm flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-xl shrink-0">
                    <i class="pi pi-users"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-muted-color font-medium uppercase tracking-wider">Invités confirmés</span>
                    <span class="text-2xl font-extrabold tracking-tight mt-0.5">148 / 200</span>
                </div>
            </div>

            <div
                class="bg-surface-0 dark:bg-surface-900 p-5 rounded-2xl border border-surface shadow-sm flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shrink-0">
                    <i class="pi pi-money-bill"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-muted-color font-medium uppercase tracking-wider">Budget engagé</span>
                    <span class="text-2xl font-extrabold tracking-tight mt-0.5">12 450 €</span>
                </div>
            </div>

            <div
                class="bg-surface-0 dark:bg-surface-900 p-5 rounded-2xl border border-surface shadow-sm flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shrink-0">
                    <i class="pi pi-calendar"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-muted-color font-medium uppercase tracking-wider">Jours restants</span>
                    <span class="text-2xl font-extrabold tracking-tight mt-0.5">365 jours</span>
                </div>
            </div>

        </div>

    </div>
</template>
