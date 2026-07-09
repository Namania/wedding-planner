import apiClient from '@/api/client'
import { parseDateOnly } from '@/utils/date'
import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

interface WeddingPayload {
    id: number
    spouse_1_name: string
    spouse_2_name: string
    date: string
}

export const useWeddingStore = defineStore('wedding', () => {
    const spouse1Name = ref<string>('')
    const spouse2Name = ref<string>('')
    const date = ref<Date>(new Date())

    const formattedDate = computed(() =>
        date.value.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })
    )

    const fetchWedding = async () => {
        const { data } = await apiClient.get<WeddingPayload>('/wedding')
        spouse1Name.value = data.spouse_1_name
        spouse2Name.value = data.spouse_2_name
        date.value = parseDateOnly(data.date)
    }

    const updateWedding = async (payload: { spouse_1_name: string; spouse_2_name: string; date: string }) => {
        const { data } = await apiClient.put<WeddingPayload>('/wedding', payload)
        spouse1Name.value = data.spouse_1_name
        spouse2Name.value = data.spouse_2_name
        date.value = parseDateOnly(data.date)
    }

    return { spouse1Name, spouse2Name, date, formattedDate, fetchWedding, updateWedding }
})
