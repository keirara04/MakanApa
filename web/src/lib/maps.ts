import type { Stored } from './storage'

/** The Google Maps key; without one, Nearby and Add a place work without a map. */
export const MAPS_KEY: string | undefined = import.meta.env.VITE_GOOGLE_MAPS_KEY || undefined

export type MapsApp = NonNullable<Stored['mapsApp']>

interface Place {
  name: string
  latitude: number
  longitude: number
  placeGoogleMapsUrl?: string | null
}

/**
 * Where "Let's eat" sends people. Google uses the place's own Maps link when the API has one
 * (it opens the listing, not just a pin); Waze, Naver and Kakao navigate to the coordinates.
 */
export function directionsUrl(place: Place, app: MapsApp = 'google'): string {
  const { latitude, longitude, name } = place
  switch (app) {
    case 'waze':
      return `https://waze.com/ul?ll=${latitude},${longitude}&navigate=yes`
    case 'kakao':
      return `https://map.kakao.com/link/to/${encodeURIComponent(name)},${latitude},${longitude}`
    case 'naver':
      return `https://m.map.naver.com/search2/search.naver?query=${encodeURIComponent(name)}`
    default:
      return place.placeGoogleMapsUrl ?? `https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`
  }
}
