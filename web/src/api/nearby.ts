import { installationId } from '../lib/storage'
import { api } from './client'
import type { Decision, HalalInfo, Photo, Review } from './decide'

export type HalalSummary = Pick<HalalInfo, 'status'> & { display: Pick<HalalInfo['display'], 'shortLabel' | 'longLabel' | 'tone' | 'action'> }

/** A pin: `presentMarker` in NearbyController. */
export interface NearbyPlace {
  id: number
  name: string
  rating: number | null
  priceLevel: number | null
  latitude: number
  longitude: number
  openStatus: 'open' | 'closed' | 'unknown'
  halal?: HalalSummary
}

export interface AreaSummary {
  placeCount: number
  openNowCount: number
  budgetFriendlyCount: number
  topCategories: { label: string; count: number }[]
  topRated: NearbyPlace[]
  communityFinds: NearbyPlace[]
  personalityTags: { key: string; label: string }[]
}

export interface Viewport {
  north: number
  south: number
  east: number
  west: number
}

export type DiscoveryMode = 'normal' | 'popular' | 'low_key' | 'cafe' | 'cheap_eats' | 'late_night'
export type Vibe = 'chill' | 'study' | 'dessert' | 'brunch'

export interface NearbyFilters {
  openNow: boolean
  budgetMax: number | null
  minRating: number | null
  mode: DiscoveryMode
  vibe: Vibe | null
  halal: boolean
}

export interface SearchResult {
  provenance: 'canonical' | 'community' | 'google_fallback' | 'kakao'
  id: number | null
  googlePlaceId: string | null
  /** Korea only. Kakao rows are live-only (Kakao's terms): no id, no resolve, link out to kakaoUrl. */
  kakaoPlaceId: string | null
  kakaoUrl: string | null
  name: string
  address: string | null
  category: string | null
  cuisine: string | null
  latitude: number
  longitude: number
  priceLevel: number | null
  rating: number | null
  distanceKm: number | null
  openStatus: 'open' | 'closed' | 'unknown'
  closesAt: string | null
  halal: HalalSummary
  isCommunityFind: boolean
  shareUrl: string | null
  groupKey: string | null
  groupSize: number
}

export interface SearchResponse {
  results: SearchResult[]
  meta: { radiusKm: number; source: 'local' | 'google' | 'kakao' | 'mixed'; googleAvailable: boolean; fallback: 'google' | 'kakao' | null; widerRadiusKm: number | null; total: number }
  suggestions: string[]
}

export interface PlaceDetails {
  id: number
  name: string
  latitude: number
  longitude: number
  shareUrl: string
  foodCategory: string | null
  rating: number | null
  priceLevel: number | null
  cuisines: string[]
  openStatus: 'open' | 'closed' | 'unknown'
  photos: Photo[]
  reviews: Review[]
  placeGoogleMapsUrl: string | null
  closesAt: string | null
  phone: string | null
  instagramHandle: string | null
  tiktokHandle: string | null
  websiteUrl: string | null
  menuItems: { name: string; description: string | null; price: number | null; category: string | null }[]
  communityPhotos: string[]
  halal: HalalInfo
}

export type OpenedFrom = 'share' | 'nudge' | 'search' | 'community' | 'recommendation' | 'nearby'

function filterQuery(filters: NearbyFilters): Record<string, string> {
  const query: Record<string, string> = { mode: filters.mode, halal: filters.halal ? '1' : '0' }
  if (filters.openNow) query.openNow = '1'
  if (filters.budgetMax) query.budgetMax = String(filters.budgetMax)
  if (filters.minRating) query.minRating = String(filters.minRating)
  if (filters.vibe) query.vibe = filters.vibe
  return query
}

