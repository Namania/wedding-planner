<template>
    <div class="fixed inset-0 bg-black flex items-center justify-center overflow-hidden cursor-none"
        @mousemove="showControls" @touchstart="showControls">

        <div v-if="photos.length === 0" class="text-center space-y-3 text-white/70">
            <i class="pi pi-images text-4xl"></i>
            <p class="text-xl font-medium">En attente des premières photos…</p>
            <p class="text-sm">Les photos partagées par les invités apparaîtront ici en direct.</p>
        </div>

        <template v-else>
            <transition name="slideshow-fade" mode="in-out">
                <img v-if="current" :key="current.id" :src="current.full_url" :alt="current.caption ?? ''"
                    class="absolute inset-0 w-full h-full object-contain" />
            </transition>

            <div class="absolute bottom-0 inset-x-0 p-8 bg-gradient-to-t from-black/70 to-transparent text-white">
                <p v-if="current?.caption" class="text-2xl font-medium drop-shadow">{{ current.caption }}</p>
                <p class="text-lg text-white/70 drop-shadow">{{ current?.guest_name }}</p>
            </div>
        </template>

        <div class="absolute top-4 right-4 transition-opacity duration-500"
            :class="controlsVisible ? 'opacity-100 cursor-auto' : 'opacity-0 pointer-events-none'">
            <Button icon="pi pi-times" label="Quitter" severity="secondary" class="!rounded-xl"
                @click="router.push('/photos')" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/api/client'
import echo from '@/echo'
import { useGalleryRealtime, type GalleryPhoto } from '@/composables/useGalleryRealtime'

const router = useRouter()

const photos = ref<GalleryPhoto[]>([])
const current = ref<GalleryPhoto | null>(null)
const controlsVisible = ref(false)

let cursor = 0
let rotationTimer: ReturnType<typeof setInterval> | null = null
let controlsTimer: ReturnType<typeof setTimeout> | null = null
const freshQueue: GalleryPhoto[] = []

const ROTATION_MS = 8000

const advance = () => {
    if (photos.value.length === 0) return

    const fresh = freshQueue.shift()
    if (fresh) {
        current.value = fresh
        return
    }

    cursor = (cursor + 1) % photos.value.length
    current.value = photos.value[cursor] ?? null
}

const showControls = () => {
    controlsVisible.value = true
    if (controlsTimer) clearTimeout(controlsTimer)
    controlsTimer = setTimeout(() => (controlsVisible.value = false), 3000)
}

onMounted(async () => {
    const { data } = await apiClient.get('/gallery-admin/photos')
    photos.value = (data.data as GalleryPhoto[]).filter((p) => !p.hidden)

    if (photos.value.length > 0) {
        current.value = photos.value[0] ?? null
    }

    rotationTimer = setInterval(advance, ROTATION_MS)
})

onUnmounted(() => {
    if (rotationTimer) clearInterval(rotationTimer)
    if (controlsTimer) clearTimeout(controlsTimer)
})

useGalleryRealtime(echo, {
    onCreatedOrUpdated: (photo) => {
        if (photo.hidden) {
            photos.value = photos.value.filter((p) => p.id !== photo.id)
            return
        }
        if (!photos.value.some((p) => p.id === photo.id)) {
            photos.value = [photo, ...photos.value]
            freshQueue.push(photo)
        }
    },
    onDeleted: (photo) => {
        photos.value = photos.value.filter((p) => p.id !== photo.id)
        if (current.value?.id === photo.id) advance()
    },
})
</script>

<style scoped>
.slideshow-fade-enter-active,
.slideshow-fade-leave-active {
    transition: opacity 1.2s ease;
}

.slideshow-fade-enter-from,
.slideshow-fade-leave-to {
    opacity: 0;
}
</style>
