<template>
    <div class="space-y-5 pb-12">
        <ConfirmPopup class="mx-4" />

        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-2xl font-bold tracking-tight">Galerie photos</h1>
            <Button icon="pi pi-play" label="Diaporama" variant="outlined" size="small" class="!rounded-xl"
                @click="router.push('/slideshow')" />
        </div>

        <div
            class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm p-4 space-y-4">
            <h2 class="font-semibold">QR code d'invitation</h2>

            <div class="flex flex-col items-center gap-3">
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR code d'invitation"
                    class="w-48 h-48 max-w-full rounded-xl border border-surface-200 dark:border-surface-800 bg-white p-2" />
                <Skeleton v-else class="!w-48 !h-48 !rounded-xl" />

                <div class="w-full flex items-center gap-2">
                    <InputText :modelValue="shareUrl" readonly class="w-full min-w-0 !rounded-xl !text-xs" />
                    <Button :icon="copied ? 'pi pi-check' : 'pi pi-copy'" variant="outlined" class="!rounded-xl shrink-0"
                        @click="copyShareUrl" />
                </div>
            </div>

            <p class="text-xs text-muted-color">
                À poser sur les tables : les invités le scannent, choisissent un prénom et un code PIN, puis
                partagent leurs photos.
            </p>

            <Button label="Imprimer le QR code" icon="pi pi-print" variant="outlined" size="small"
                class="w-full !rounded-xl" :disabled="!qrDataUrl" @click="printQr" />

            <Button label="Regénérer le lien d'invitation" icon="pi pi-refresh" severity="danger"
                variant="text" size="small" class="!rounded-xl w-full !whitespace-normal" @click="confirmRotate($event)" />
        </div>

        <div v-if="settings"
            class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm p-4 space-y-4">
            <h2 class="font-semibold">Réglages</h2>

            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium">Inscriptions ouvertes</p>
                    <p class="text-xs text-muted-color">À couper après le mariage : la galerie reste accessible aux
                        inscrits.</p>
                </div>
                <ToggleSwitch v-model="settings.registrations_open" class="shrink-0" @change="saveSettings" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-wider text-muted-color">Invités max</label>
                    <InputNumber v-model="settings.max_guests" :min="1" :max="5000" class="w-full"
                        :inputClass="'w-full !rounded-xl'" @blur="saveSettings" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-wider text-muted-color">Photos / invité</label>
                    <InputNumber v-model="settings.max_photos_per_guest" :min="1" :max="1000" class="w-full"
                        :inputClass="'w-full !rounded-xl'" @blur="saveSettings" />
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-color">
                <span>{{ settings.guests_count }} invité·es inscrit·es · {{ settings.photos_count }} photos</span>
                <Button icon="pi pi-download" label="Tout télécharger" size="small" variant="outlined"
                    class="!rounded-xl" @click="exportZip" />
            </div>
        </div>

        <div
            class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm p-4 space-y-3">
            <h2 class="font-semibold">Invités inscrits <span
                    class="text-sm font-normal text-muted-color">({{ guests.length }})</span></h2>

            <p v-if="guests.length === 0" class="text-sm text-muted-color">Personne ne s'est encore inscrit.</p>

            <div v-for="guest in guests" :key="guest.id"
                class="flex items-center justify-between gap-2 py-2 border-b border-surface-100 dark:border-surface-800 last:border-0">
                <div class="min-w-0">
                    <p class="font-medium truncate">
                        {{ guest.name }}
                        <Tag v-if="guest.banned" value="Banni" severity="danger" class="!text-xs ml-1" />
                    </p>
                    <p class="text-xs text-muted-color">{{ guest.photos_count }} photo(s)</p>
                </div>
                <div class="flex items-center shrink-0">
                    <Button icon="pi pi-key" variant="text" severity="secondary" class="!w-8 !h-8"
                        title="Nouveau PIN" @click="resetPin(guest)" />
                    <Button v-if="!guest.banned" icon="pi pi-ban" variant="text" severity="danger" class="!w-8 !h-8"
                        @click="banGuest(guest)" />
                    <Button v-else icon="pi pi-undo" variant="text" severity="secondary" class="!w-8 !h-8"
                        @click="unbanGuest(guest)" />
                    <Button icon="pi pi-trash" variant="text" severity="danger" class="!w-8 !h-8"
                        @click="confirmDeleteGuest($event, guest)" />
                </div>
            </div>
        </div>

        <div
            class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm p-4 space-y-3">
            <h2 class="font-semibold">Photos</h2>

            <p v-if="photos.length === 0" class="text-sm text-muted-color">Aucune photo pour le moment.</p>

            <div class="grid grid-cols-3 gap-1.5">
                <div v-for="photo in photos" :key="photo.id"
                    class="relative aspect-square rounded-xl overflow-hidden bg-surface-100 dark:bg-surface-800">
                    <img :src="photo.thumb_url" class="w-full h-full object-cover"
                        :class="{ 'opacity-30': photo.hidden }" loading="lazy" />
                    <div class="absolute inset-x-0 bottom-0 flex justify-between items-end p-1 bg-gradient-to-t from-black/60 to-transparent">
                        <span class="text-[10px] text-white/90 truncate max-w-[50%]">{{ photo.guest_name }}</span>
                        <div class="flex">
                            <button class="w-7 h-7 grid place-items-center text-white/90"
                                @click="photo.hidden ? unhidePhoto(photo) : hidePhoto(photo)">
                                <i class="pi text-xs" :class="photo.hidden ? 'pi-eye' : 'pi-eye-slash'"></i>
                            </button>
                            <button class="w-7 h-7 grid place-items-center text-red-300"
                                @click="confirmDeletePhoto($event, photo)">
                                <i class="pi pi-trash text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <Tag v-if="photo.hidden" value="Masquée" severity="warn"
                        class="!absolute top-1 left-1 !text-[10px]" />
                </div>
            </div>

            <Button v-if="hasMore" label="Charger plus" variant="outlined" severity="secondary"
                class="w-full !rounded-xl" :loading="isLoadingMore" @click="loadMore" />
        </div>

        <Dialog v-model:visible="pinDialog" header="Nouveau code PIN" modal class="w-[90vw] max-w-md !rounded-2xl"
            :draggable="false">
            <div class="text-center space-y-2">
                <p class="text-sm text-muted-color">Transmettez ce code à {{ pinGuestName }} — il ne sera plus
                    affiché ensuite.</p>
                <p class="text-3xl font-bold tracking-[0.3em]">{{ newPin }}</p>
            </div>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/api/client'
