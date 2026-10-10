import { createContext, use, useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { authApi } from '../../api/auth'
import { ApiError } from '../../api/client'
import { matchesFilters, nearbyApi, type AreaSummary, type NearbyFilters, type NearbyPlace, type Viewport } from '../../api/nearby'
import { useSession } from '../../auth/session'
import { getStored, setStored, useStored } from '../../lib/storage'

const CACHE_HOURS = 12

interface NearbyContextValue {
  filters: NearbyFilters
  setFilters: (change: Partial<NearbyFilters>) => void
  resetFilters: () => void
  /** The box the current places were loaded for. */
  viewport: Viewport | null
  places: NearbyPlace[]
  /** Places that pass the chips: the pick pool and the list. Others only fade on the map. */
  filtered: NearbyPlace[]
  summary: AreaSummary | null
  loading: boolean
  error: unknown
  clearError: () => void
  load: (viewport: Viewport) => Promise<void>
  /** Adds a place found through search, so it can be shown and picked. */
  addPlace: (place: NearbyPlace) => void
  /** "Don't suggest": hidden here and from search for good. */
  exclude: (id: number) => void
}

const DEFAULT_FILTERS = { openNow: false, budgetMax: null, minRating: null, mode: 'normal', vibe: null } as const
const NearbyContext = createContext<NearbyContextValue | null>(null)

function filterKey(filters: NearbyFilters): string {
  return JSON.stringify([filters.openNow, filters.budgetMax, filters.minRating, filters.mode, filters.vibe, filters.halal])
}

export function NearbyProvider({ children }: { children: ReactNode }) {
  const { user, setUser } = useSession()
  const stored = useStored('nearbyFilters') ?? DEFAULT_FILTERS
  const excluded = useStored('excluded') ?? []
  const halal = user?.halalPreference ?? getStored('halalOnly') ?? false
  const filters = useMemo<NearbyFilters>(
    () => ({ ...(stored as Omit<NearbyFilters, 'halal'>), halal }),
    [stored, halal],
  )

  const [viewport, setViewport] = useState<Viewport | null>(null)
  const [raw, setRaw] = useState<NearbyPlace[]>([])
  const [summary, setSummary] = useState<AreaSummary | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const inFlight = useRef<AbortController | null>(null)
  const lastKey = useRef<string | null>(null)

  const load = useCallback(
    async (box: Viewport) => {
      const key = JSON.stringify([box, filterKey(filters)])
      if (key === lastKey.current) return
      inFlight.current?.abort()
      const controller = new AbortController()
      inFlight.current = controller

      // First visit: paint the cached result at once if it still covers this spot.
      if (!lastKey.current) {
        const cache = getStored('nearbyCache')
        const centre = { latitude: (box.north + box.south) / 2, longitude: (box.east + box.west) / 2 }
        if (
          cache &&
          cache.filterKey === filterKey(filters) &&
          Date.now() - cache.savedAt < CACHE_HOURS * 3_600_000 &&
          centre.latitude < cache.viewport.north && centre.latitude > cache.viewport.south &&
          centre.longitude < cache.viewport.east && centre.longitude > cache.viewport.west
        ) {
          setViewport(cache.viewport)
          setRaw(cache.places as NearbyPlace[])
          setSummary(cache.areaSummary as AreaSummary)
        }
      }

      setLoading(true)
      setError(null)
      try {
        const response = await nearbyApi.places(box, filters, controller.signal)
        lastKey.current = key
        setViewport(box)
        setRaw(response.places)
        setSummary(response.areaSummary)
        setStored('nearbyCache', { viewport: box, filterKey: filterKey(filters), places: response.places, areaSummary: response.areaSummary, savedAt: Date.now() })
      } catch (caught) {
        if (caught instanceof DOMException && caught.name === 'AbortError') return
        setError(caught)
      } finally {
        if (inFlight.current === controller) setLoading(false)
      }
    },
    [filters],
  )

  // A chip change reloads the same area with the new filters.
  const lastFilters = useRef(filterKey(filters))
  useEffect(() => {
    const key = filterKey(filters)
    if (key === lastFilters.current) return
    lastFilters.current = key
    if (viewport) void load(viewport)
  }, [filters, viewport, load])

  const setFilters = useCallback(
    (change: Partial<NearbyFilters>) => {
      const { halal: halalChange, ...rest } = change
      if (Object.keys(rest).length) {
        const next = { ...(getStored('nearbyFilters') ?? DEFAULT_FILTERS), ...rest }
        // Back to "For you" clears the vibe, as on iOS.
        if (rest.mode === 'normal') next.vibe = null
        setStored('nearbyFilters', next)
      }
      if (halalChange !== undefined) {
        setStored('halalOnly', halalChange)
        if (user) {
          setUser({ ...user, halalPreference: halalChange })
          void authApi.updateProfile({ halalPreference: halalChange }).then(({ user: updated }) => setUser(updated)).catch(() => {})
        }
      }
    },
    [user, setUser],
  )

  const value = useMemo<NearbyContextValue>(() => {
    const places = raw.filter((place) => !excluded.includes(place.id))
    return {
      filters,
      setFilters,
      resetFilters: () => setStored('nearbyFilters', { ...DEFAULT_FILTERS }),
      viewport,
      places,
      filtered: places.filter((place) => matchesFilters(place, filters)),
      summary,
      loading,
      error,
      clearError: () => setError(null),
      load,
      addPlace: (place) => setRaw((current) => (current.some((item) => item.id === place.id) ? current : [...current, place])),
      exclude: (id) => {
        setStored('excluded', [...new Set([...(getStored('excluded') ?? []), id])])
        setStored('saved', (getStored('saved') ?? []).filter((item) => item.id !== id))
      },
    }
  }, [raw, excluded, filters, setFilters, viewport, summary, loading, error, load])

  return <NearbyContext value={value}>{children}</NearbyContext>
}

export function useNearby(): NearbyContextValue {
  const value = use(NearbyContext)
  if (!value) throw new Error('useNearby must be used inside NearbyProvider')
  return value
}

export function isRateLimited(error: unknown): error is ApiError {
  return error instanceof ApiError && error.status === 429
}
