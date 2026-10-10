import { useSyncExternalStore } from 'react'

/**
 * Everything the app keeps on the device, in one versioned localStorage entry: the web
 * equivalent of the iOS app's Keychain token plus its UserDefaults. Reads never throw
 * (private mode, blocked storage): the app then runs without memory, like a fresh install.
 */
const KEY = 'makanapa:v1'

export interface RecentPick {
  id: number
  name: string
  latitude: number
  longitude: number
  foodCategory: string | null
  priceLevel: number | null
  placeGoogleMapsUrl: string | null
  at: number
  source: 'solo' | 'saved' | 'nearby'
}

export interface SavedPlace {
  id: number
  name: string
  latitude: number
  longitude: number
  savedAt: number
}

export interface PendingVibe {
  decisionId: number
  clientToken: string
  restaurantName: string
  acceptedAt: number
}

export interface Stored {
  token?: string
  installationId?: string
  onboarded?: boolean
  halalOnly?: boolean
  mapsApp?: 'google' | 'waze' | 'naver'
  /** Last 5 accepted picks, newest first. */
  recent?: RecentPick[]
  saved?: SavedPlace[]
  /** "Not this": kept out of Nearby (iOS behaviour), never sent with Decide requests. */
  excluded?: number[]
  /** Last full-flow answers, reused by quick pick. null budget = "Anything". */
  lastBudget?: number | null
  lastDistanceKm?: number
  ignoredContext?: string[]
  pendingVibe?: PendingVibe
  acceptCount?: number
  tasteAsked?: boolean
  /** Decision ids already counted toward the guest sign-up nudge. */
  nudgePicks?: number[]
  nudgeDismissed?: ('picks' | 'saves')[]
  nudgeSnoozedUntil?: number
  /** Nearby chips other than halal (halal follows the account's preference). */
  nearbyFilters?: { openNow: boolean; budgetMax: number | null; minRating: number | null; mode: string; vibe: string | null }
  recentSearches?: string[]
  halalNoticeDismissed?: boolean
  /** Last Nearby result, painted at once on the next visit if it still covers where you are (12 h). */
  nearbyCache?: { viewport: { north: number; south: number; east: number; west: number }; filterKey: string; places: unknown[]; areaSummary: unknown; savedAt: number }
}

const listeners = new Set<() => void>()
let snapshot: Stored = load()

function load(): Stored {
  try {
    const raw = localStorage.getItem(KEY)
    return raw ? (JSON.parse(raw) as Stored) : {}
  } catch {
    return {}
  }
}

export function getStored<K extends keyof Stored>(key: K): Stored[K] {
  return snapshot[key]
}

export function setStored<K extends keyof Stored>(key: K, value: Stored[K]): void {
  const next = { ...snapshot, [key]: value }
  if (value === undefined) delete next[key]
  snapshot = next
  try {
    localStorage.setItem(KEY, JSON.stringify(next))
  } catch {
    // Storage unavailable: the value lives only as long as the page.
  }
  listeners.forEach((listener) => listener())
}

/** Read a stored value and re-render when it changes. */
export function useStored<K extends keyof Stored>(key: K): Stored[K] {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
    () => snapshot[key],
  )
}

/** Test helper: forget everything held in memory. */
export function resetStoredForTests(): void {
  snapshot = load()
}

/** Stable per-browser id, sent with sessions and saves, like iOS's InstallationID. */
export function installationId(): string {
  let id = getStored('installationId')
  if (!id) {
    id = crypto.randomUUID()
    setStored('installationId', id)
  }
  return id
}
