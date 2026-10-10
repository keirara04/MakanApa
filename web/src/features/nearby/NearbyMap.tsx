import { MarkerClusterer, type Renderer } from '@googlemaps/markerclusterer'
import { APIProvider, Map, useMap, useMapsLibrary } from '@vis.gl/react-google-maps'
import { useEffect, useRef, useState } from 'react'
import type { NearbyPlace, Viewport } from '../../api/nearby'
import type { Coordinates } from '../../lib/location'

export const MAPS_KEY: string | undefined = import.meta.env.VITE_GOOGLE_MAPS_KEY || undefined
const MAP_ID: string = import.meta.env.VITE_GOOGLE_MAPS_MAP_ID || 'DEMO_MAP_ID'
const MIN_ZOOM_FOR_PLACES = 14
/** iOS: the "Search this area" pill shows once the centre has moved 35% of the last span. */
const MOVE_THRESHOLD = 0.35

interface Props {
  centre: Coordinates
  user: Coordinates | null
  places: NearbyPlace[]
  /** Ids that pass the chips; the rest are drawn faded, never removed. */
  matching: Set<number>
  loadedViewport: Viewport | null
  winnerId: number | null
  focusId: number | null
  onSelect: (place: NearbyPlace) => void
  onLoadArea: (viewport: Viewport) => void
}

/** Google's map, required for showing Google Places results. Only rendered when a key is set. */
export function NearbyMap(props: Props) {
  return (
    <APIProvider apiKey={MAPS_KEY!} libraries={['marker']}>
      <Map
        mapId={MAP_ID}
        defaultCenter={{ lat: props.centre.latitude, lng: props.centre.longitude }}
        defaultZoom={15}
        gestureHandling="greedy"
        disableDefaultUI
        clickableIcons={false}
        className="h-full w-full"
      />
      <MapLayer {...props} />
    </APIProvider>
  )
}

