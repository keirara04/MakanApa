import { useSyncExternalStore } from 'react'

/** Chrome's install offer (not in TypeScript's DOM types). */
interface InstallPromptEvent extends Event {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

// Chrome offers installing once, early, often before any screen that shows the card has mounted:
// kept here from the start.
let offer: InstallPromptEvent | null = null
const listeners = new Set<() => void>()
const notify = () => listeners.forEach((listener) => listener())

if (typeof window !== 'undefined') {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault()
    offer = event as InstallPromptEvent
    notify()
  })
  window.addEventListener('appinstalled', () => {
    offer = null
    notify()
  })
}

const subscribe = (listener: () => void) => {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

/** True while the browser would install MakanApa (Chrome on Android and computers, not yet installed). */
export function useCanInstall(): boolean {
  return useSyncExternalStore(subscribe, () => offer !== null)
}

/** Shows the browser's own install dialog. Resolves true when the person installs. */
export async function install(): Promise<boolean> {
  const current = offer
  if (!current) return false
  await current.prompt()
  const { outcome } = await current.userChoice
  offer = null
  notify()
  return outcome === 'accepted'
}

/** Online or not, kept current. */
export function useOnline(): boolean {
  return useSyncExternalStore(
    (listener) => {
      window.addEventListener('online', listener)
      window.addEventListener('offline', listener)
      return () => {
        window.removeEventListener('online', listener)
        window.removeEventListener('offline', listener)
      }
    },
    () => navigator.onLine,
  )
}
