<template>
    <div class="min-h-screen">
        <ConfirmPopup class="mx-4" />

        <header
            class="sticky top-0 z-20 bg-surface-0/90 dark:bg-surface-900/90 backdrop-blur border-b border-surface-200 dark:border-surface-800">
            <div class="max-w-md mx-auto px-4 h-14 flex items-center justify-between">
                <div class="flex items-center gap-2 min-w-0">
                    <i class="pi pi-camera text-indigo-600 dark:text-indigo-400"></i>
                    <span class="font-bold tracking-tight truncate">Photos du mariage</span>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <span class="text-sm text-muted-color truncate max-w-24">{{ me?.name }}</span>
                    <Button icon="pi pi-sign-out" variant="text" severity="danger" class="!w-9 !h-9"
                        @click="handleLogout" />
                </div>
            </div>
        </header>

        <main class="max-w-md mx-auto p-4 space-y-5 pb-28">
            <div v-if="isLoading" class="grid grid-cols-3 gap-1.5">
                <Skeleton v-for="i in 9" :key="i" class="!rounded-xl aspect-square !h-auto" />
            </div>

            <div v-else-if="photos.length === 0"
                class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm p-8 text-center space-y-2">
                <i class="pi pi-images text-3xl text-muted-color"></i>
                <p class="font-semibold">Aucune photo pour le moment</p>
                <p class="text-sm text-muted-color">Soyez le premier ou la première à en partager une !</p>
            </div>

            <template v-else>
                <div class="grid grid-cols-3 gap-1.5">
                    <button v-for="photo in photos" :key="photo.id"
                        class="relative aspect-square rounded-xl overflow-hidden bg-surface-100 dark:bg-surface-800 focus:outline-none"
                        @click="openLightbox(photo)">
                        <img :src="photo.thumb_url" :alt="photo.caption ?? `Photo de ${photo.guest_name}`"
                            class="w-full h-full object-cover" loading="lazy" />
                    </button>
                </div>

                <Button v-if="hasMore" label="Charger plus de photos" variant="outlined" severity="secondary"
                    class="w-full !rounded-xl" :loading="isLoadingMore" @click="loadMore" />
            </template>
        </main>

        <div class="fixed bottom-6 inset-x-0 z-20 flex justify-center pointer-events-none">
            <Button :label="uploading ? uploadProgressLabel : 'Partager des photos'"
                :icon="uploading ? 'pi pi-spin pi-spinner' : 'pi pi-camera'" :disabled="uploading"
                class="!rounded-full !px-6 !py-3 font-semibold shadow-lg pointer-events-auto"
                @click="fileInput?.click()" />
            <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple
                class="hidden" @change="handleFilesSelected" />
        </div>

        <Message v-if="uploadError" severity="error" size="small"
            class="fixed bottom-24 inset-x-4 z-20 max-w-md mx-auto">
            {{ uploadError }}
        </Message>

        <Dialog v-model:visible="captionDialog" header="Ajouter une légende ?" modal dismissableMask
            class="w-[90vw] max-w-md !rounded-2xl" :draggable="false">
            <div class="space-y-4">
                <img v-if="pendingPreview" :src="pendingPreview" class="w-full max-h-64 object-contain rounded-xl" />
                <InputText v-model.trim="pendingCaption" placeholder="Ex: Ouverture du bal !" maxlength="200"
                    class="w-full !rounded-xl" />
            </div>
            <template #footer>
                <Button label="Envoyer" icon="pi pi-send" class="!rounded-xl w-full" @click="confirmCaptionUpload" />
            </template>
        </Dialog>

        <div v-if="lightboxPhoto" class="fixed inset-0 z-50 bg-black/95 flex flex-col" @click.self="lightboxPhoto = null">
            <div class="flex items-center justify-between p-3 text-white/90">
                <span class="text-sm font-medium">{{ lightboxPhoto.guest_name }}</span>
                <div class="flex items-center gap-2 shrink-0">
                    <button v-if="lightboxPhoto.guest_id === me?.id" type="button"
                        class="w-10 h-10 grid place-items-center rounded-full bg-white/15 text-red-400"
                        @click="confirmDelete($event, lightboxPhoto)">
                        <i class="pi pi-trash"></i>
                    </button>
                    <button type="button"
                        class="w-10 h-10 grid place-items-center rounded-full bg-white/15 text-white"
                        @click="lightboxPhoto = null">
                        <i class="pi pi-times"></i>
                    </button>
                </div>
            </div>
            <div class="flex-1 min-h-0 flex items-center justify-center p-2">
                <img :src="lightboxPhoto.full_url" :alt="lightboxPhoto.caption ?? ''"
                    class="max-w-full max-h-full object-contain rounded-lg" />
            </div>
            <p v-if="lightboxPhoto.caption" class="p-4 pt-2 text-center text-sm text-white/80">
                {{ lightboxPhoto.caption }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import galleryClient, { getGalleryToken, setGalleryToken } from '@/api/galleryClient'
import { getGalleryEcho } from '@/galleryEcho'
import { useGalleryRealtime, type GalleryPhoto } from '@/composables/useGalleryRealtime'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import ConfirmPopup from 'primevue/confirmpopup'
import { useConfirm } from 'primevue/useconfirm'
import axios from 'axios'

interface GalleryGuestProfile {
    id: number
    name: string
    photos_count?: number
}

const router = useRouter()
const confirm = useConfirm()

const me = ref<GalleryGuestProfile | null>(null)
const photos = ref<GalleryPhoto[]>([])
const isLoading = ref(true)
const isLoadingMore = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)