function MapLayer({ user, places, matching, loadedViewport, winnerId, focusId, onSelect, onLoadArea }: Props) {
  const map = useMap()
  const markerLib = useMapsLibrary('marker')
  const clusterer = useRef<MarkerClusterer | null>(null)
  const markers = useRef(new globalThis.Map<number, google.maps.marker.AdvancedMarkerElement>())
  const [state, setState] = useState<{ zoomedOut: boolean; moved: boolean; bounds: Viewport | null }>({ zoomedOut: false, moved: false, bounds: null })
  const autoLoaded = useRef(false)

  // Track the camera: first settle at a usable zoom loads automatically; later moves offer a button.
  useEffect(() => {
    if (!map) return
    const listener = map.addListener('idle', () => {
      const bounds = map.getBounds()
      const zoom = map.getZoom() ?? 0
      if (!bounds) return
      const ne = bounds.getNorthEast()
      const sw = bounds.getSouthWest()
      const box = { north: ne.lat(), south: sw.lat(), east: ne.lng(), west: sw.lng() }
      const zoomedOut = zoom < MIN_ZOOM_FOR_PLACES
      // Only when nothing is loaded yet: coming back to Nearby keeps the area it had (each
      // load spends Places quota), and "Search this area" offers a reload once it's moved.
      if (!zoomedOut && !autoLoaded.current) {
        autoLoaded.current = true
        if (!loadedViewport) onLoadArea(box)
      }
      let moved = false
      if (loadedViewport) {
        const latSpan = loadedViewport.north - loadedViewport.south
        const lngSpan = loadedViewport.east - loadedViewport.west
        moved =
          Math.abs((box.north + box.south) / 2 - (loadedViewport.north + loadedViewport.south) / 2) / latSpan > MOVE_THRESHOLD ||
          Math.abs((box.east + box.west) / 2 - (loadedViewport.east + loadedViewport.west) / 2) / lngSpan > MOVE_THRESHOLD
      }
      setState({ zoomedOut, moved: moved && !zoomedOut, bounds: box })
    })
    return () => listener.remove()
  }, [map, loadedViewport, onLoadArea])

  // Latest callbacks for marker clicks (markers are reused across renders).
  const select = useRef(onSelect)
  const byId = useRef(new globalThis.Map<number, NearbyPlace>())
  useEffect(() => {
    select.current = onSelect
    byId.current = new globalThis.Map(places.map((place) => [place.id, place]))
  })

  // Rating pills, clustered when they overlap. The pick and the focused place never cluster.
  useEffect(() => {
    if (!map || !markerLib) return
    clusterer.current ??= new MarkerClusterer({ map, renderer: clusterRenderer })
    const existing = markers.current
    const next = new globalThis.Map<number, google.maps.marker.AdvancedMarkerElement>()
    const standalone = new Set([winnerId, focusId].filter((id): id is number => id !== null))

    for (const place of places) {
      if (place.rating === null && !standalone.has(place.id)) continue
      let marker = existing.get(place.id)
      if (!marker) {
        marker = new markerLib.AdvancedMarkerElement({ position: { lat: place.latitude, lng: place.longitude }, title: place.name })
        const id = place.id
        marker.addListener('gmp-click', () => {
          const current = byId.current.get(id)
          if (current) select.current(current)
        })
      }
      marker.content = pill(place, place.id === winnerId, place.id === focusId)
      const faded = winnerId !== null ? place.id !== winnerId : !matching.has(place.id) && place.id !== focusId
      marker.style.opacity = faded ? '0.35' : '1'
      marker.zIndex = standalone.has(place.id) ? 10 : 1
      next.set(place.id, marker)
    }

    clusterer.current.clearMarkers(true)
    existing.forEach((marker) => (marker.map = null))
    const clusterable: google.maps.marker.AdvancedMarkerElement[] = []
    next.forEach((marker, id) => (standalone.has(id) ? (marker.map = map) : clusterable.push(marker)))
    clusterer.current.addMarkers(clusterable, true)
    clusterer.current.render()
    markers.current = next
  }, [map, markerLib, places, matching, winnerId, focusId])

  // The person's own position: a plain blue dot.
  useEffect(() => {
    if (!map || !markerLib || !user) return
    const dot = document.createElement('div')
    dot.className = 'h-4 w-4 rounded-full border-[3px] border-white bg-[#2f7cf6] shadow-[0_0_0_12px_rgb(47_124_246/0.18)]'
    const marker = new markerLib.AdvancedMarkerElement({ map, position: { lat: user.latitude, lng: user.longitude }, content: dot, title: 'Your location' })
    return () => {
      marker.map = null
    }
  }, [map, markerLib, user])

  // Pan to a place picked from search or the list.
  useEffect(() => {
    const place = places.find((item) => item.id === focusId)
    if (map && place) {
      map.panTo({ lat: place.latitude, lng: place.longitude })
      if ((map.getZoom() ?? 0) < 16) map.setZoom(16)
    }
  }, [map, focusId, places])

  return (
    <div className="pointer-events-none absolute inset-x-0 top-3 z-10 flex flex-col items-center gap-2 px-3">
      {state.zoomedOut ? (
        <p className="rounded-full bg-kicap px-4 py-2 font-display text-sm font-semibold text-cream shadow-lift">Zoom in to see places to eat</p>
      ) : (
        state.moved &&
        state.bounds && (
          <button
            type="button"
            onClick={() => {
              onLoadArea(state.bounds!)
              setState((current) => ({ ...current, moved: false }))
            }}
            className="press pointer-events-auto min-h-11 rounded-full bg-kicap px-5 font-display font-semibold text-cream shadow-lift"
          >
            Search this area
          </button>
        )
      )}
      {user && map && (
        <button
          type="button"
          aria-label="Go to my location"
          onClick={() => {
            map.panTo({ lat: user.latitude, lng: user.longitude })
            map.setZoom(15)
          }}
          className="press pointer-events-auto absolute right-3 top-0 flex h-11 w-11 items-center justify-center rounded-full border-2 border-kicap bg-surface shadow-lift"
        >
          <svg viewBox="0 0 24 24" className="h-5 w-5" fill="currentColor" aria-hidden="true">
            <path d="M12 2 4 20l8-4 8 4z" transform="rotate(45 12 12)" />
          </svg>
        </button>
      )}
    </div>
  )
}

function pill(place: NearbyPlace, winner: boolean, focused: boolean): HTMLElement {
  const element = document.createElement('div')
  element.textContent = place.rating !== null ? `★${place.rating.toFixed(1)}` : '•'
  element.className = winner
    ? 'rounded-full bg-[#c13a26] px-3 py-1.5 font-[Fredoka] text-[15px] font-bold text-white shadow-lg'
    : `rounded-full bg-white px-2 py-1 font-[Fredoka] text-xs font-bold text-[#29201d] shadow-md ${focused ? 'scale-110 outline-2 outline-[#29201d]' : ''}`
  return element
}

const clusterRenderer: Renderer = {
  render: ({ count, position }) => {
    const bubble = document.createElement('div')
    bubble.textContent = String(count)
    bubble.className = 'flex h-[30px] min-w-[30px] items-center justify-center rounded-full border-2 border-white bg-[#29201d] px-2 font-[Fredoka] text-[13px] font-bold text-white shadow-md'
    return new google.maps.marker.AdvancedMarkerElement({ position, content: bubble, title: `${count} places here`, zIndex: 5 })
  },
}
