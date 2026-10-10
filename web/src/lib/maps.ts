import type { Stored } from './storage'

export type MapsApp = NonNullable<Stored['mapsApp']>

interface Place {
  name: string
  latitude: number
  longitude: number
  placeGoogleMapsUrl?: string | null
}

/**
 * Where "Let's eat" sends people. Google uses the place's own Maps link when the API has one
 * (it opens the listing, not just a pin); Waze and Naver navigate to the coordinates.
 */
export function directionsUrl(place: Place, app: MapsApp = 'google'): string {
  const { latitude, longitude, name } = place
  switch (app) {
    case 'waze':
      return `https://waze.com/ul?ll=${latitude},${longitude}&navigate=yes`
    case 'naver':
      return `https://m.map.naver.com/search2/search.naver?query=${encodeURIComponent(name)}`
    default:
      return place.placeGoogleMapsUrl ?? `https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`
  }
}
