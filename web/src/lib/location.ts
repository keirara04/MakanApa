import { useCallback, useEffect, useState } from 'react'

export interface Coordinates {
  latitude: number
  longitude: number
}

export type LocationState =
  | { status: 'unknown' } // not asked yet
  | { status: 'locating' }
  | { status: 'ready'; coords: Coordinates }
  | { status: 'denied' }
  | { status: 'unavailable' } // no GPS fix, or the browser has no geolocation

/** One-shot fix at about 100 m accuracy, like the iOS app's when-in-use requestLocation(). */
export function getPosition(): Promise<Coordinates> {
  return new Promise((resolve, reject) => {
    if (!('geolocation' in navigator)) {
      reject(new GeolocationUnavailable())
      return
    }
    navigator.geolocation.getCurrentPosition(
      (position) => resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude }),
      reject,
      { enableHighAccuracy: false, maximumAge: 60_000, timeout: 15_000 },
    )
  })
}

class GeolocationUnavailable extends Error {}

/**
 * Location for the screens that need it. Asks on mount only when the browser already has
 * permission (so it never pops a prompt by itself); `request()` asks explicitly. Refreshes when
 * the app comes back to the foreground.
 */
export function useLocation(): { state: LocationState; request: () => Promise<void> } {
  const [state, setState] = useState<LocationState>({ status: 'unknown' })

  const request = useCallback(async () => {
    setState((current) => (current.status === 'ready' ? current : { status: 'locating' }))
    try {
      setState({ status: 'ready', coords: await getPosition() })
    } catch (error) {
      const denied = error instanceof GeolocationPositionError && error.code === error.PERMISSION_DENIED
      setState({ status: denied ? 'denied' : 'unavailable' })
    }
  }, [])

  useEffect(() => {
    let cancelled = false
    navigator.permissions
      ?.query({ name: 'geolocation' })
      .then((permission) => {
        if (cancelled) return
        if (permission.state === 'granted') void request()
        if (permission.state === 'denied') setState({ status: 'denied' })
      })
      .catch(() => {})

    const onVisible = () => {
      if (document.visibilityState === 'visible') {
        setState((current) => {
          if (current.status === 'ready') void request()
          return current
        })
      }
    }
    document.addEventListener('visibilitychange', onVisible)
    return () => {
      cancelled = true
      document.removeEventListener('visibilitychange', onVisible)
    }
  }, [request])

  return { state, request }
}
