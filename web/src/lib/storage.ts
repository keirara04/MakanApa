/**
 * Everything the app keeps on the device, in one versioned localStorage entry: the web
 * equivalent of the iOS app's Keychain token plus its UserDefaults. Reads never throw
 * (private mode, blocked storage): the app then runs without memory, like a fresh install.
 */
const KEY = 'makanapa:v1'

export interface Stored {
  token?: string
  installationId?: string
  onboarded?: boolean
  halalOnly?: boolean
  mapsApp?: 'google' | 'waze' | 'naver'
}

function read(): Stored {
  try {
    const raw = localStorage.getItem(KEY)
    return raw ? (JSON.parse(raw) as Stored) : {}
  } catch {
    return {}
  }
}

export function getStored<K extends keyof Stored>(key: K): Stored[K] {
  return read()[key]
}

export function setStored<K extends keyof Stored>(key: K, value: Stored[K]): void {
  try {
    const next = { ...read(), [key]: value }
    if (value === undefined) delete next[key]
    localStorage.setItem(KEY, JSON.stringify(next))
  } catch {
    // Storage unavailable: the value lives only as long as the page.
  }
}

/** Stable per-browser id, sent with sessions and (later) push subscriptions, like iOS's InstallationID. */
export function installationId(): string {
  let id = getStored('installationId')
  if (!id) {
    id = crypto.randomUUID()
    setStored('installationId', id)
  }
  return id
}
