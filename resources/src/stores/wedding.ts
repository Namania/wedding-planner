import { computed } from 'vue'
import { defineStore } from 'pinia'

export const useWeddingStore = defineStore('wedding', () => {
    const date = new Date(import.meta.env.VITE_API_WENDING_DATE)

    const formattedDate = computed(() =>
        date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })
    )

    return { date, formattedDate }
})