const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const uploadQueueTotal = ref(0)
const uploadQueueDone = ref(0)
const uploadError = ref('')

const captionDialog = ref(false)
const pendingFile = ref<File | null>(null)
const pendingPreview = ref<string | null>(null)
const pendingCaption = ref('')

const lightboxPhoto = ref<GalleryPhoto | null>(null)

const hasMore = computed(() => currentPage.value < lastPage.value)
const uploadProgressLabel = computed(() =>
    uploadQueueTotal.value > 1 ? `Envoi ${uploadQueueDone.value + 1}/${uploadQueueTotal.value}…` : 'Envoi…'
)

const fetchProfile = async () => {
    const { data } = await galleryClient.get('/gallery/me')
    me.value = data
}

const fetchPhotos = async (page = 1) => {
    const { data } = await galleryClient.get('/gallery/photos', { params: { page } })
    if (page === 1) {
        photos.value = data.data
    } else {
        photos.value = [...photos.value, ...data.data]
    }
    currentPage.value = data.meta.current_page
    lastPage.value = data.meta.last_page
}

const loadMore = async () => {
    isLoadingMore.value = true
    try {
        await fetchPhotos(currentPage.value + 1)
    } finally {
        isLoadingMore.value = false
    }
}

const upsertPhoto = (photo: GalleryPhoto) => {
    if (photo.hidden) {
        removePhoto(photo)
        return
    }
    const index = photos.value.findIndex((p) => p.id === photo.id)
    if (index !== -1) {
        photos.value.splice(index, 1, photo)
    } else {
        photos.value = [photo, ...photos.value]
    }
}

const removePhoto = (photo: GalleryPhoto) => {
    photos.value = photos.value.filter((p) => p.id !== photo.id)
    if (lightboxPhoto.value?.id === photo.id) {
        lightboxPhoto.value = null
    }
}

const handleFilesSelected = (event: Event) => {
    const input = event.target as HTMLInputElement
    const files = Array.from(input.files ?? [])
    input.value = ''

    if (files.length === 0) return

    uploadError.value = ''

    const single = files.length === 1 ? files[0] : undefined
    if (single) {
        pendingFile.value = single
        pendingPreview.value = URL.createObjectURL(single)
        pendingCaption.value = ''
        captionDialog.value = true
    } else {
        uploadFiles(files)
    }
}

const confirmCaptionUpload = () => {
    captionDialog.value = false
    if (pendingFile.value) {
        uploadFiles([pendingFile.value], pendingCaption.value || undefined)
    }
    if (pendingPreview.value) {
        URL.revokeObjectURL(pendingPreview.value)
        pendingPreview.value = null
    }
    pendingFile.value = null
}

const uploadFiles = async (files: File[], caption?: string) => {
    uploading.value = true
    uploadQueueTotal.value = files.length
    uploadQueueDone.value = 0

    try {
        for (const file of files) {
            const form = new FormData()
            form.append('photo', file)
            if (caption) form.append('caption', caption)

            const { data } = await galleryClient.post('/gallery/photos', form, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })

            upsertPhoto(data)
            uploadQueueDone.value++
        }
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const data = error.response.data as { message?: string }
            uploadError.value = data.message || "Cette image n'a pas pu être envoyée."
        } else if (axios.isAxiosError(error) && error.response?.status === 429) {
            uploadError.value = "Trop d'envois d'un coup — réessayez dans quelques minutes."
        } else {
            uploadError.value = "L'envoi a échoué. Vérifiez votre connexion."
        }
    } finally {
        uploading.value = false
    }
}

const openLightbox = (photo: GalleryPhoto) => {
    lightboxPhoto.value = photo
}

const confirmDelete = (event: MouseEvent, photo: GalleryPhoto) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Supprimer cette photo ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: async () => {
            await galleryClient.delete(`/gallery/photos/${photo.id}`)
            removePhoto(photo)
        },
    })
}

const handleLogout = async () => {
    try {
        await galleryClient.post('/gallery/logout')
    } finally {
        setGalleryToken(null)
        router.push('/gallery/login')
    }
}

onMounted(async () => {
    if (!getGalleryToken()) {
        router.replace('/gallery/login')
        return
    }

    try {
        await Promise.all([fetchProfile(), fetchPhotos()])
    } finally {
        isLoading.value = false
    }
})

useGalleryRealtime(getGalleryEcho(), {
    onCreatedOrUpdated: upsertPhoto,
    onDeleted: removePhoto,
})
</script>