export const nearbyApi = {
  /** Every place in the box (only halal filters it); the summary reflects all the filters. Google-backed. */
  places: (viewport: Viewport, filters: NearbyFilters, signal?: AbortSignal) => {
    const query = new URLSearchParams({ ...mapValues(viewport), ...filterQuery(filters) })
    return api<{ places: NearbyPlace[]; areaSummary: AreaSummary }>(`places/nearby?${query}`, { signal })
  },

  pick: (body: { latitude: number; longitude: number; viewport: Viewport; visiblePlaceIds: number[]; filters: NearbyFilters; ignoreContext: string[] }) =>
    api<Decision>('places/nearby/pick', {
      method: 'POST',
      body: {
        latitude: body.latitude,
        longitude: body.longitude,
        viewport: body.viewport,
        visiblePlaceIds: body.visiblePlaceIds,
        openNow: body.filters.openNow || null,
        budgetMax: body.filters.budgetMax,
        minRating: body.filters.minRating,
        mode: body.filters.mode,
        vibe: body.filters.vibe,
        halal: body.filters.halal,
        installationId: installationId(),
        ignoreContext: body.ignoreContext,
      },
    }),

  search: (params: { query: string; latitude: number; longitude: number; radiusKm: number; halal: boolean; includeGoogle?: boolean }, signal?: AbortSignal) => {
    const query = new URLSearchParams({
      query: params.query,
      latitude: String(params.latitude),
      longitude: String(params.longitude),
      radiusKm: String(params.radiusKm),
      halal: params.halal ? '1' : '0',
      // This client renders Kakao rows, so the server may use Kakao instead of Google in Korea.
      kakao: '1',
    })
    if (params.includeGoogle) query.set('includeGoogle', '1')
    return api<SearchResponse>(`places/search?${query}`, { signal })
  },

  /** Turns a Google-only search result into a MakanApa place (one Place Details call if new). */
  resolve: (googlePlaceId: string) => api<{ restaurant: SearchResult }>('places/resolve', { method: 'POST', body: { googlePlaceId } }),

  details: (id: number, signal?: AbortSignal) => api<PlaceDetails>(`restaurants/${id}/details`, { signal }),

  /** "Eat here": an accepted pick of this exact place. Same clientChoiceId on retry. */
  choose: (id: number, body: { clientChoiceId: string; latitude?: number; longitude?: number; openedFrom: OpenedFrom; search?: { query: string; radiusKm: number; source: string } }) =>
    api<{ decisionId: number; clientToken: string; created: boolean }>(`restaurants/${id}/choose`, {
      method: 'POST',
      body: { ...body, installationId: installationId() },
    }),

  quickAddPhoto: (id: number, photo: Blob) => {
    const form = new FormData()
    form.append('photo', photo, 'photo.jpg')
    form.append('photoType', 'other')
    return api<{ photo: { id: number; photoType: string } }>(`restaurants/${id}/photos/quick-add`, { method: 'POST', body: form })
  },

  ambassadorPicks: () => api<{ picks: { restaurantId: number; note: string | null }[]; max: number }>('me/ambassador-picks'),
  addAmbassadorPick: (restaurantId: number, note: string | null) =>
    api<{ pick: { restaurantId: number; note: string | null } }>('me/ambassador-picks', { method: 'POST', body: { restaurantId, note } }),
  removeAmbassadorPick: (restaurantId: number) => api<{ removed: true }>(`me/ambassador-picks/${restaurantId}`, { method: 'DELETE' }),
}

function mapValues(viewport: Viewport): Record<string, string> {
  return Object.fromEntries(Object.entries(viewport).map(([key, value]) => [key, value.toFixed(6)]))
}

/**
 * A box around a point. Half-width 0.7 km keeps the server's search circle (centre to corner)
 * just under 1 km, which it covers with one Google tile instead of seven.
 */
export function boxAround(latitude: number, longitude: number, halfKm = 0.7): Viewport {
  const latDelta = halfKm / 111.32
  const lngDelta = halfKm / (111.32 * Math.cos((latitude * Math.PI) / 180))
  return { north: latitude + latDelta, south: latitude - latDelta, east: longitude + lngDelta, west: longitude - lngDelta }
}

export function centreOf(viewport: Viewport): { latitude: number; longitude: number } {
  return { latitude: (viewport.north + viewport.south) / 2, longitude: (viewport.east + viewport.west) / 2 }
}

/** Straight-line km, for "400 m away" and sorting by distance. */
export function distanceKm(a: { latitude: number; longitude: number }, b: { latitude: number; longitude: number }): number {
  const rad = Math.PI / 180
  const dLat = (b.latitude - a.latitude) * rad
  const dLng = (b.longitude - a.longitude) * rad
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(a.latitude * rad) * Math.cos(b.latitude * rad) * Math.sin(dLng / 2) ** 2
  return 12742 * Math.asin(Math.sqrt(h))
}

/** iOS filteredPlaces: the chips narrow what can be picked; markers outside it only fade. */
export function matchesFilters(place: NearbyPlace, filters: NearbyFilters): boolean {
  if (filters.openNow && place.openStatus !== 'open') return false
  if (filters.budgetMax && place.priceLevel !== null && place.priceLevel > filters.budgetMax) return false
  if (filters.minRating && (place.rating === null || place.rating < filters.minRating)) return false
  return true
}