import echo from '@/echo'
import { useGalleryRealtime, type GalleryPhoto } from '@/composables/useGalleryRealtime'
import QRCode from 'qrcode'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import ToggleSwitch from 'primevue/toggleswitch'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import ConfirmPopup from 'primevue/confirmpopup'
import { useConfirm } from 'primevue/useconfirm'

interface GallerySettings {
    invite_token: string
    registrations_open: boolean
    registration_closes_at: string | null
    max_guests: number
    max_photos_per_guest: number
    guests_count: number
    photos_count: number
}

interface GalleryGuestRow {
    id: number
    name: string
    banned: boolean
    photos_count: number
    last_seen_at: string | null
}

const router = useRouter()
const confirm = useConfirm()

const settings = ref<GallerySettings | null>(null)
const guests = ref<GalleryGuestRow[]>([])
const photos = ref<GalleryPhoto[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoadingMore = ref(false)

const qrDataUrl = ref('')
const copied = ref(false)

const pinDialog = ref(false)
const newPin = ref('')
const pinGuestName = ref('')

const hasMore = computed(() => currentPage.value < lastPage.value)
const shareUrl = computed(() =>
    settings.value ? `${window.location.origin}/share/${settings.value.invite_token}` : ''
)

watch(shareUrl, async (url) => {
    if (!url) return
    qrDataUrl.value = await QRCode.toDataURL(url, { width: 512, margin: 2 })
})

const fetchSettings = async () => {
    const { data } = await apiClient.get('/gallery-admin/settings')
    settings.value = data
}

const fetchGuests = async () => {
    const { data } = await apiClient.get('/gallery-admin/guests')
    guests.value = data
}

const fetchPhotos = async (page = 1) => {
    const { data } = await apiClient.get('/gallery-admin/photos', { params: { page } })
    photos.value = page === 1 ? data.data : [...photos.value, ...data.data]
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

const saveSettings = async () => {
    if (!settings.value) return
    const { data } = await apiClient.put('/gallery-admin/settings', {
        registrations_open: settings.value.registrations_open,
        max_guests: settings.value.max_guests,
        max_photos_per_guest: settings.value.max_photos_per_guest,
    })
    settings.value = data
}

const confirmRotate = (event: MouseEvent) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Tous les QR codes déjà imprimés cesseront de fonctionner. Continuer ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Regénérer', severity: 'danger' },
        accept: async () => {
            const { data } = await apiClient.post('/gallery-admin/settings/rotate-token')
            settings.value = data
        },
    })
}

