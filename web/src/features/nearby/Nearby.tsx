import { useCallback, useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router'
import { boxAround, centreOf, nearbyApi, type NearbyPlace, type Viewport } from '../../api/nearby'
import { errorMessage } from '../../lib/errors'
import { useLocation } from '../../lib/location'
import { getStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { useDecide } from '../decide/state'
import { AreaPanel } from './AreaPanel'
import { FilterRibbon } from './FilterRibbon'
import { MAPS_KEY, NearbyMap } from './NearbyMap'
import { PlaceSheet } from './PlaceSheet'
import { SearchPanel, type SearchContext } from './SearchPanel'
import { usePlaceLink, type PlaceOrigin } from '../notifications/useNudgeLinks'
import { useNearby } from './state'

const MIN_PICK_MS = 600

/**
 * Nearby: what's around, with filters, search, place details and Pick for me. With a Maps key
 * the map leads (beside the panel on wide screens, above it on phones); without one the list
 * stands on its own, loaded around where you are.
 */
export function Nearby() {
  const location = useLocation()
  const coords = location.state.status === 'ready' ? location.state.coords : null
  const nearby = useNearby()
  const { adopt } = useDecide()
  const navigate = useNavigate()
  const [searching, setSearching] = useState(false)
  const [open, setOpen] = useState<{ place: NearbyPlace; search: SearchContext | null; origin: PlaceOrigin | null } | null>(null)
  const [picking, setPicking] = useState(false)
  const [winnerId, setWinnerId] = useState<number | null>(null)
  const [pickError, setPickError] = useState<string | null>(null)

  // Without a map, the list is loaded around the person (one Google tile's worth).
  const { load } = nearby
  useEffect(() => {
    if (!MAPS_KEY && coords && !nearby.viewport) void load(boxAround(coords.latitude, coords.longitude))
  }, [coords, nearby.viewport, load])

  const matching = useMemo(() => new Set(nearby.filtered.map((place) => place.id)), [nearby.filtered])
  const centre = nearby.viewport ? centreOf(nearby.viewport) : coords

  const openPlace = useCallback((place: NearbyPlace, search: SearchContext | null = null, origin: PlaceOrigin | null = null) => {
    setWinnerId(null)
    setOpen({ place, search, origin })
  }, [])

  usePlaceLink(openPlace)

  const onLoadArea = useCallback((viewport: Viewport) => void load(viewport), [load])

  async function pick() {
    if (!nearby.viewport) return
    setPicking(true)
    setPickError(null)
    const started = Date.now()
    try {
      const origin = coords ?? centreOf(nearby.viewport)
      const decision =
        nearby.filtered.length === 0
          ? null
          : await nearbyApi.pick({ ...origin, viewport: nearby.viewport, visiblePlaceIds: nearby.filtered.map((place) => place.id), filters: nearby.filters, ignoreContext: getStored('ignoredContext') ?? [] })
      await new Promise((resolve) => setTimeout(resolve, Math.max(0, MIN_PICK_MS - (Date.now() - started))))
      if (decision?.recommendation) {
        setWinnerId(decision.recommendation.id)
        await new Promise((resolve) => setTimeout(resolve, 300))
      }
      adopt(decision ?? { decisionId: 0, clientToken: '', algorithmVersion: 'v1', recommendation: null }, null, 'nearby')
      navigate('/nearby/result')
    } catch (caught) {
      setPickError(errorMessage(caught))
    } finally {
      setPicking(false)
    }
  }

  const sheet = (
    <PlaceSheet
      place={open?.place ?? null}
      search={open?.search ?? null}
      origin={open?.origin ?? null}
      user={coords}
      onClose={() => setOpen(null)}
      onExclude={nearby.exclude}
      // The search panel stays open underneath, so going back is just closing the sheet.
      onBackToResults={open?.search ? () => setOpen(null) : null}
    />
  )

  // A place link (push or shared) still opens its sheet over this.
  if (!coords && location.state.status !== 'locating' && !nearby.viewport) {
    return (
      <section className="flex flex-1 flex-col items-center justify-center gap-4 text-center">
        <img src="/illustrations/location-map.svg" alt="" aria-hidden="true" width={140} height={140} />
        <h1 className="font-display text-3xl font-bold">Food around you</h1>
        {location.state.status === 'denied' ? (
          <p className="max-w-sm text-kicap-soft">
            Location is blocked for this site. Allow Location from the icon next to the address bar, then reload to see places nearby.
          </p>
        ) : (
          <>
            <p className="max-w-sm text-kicap-soft">MakanApa uses your location to show what’s around you. It’s never shown to other people.</p>
            <Button className="max-w-xs" onClick={() => void location.request()}>
              Turn on location
            </Button>
          </>
        )}
        {sheet}
      </section>
    )
  }

  const searchButton = (
    <button
      type="button"
      onClick={() => setSearching(true)}
      aria-label="Search places, food, or cuisine"
      className="press flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-kicap bg-surface"
    >
      <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
        <circle cx="11" cy="11" r="7" />
        <path d="m20 20-3.5-3.5" />
      </svg>
    </button>
  )

  return (
    <div className={`flex flex-1 flex-col gap-4 ${MAPS_KEY ? 'md:grid md:grid-cols-[minmax(0,1fr)_24rem] md:gap-6' : 'mx-auto w-full max-w-3xl'}`}>
      {MAPS_KEY && centre && (
        <div className="relative h-[45dvh] overflow-hidden rounded-card border-2 border-kicap md:sticky md:top-6 md:h-[calc(100dvh-6rem)]">
          <NearbyMap
            centre={centre}
            user={coords}
            places={nearby.places}
            matching={matching}
            loadedViewport={nearby.viewport}
            winnerId={winnerId}
            focusId={open?.place.id ?? null}
            onSelect={(place) => openPlace(place)}
            onLoadArea={onLoadArea}
          />
          {picking && <p className="absolute inset-x-0 bottom-3 mx-auto w-fit rounded-full bg-kicap px-4 py-2 font-display text-sm font-semibold text-cream">Thinking…</p>}
        </div>
      )}

      <div className="flex flex-col gap-4">
        {!MAPS_KEY && (
          <header>
            <h1 className="font-display text-4xl font-bold tracking-tight">Food around you</h1>
            <p className="mt-1 text-kicap-soft">Places within about a kilometre, from where you are now.</p>
          </header>
        )}

        {searching && centre ? (
          <SearchPanel
            centre={centre}
            onClose={() => setSearching(false)}
            onSelect={(place, context) => {
              nearby.addPlace(place)
              openPlace(place, context)
            }}
          />
        ) : (
          <>
            <FilterRibbon trailing={searchButton} />
            {nearby.error ? (
              <div role="alert" className="flex items-start gap-3 rounded-row bg-sambal-deep px-4 py-3 text-white">
                <p className="flex-1">{errorMessage(nearby.error)}</p>
                <button type="button" onClick={nearby.retry} className="min-h-11 px-2 font-display font-semibold underline">
                  Try again
                </button>
                <button type="button" aria-label="Dismiss" onClick={nearby.clearError} className="h-11 w-11">
                  ✕
                </button>
              </div>
            ) : null}
            {pickError && <p role="alert" className="text-sambal-deep">{pickError}</p>}
            <AreaPanel onOpen={(place) => openPlace(place)} onPick={() => void pick()} picking={picking} />
            {!nearby.summary && !nearby.loading && !nearby.error && (
              <div className="flex flex-col items-center gap-2 text-center">
                <Mascot mood="thinking" size={96} />
              </div>
            )}
          </>
        )}
      </div>

      {sheet}
    </div>
  )
}
