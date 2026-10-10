import { APIProvider, Map, Marker, useMap } from '@vis.gl/react-google-maps'
import { useEffect, useRef, useState } from 'react'
import type { Coordinates } from '../../lib/location'
import { Button } from '../../ui/Button'
import { MAPS_KEY } from '../nearby/NearbyMap'

const KUALA_LUMPUR = { latitude: 3.139, longitude: 101.6869 }

/**
 * Drag the map, not the pin: the pin stays in the middle and its spot is what gets confirmed
 * (iOS MapPinPickerView). Only shown when a Maps key is set. Reverse geocoding is left out: on
 * the web it bills per call, and the coordinates are what the reviewer uses.
 */
export function PinPicker({ start, onConfirm, onClose }: { start: Coordinates | null; onConfirm: (coords: Coordinates) => void; onClose: () => void }) {
  const [initial] = useState(() => start ?? KUALA_LUMPUR)
  const centre = useRef(initial)

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => event.key === 'Escape' && onClose()
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  return (
    <div role="dialog" aria-modal="true" aria-label="Choose the spot on the map" className="fixed inset-0 z-50 flex flex-col bg-cream">
      <div className="flex items-center justify-between px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-2">
        <button type="button" onClick={onClose} className="min-h-11 px-2 font-display font-semibold text-sambal-deep">
          Cancel
        </button>
        <h2 className="font-display text-lg font-bold">Drag the map to the spot</h2>
        <span className="w-16" />
      </div>
      <div className="relative flex-1">
        <APIProvider apiKey={MAPS_KEY!}>
          <Map
            defaultCenter={{ lat: initial.latitude, lng: initial.longitude }}
            defaultZoom={17}
            gestureHandling="greedy"
            disableDefaultUI
            clickableIcons={false}
            className="h-full w-full"
          />
          <CentreTracker onMove={(coords) => (centre.current = coords)} />
        </APIProvider>
        <svg viewBox="0 0 24 32" className="pointer-events-none absolute left-1/2 top-1/2 h-10 w-8 -translate-x-1/2 -translate-y-full drop-shadow" aria-hidden="true">
          <path d="M12 1C6 1 1.5 5.5 1.5 11.3 1.5 19 12 31 12 31s10.5-12 10.5-19.7C22.5 5.5 18 1 12 1z" fill="var(--color-sambal)" stroke="white" strokeWidth="2" />
          <circle cx="12" cy="11" r="3.6" fill="white" />
        </svg>
      </div>
      <div className="px-5 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
        <Button onClick={() => onConfirm(centre.current)}>Confirm this location</Button>
      </div>
    </div>
  )
}

/** Where the pin is: a still map with a marker, or the coordinates and a link when there's no Maps key. */
export function LocationPreview({ coords, name, height = 180 }: { coords: Coordinates; name: string; height?: number }) {
  const position = { lat: coords.latitude, lng: coords.longitude }
  if (!MAPS_KEY) {
    return (
      <div className="flex flex-col gap-1 rounded-row border-2 border-hairline bg-surface px-4 py-3">
        <p className="font-semibold tabular-nums">
          {coords.latitude.toFixed(5)}, {coords.longitude.toFixed(5)}
        </p>
        <a
          href={`https://www.google.com/maps/search/?api=1&query=${coords.latitude},${coords.longitude}`}
          target="_blank"
          rel="noreferrer"
          className="min-h-11 content-center font-semibold text-sambal-deep underline decoration-2 underline-offset-4"
        >
          Check it on Google Maps
        </a>
      </div>
    )
  }
  return (
    <div className="overflow-hidden rounded-row border-2 border-hairline" style={{ height }} role="img" aria-label="Map showing the pinned location">
      <APIProvider apiKey={MAPS_KEY}>
        <Map key={`${coords.latitude},${coords.longitude}`} defaultCenter={position} defaultZoom={17} gestureHandling="none" disableDefaultUI clickableIcons={false} className="pointer-events-none h-full w-full">
          <Marker position={position} title={name || 'Here'} />
        </Map>
      </APIProvider>
    </div>
  )
}

function CentreTracker({ onMove }: { onMove: (coords: Coordinates) => void }) {
  const map = useMap()
  useEffect(() => {
    if (!map) return
    const listener = map.addListener('idle', () => {
      const centre = map.getCenter()
      if (centre) onMove({ latitude: centre.lat(), longitude: centre.lng() })
    })
    return () => listener.remove()
  }, [map, onMove])
  return null
}