const copyShareUrl = async () => {
    await navigator.clipboard.writeText(shareUrl.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
}

const printQr = () => {
    const win = window.open('', '_blank', 'width=600,height=700')
    if (!win) return
    win.document.write(`
        <html>
        <head><title>QR code — Galerie photos du mariage</title></head>
        <body style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:95vh;font-family:sans-serif;text-align:center;">
            <h1 style="font-size:1.5rem;">Partagez vos photos du mariage !</h1>
            <p style="color:#555;">Scannez ce QR code avec votre téléphone</p>
            <img src="${qrDataUrl.value}" style="width:380px;height:380px;" onload="window.print()" />
        </body>
        </html>
    `)
    win.document.close()
}

const exportZip = () => {
    const base = (import.meta.env.VITE_API_BASE_URL as string) || '/api'
    window.open(`${base}/gallery-admin/export`, '_blank')
}

const resetPin = async (guest: GalleryGuestRow) => {
    const { data } = await apiClient.post(`/gallery-admin/guests/${guest.id}/reset-pin`)
    newPin.value = data.pin
    pinGuestName.value = guest.name
    pinDialog.value = true
}

const banGuest = async (guest: GalleryGuestRow) => {
    const { data } = await apiClient.patch(`/gallery-admin/guests/${guest.id}/ban`)
    Object.assign(guest, data)
    await fetchPhotos()
}

const unbanGuest = async (guest: GalleryGuestRow) => {
    const { data } = await apiClient.patch(`/gallery-admin/guests/${guest.id}/unban`)
    Object.assign(guest, data)
}

const confirmDeleteGuest = (event: MouseEvent, guest: GalleryGuestRow) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: `Supprimer ${guest.name} et toutes ses photos ?`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: async () => {
            await apiClient.delete(`/gallery-admin/guests/${guest.id}`)
            guests.value = guests.value.filter((g) => g.id !== guest.id)
            photos.value = photos.value.filter((p) => p.guest_id !== guest.id)
        },
    })
}

const hidePhoto = async (photo: GalleryPhoto) => {
    const { data } = await apiClient.patch(`/gallery-admin/photos/${photo.id}/hide`)
    upsertPhoto(data)
}

const unhidePhoto = async (photo: GalleryPhoto) => {
    const { data } = await apiClient.patch(`/gallery-admin/photos/${photo.id}/unhide`)
    upsertPhoto(data)
}

const confirmDeletePhoto = (event: MouseEvent, photo: GalleryPhoto) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: 'Supprimer définitivement cette photo ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: async () => {
            await apiClient.delete(`/gallery-admin/photos/${photo.id}`)
            photos.value = photos.value.filter((p) => p.id !== photo.id)
        },
    })
}

const upsertPhoto = (photo: GalleryPhoto) => {
    const index = photos.value.findIndex((p) => p.id === photo.id)
    if (index !== -1) {
        photos.value.splice(index, 1, photo)
    } else {
        photos.value = [photo, ...photos.value]
    }
}

onMounted(() => {
    fetchSettings()
    fetchGuests()
    fetchPhotos()
})

useGalleryRealtime(echo, {
    onCreatedOrUpdated: upsertPhoto,
    onDeleted: (photo) => {
        photos.value = photos.value.filter((p) => p.id !== photo.id)
    },
})
</script>
