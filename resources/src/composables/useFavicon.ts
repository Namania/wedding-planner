import { ref } from 'vue'

export function useFavicon() {
    const failed = ref<Set<number>>(new Set())

    const getFaviconUrl = (website: string | null): string | null => {
        if (!website) return null
        try {
            return `${new URL(website).origin}/favicon.ico`
        } catch {
            return null
        }
    }

    const showFavicon = (id: number, website: string | null): boolean =>
        !!getFaviconUrl(website) && !failed.value.has(id)

    const onFaviconError = (id: number) => {
        failed.value.add(id)
    }

    return { getFaviconUrl, showFavicon, onFaviconError }
}
