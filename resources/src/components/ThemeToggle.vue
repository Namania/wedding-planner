<template>
    <Button :icon="isDark ? 'pi pi-sun' : 'pi pi-moon'" variant="text" severity="secondary" @click="toggleDarkMode"
        class="rounded-full !w-10 !h-10 text-muted-color" />
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

const isDark = ref<boolean>(false)

onMounted(() => {
    const savedTheme = localStorage.getItem('theme')
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches

    if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
        document.documentElement.classList.add('dark')
        isDark.value = true
    } else {
        document.documentElement.classList.remove('dark')
        isDark.value = false
    }
})

const toggleDarkMode = (): void => {
    const element = document.documentElement

    if (element.classList.contains('dark')) {
        element.classList.remove('dark')
        localStorage.setItem('theme', 'light')
        isDark.value = false
    } else {
        element.classList.add('dark')
        localStorage.setItem('theme', 'dark')
        isDark.value = true
    }
}
</script>
