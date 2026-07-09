import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import apiClient from '@/api/client'
import type { QuoteStatus } from '@/components/QuoteStatusBadge.vue'

export interface SimulationVenue {
    id: number
    name: string
    price: number
    quote_status: QuoteStatus
}

export interface SimulationCaterer {
    id: number
    name: string
    price_per_person: number
    quote_status: QuoteStatus
}

export interface SimulationFlorist {
    id: number
    name: string
    price: number
    quote_status: QuoteStatus
}

export interface SimulationAnimation {
    id: number
    name: string
    price: number
    quote_status: QuoteStatus
}

export interface SimulationOutfit {
    id: number
    name: string
    price: number
    spouse: 'spouse_1' | 'spouse_2'
    quote_status: QuoteStatus
}

export interface Simulation {
    id: number
    name: string
    is_active: boolean
    venue_id: number | null
    caterer_id: number | null
    florist_id: number | null
    // Contrairement au lieu/traiteur/fleuriste (un seul choix), plusieurs
    // animations/tenues peuvent être retenues en même temps pour une simulation.
    animation_ids: number[]
    outfit_ids: number[]
    venue: SimulationVenue | null
    caterer: SimulationCaterer | null
    florist: SimulationFlorist | null
    animations: SimulationAnimation[]
    outfits: SimulationOutfit[]
}

type SelectionCategory = 'venue_id' | 'caterer_id' | 'florist_id'

export const useSimulationsStore = defineStore('simulations', () => {
    const simulations = ref<Simulation[]>([])
    const isLoaded = ref<boolean>(false)

    const active = computed<Simulation | null>(() => simulations.value.find(s => s.is_active) ?? null)

    async function fetchSimulations() {
        const { data } = await apiClient.get<Simulation[]>('/simulations')
        simulations.value = data
        isLoaded.value = true
    }

    async function ensureLoaded() {
        if (!isLoaded.value) await fetchSimulations()
    }

    async function createSimulation(name: string) {
        await apiClient.post('/simulations', { name })
        await fetchSimulations()
    }

    async function renameSimulation(id: number, name: string) {
        await apiClient.put(`/simulations/${id}`, { name })
        await fetchSimulations()
    }

    async function deleteSimulation(id: number) {
        await apiClient.delete(`/simulations/${id}`)
        await fetchSimulations()
    }

    async function activateSimulation(id: number) {
        await apiClient.patch(`/simulations/${id}/activate`)
        await fetchSimulations()
    }

    // Sélectionne/désélectionne un lieu, traiteur ou fleuriste pour la simulation
    // active. Un second appel avec le même id désélectionne (bascule).
    async function toggleSelection(category: SelectionCategory, id: number) {
        if (!active.value) return
        const newValue = active.value[category] === id ? null : id
        await apiClient.put(`/simulations/${active.value.id}`, { [category]: newValue })
        await fetchSimulations()
    }

    // Ajoute/retire une animation de la simulation active : plusieurs animations
    // peuvent être retenues en même temps, contrairement aux autres catégories.
    async function toggleAnimation(animationId: number) {
        if (!active.value) return
        const current = active.value.animation_ids
        const animationIds = current.includes(animationId)
            ? current.filter(id => id !== animationId)
            : [...current, animationId]
        await apiClient.put(`/simulations/${active.value.id}`, { animation_ids: animationIds })
        await fetchSimulations()
    }

    // Ajoute/retire une tenue de la simulation active : plusieurs tenues peuvent
    // être retenues en même temps (une par marié, voire plusieurs par marié).
    async function toggleOutfit(outfitId: number) {
        if (!active.value) return
        const current = active.value.outfit_ids
        const outfitIds = current.includes(outfitId)
            ? current.filter(id => id !== outfitId)
            : [...current, outfitId]
        await apiClient.put(`/simulations/${active.value.id}`, { outfit_ids: outfitIds })
        await fetchSimulations()
    }

    return {
        simulations,
        active,
        isLoaded,
        fetchSimulations,
        ensureLoaded,
        createSimulation,
        renameSimulation,
        deleteSimulation,
        activateSimulation,
        toggleSelection,
        toggleAnimation,
        toggleOutfit,
    }
})
