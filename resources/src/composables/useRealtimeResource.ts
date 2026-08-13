import { onMounted, onUnmounted } from 'vue'
import echo from '@/echo'

interface ResourceChangedEvent<T> {
    resource: string
    action: 'created' | 'updated' | 'deleted'
    payload: T
}

// Écoute le canal partagé "wedding" et ne réagit qu'aux events concernant la
// ressource demandée, pour que le conjoint voie les changements de l'autre
// sans avoir à recharger la page.
export function useRealtimeResource<T>(
    resource: string,
    handlers: { onCreatedOrUpdated: (payload: T) => void; onDeleted: (payload: T) => void }
) {
    const listener = (event: ResourceChangedEvent<T>) => {
        if (event.resource !== resource) return
        if (event.action === 'deleted') {
            handlers.onDeleted(event.payload)
        } else {
            handlers.onCreatedOrUpdated(event.payload)
        }
    }

    onMounted(() => {
        echo.private('wedding').listen('.resource.changed', listener)
    })

    onUnmounted(() => {
        echo.private('wedding').stopListening('.resource.changed', listener)
    })
}
