import { onMounted, onUnmounted } from 'vue'
import type Echo from 'laravel-echo'

export interface GalleryPhoto {
    id: number
    guest_id: number
    guest_name: string | null
    caption: string | null
    width: number
    height: number
    hidden: boolean
    thumb_url: string
    full_url: string
    created_at: string
}

interface GalleryPhotoChangedEvent {
    action: 'created' | 'updated' | 'deleted'
    payload: GalleryPhoto
}

export function useGalleryRealtime(
    echoInstance: Echo<'reverb'>,
    handlers: { onCreatedOrUpdated: (photo: GalleryPhoto) => void; onDeleted: (photo: GalleryPhoto) => void }
) {
    const listener = (event: GalleryPhotoChangedEvent) => {
        if (event.action === 'deleted') {
            handlers.onDeleted(event.payload)
        } else {
            handlers.onCreatedOrUpdated(event.payload)
        }
    }

    onMounted(() => {
        echoInstance.private('gallery').listen('.photo.changed', listener)
    })

    onUnmounted(() => {
        echoInstance.private('gallery').stopListening('.photo.changed', listener)
    })
}
