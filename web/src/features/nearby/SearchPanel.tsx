import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router'
import { nearbyApi, type NearbyPlace, type SearchResponse, type SearchResult } from '../../api/nearby'
import { errorMessage } from '../../lib/errors'
import { getStored, setStored, useStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { HalalBadge } from '../decide/PickParts'
import { useNearby } from './state'

const TRY = ['nasi lemak', 'mamak', 'coffee', 'chicken rice']
const RADIUS_KM = 6

export interface SearchContext {
  query: string
  radiusKm: number
  source: string
  /** Address and category from the search row, shown before details load. */
  result: SearchResult
}

function remember(query: string) {
  const recent = (getStored('recentSearches') ?? []).filter((item) => item.toLowerCase() !== query.toLowerCase())
  setStored('recentSearches', [query, ...recent].slice(0, 5))
}

/** Search a dish or a place around where the map is (or where you are). */
export function SearchPanel({ centre, onClose, onSelect }: {
  centre: { latitude: number; longitude: number }
  onClose: () => void
  onSelect: (place: NearbyPlace, context: SearchContext) => void
}) {
  const { filters } = useNearby()
  const excluded = useStored('excluded') ?? []
  const recent = useStored('recentSearches') ?? []
  const [text, setText] = useState('')
  const navigate = useNavigate()
  const [run, setRun] = useState<{ query: string; radiusKm: number; includeGoogle: boolean } | null>(null)
  const [response, setResponse] = useState<SearchResponse | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const [resolving, setResolving] = useState<string | null>(null)
  const input = useRef<HTMLInputElement>(null)
  const [placeholder] = useState(() => TRY[Math.floor(Math.random() * TRY.length)])

  useEffect(() => input.current?.focus(), [])

  // Type-ahead: 2+ characters, 300 ms after the last keystroke.
  useEffect(() => {
    const query = text.trim()
    if (query.length < 2) {
      setRun(null)
      setResponse(null)
      return
    }
    const timer = setTimeout(() => setRun({ query, radiusKm: RADIUS_KM, includeGoogle: false }), 300)
    return () => clearTimeout(timer)
  }, [text])

  useEffect(() => {
    if (!run) return
    const controller = new AbortController()
    setLoading(true)
    setError(null)
    nearbyApi
      .search({ ...run, ...centre, halal: filters.halal }, controller.signal)
      .then(setResponse)
      .catch((caught) => {
        if (!(caught instanceof DOMException && caught.name === 'AbortError')) setError(errorMessage(caught))
      })
      .finally(() => !controller.signal.aborted && setLoading(false))
    return () => controller.abort()
  }, [run, centre, filters.halal])

  async function choose(result: SearchResult) {
    if (!run || !response) return
    remember(run.query)
    let place = result
    try {
      if (result.provenance === 'google_fallback' && result.googlePlaceId) {
        setResolving(result.googlePlaceId)
        place = (await nearbyApi.resolve(result.googlePlaceId)).restaurant
      }
    } catch (caught) {
      setError(errorMessage(caught))
      return
    } finally {
      setResolving(null)
    }
    if (place.id === null) return
    onSelect(
      { id: place.id, name: place.name, rating: place.rating, priceLevel: place.priceLevel, latitude: place.latitude, longitude: place.longitude, openStatus: place.openStatus, halal: place.halal },
      { query: run.query, radiusKm: response.meta.radiusKm, source: response.meta.source, result: place },
    )
  }

  const results = (response?.results ?? []).filter((result) => result.id === null || !excluded.includes(result.id))
  const radius = (km: number) => `${Number(km.toFixed(1))} km`

  return (
    <section className="flex flex-col gap-4" aria-label="Search places">
      <div className="flex items-center gap-2 rounded-full border-2 border-kicap bg-surface pl-4 pr-1">
        <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0 text-kicap-soft" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7" />
          <path d="m20 20-3.5-3.5" />
        </svg>
        <input
          ref={input}
          type="search"
          value={text}
          onChange={(event) => setText(event.target.value)}
          onKeyDown={(event) => event.key === 'Escape' && onClose()}
          placeholder={`Search “${placeholder}”`}
          aria-label="Search places, food, or cuisine"
          maxLength={100}
          className="min-h-12 flex-1 bg-transparent text-lg placeholder:text-kicap-soft focus:outline-none [&::-webkit-search-cancel-button]:appearance-none"
        />
        <button type="button" onClick={onClose} aria-label="Close search" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-kicap-soft">
          ✕
        </button>
      </div>

      <div aria-live="polite">
        {!run && (
          <div className="flex flex-col gap-4">
            {recent.length > 0 && (
              <div>
                <h2 className="text-sm font-semibold text-kicap-soft">Recent</h2>
                <ul>
                  {recent.map((query) => (
                    <li key={query} className="flex items-center">
                      <button type="button" onClick={() => setText(query)} className="flex min-h-11 flex-1 items-center text-left">
                        {query}
                      </button>
                      <button
                        type="button"
                        aria-label={`Remove ${query} from recent searches`}
                        onClick={() => setStored('recentSearches', recent.filter((item) => item !== query))}
                        className="h-11 w-11 text-kicap-soft"
                      >
                        ✕
                      </button>
                    </li>
                  ))}
                </ul>
              </div>
            )}
            <div>
              <h2 className="text-sm font-semibold text-kicap-soft">Try</h2>
              <div className="mt-2 flex flex-wrap gap-2">
                {TRY.map((query) => (
                  <button key={query} type="button" onClick={() => setText(query)} className="press min-h-10 rounded-full border-2 border-hairline bg-surface px-4 font-semibold">
                    {query}
                  </button>
                ))}
              </div>
            </div>
          </div>
        )}

        {run && loading && !response && (
          <ul aria-label="Searching" className="flex flex-col gap-2">
            {[0, 1, 2, 3].map((index) => (
              <li key={index} className="h-20 animate-pulse rounded-row bg-hairline/60" />
            ))}
          </ul>
        )}

        {run && error && (
          <div className="flex flex-col items-start gap-2">
            <p role="alert">{error}</p>
            <Button variant="secondary" className="max-w-xs" onClick={() => setRun({ ...run })}>
              Try again
            </Button>
          </div>
        )}

        {run && response && results.length === 0 && !error && (
          <div className="flex flex-col gap-3">
            <p className="font-display text-xl font-semibold">
              No “{run.query}” within {radius(response.meta.radiusKm)}.
            </p>
            {response.meta.widerRadiusKm && (
              <Button className="max-w-xs" onClick={() => setRun({ ...run, radiusKm: response.meta.widerRadiusKm! })}>
                Search within {radius(response.meta.widerRadiusKm)}
              </Button>
            )}
            <Button variant="secondary" className="max-w-xs" onClick={() => navigate(`/community/add?q=${encodeURIComponent(run.query)}`, { state: { from: '/nearby' } })}>
              Add this place
            </Button>
            {response.meta.googleAvailable && (
              <Button variant="secondary" className="max-w-xs" onClick={() => setRun({ ...run, includeGoogle: true })}>
                Search Google for “{run.query}”
              </Button>
            )}
            {response.suggestions.length > 0 && (
              <div>
                <h2 className="text-sm font-semibold text-kicap-soft">Maybe try</h2>
                <div className="mt-2 flex flex-wrap gap-2">
                  {response.suggestions.map((suggestion) => (
                    <button key={suggestion} type="button" onClick={() => setText(suggestion)} className="press min-h-10 rounded-full border-2 border-hairline bg-surface px-4 font-semibold">
                      {suggestion}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}

        {run && response && results.length > 0 && (
          <div>
            <p className="flex flex-wrap items-center gap-x-3 text-sm text-kicap-soft">
              {results.length} {results.length === 1 ? 'result' : 'results'} within {radius(response.meta.radiusKm)}
              {response.meta.widerRadiusKm && (
                <button type="button" className="min-h-11 font-semibold text-sambal-deep underline" onClick={() => setRun({ ...run, radiusKm: response.meta.widerRadiusKm! })}>
                  Wider ({radius(response.meta.widerRadiusKm)})
                </button>
              )}
            </p>
            <ul className="mt-1 divide-y-2 divide-hairline">
              {results.map((result, index) => {
                const firstOfGroup = result.groupKey && result.groupSize > 1 && results.findIndex((item) => item.groupKey === result.groupKey) === index
                return (
                  <li key={result.id ?? result.googlePlaceId ?? index}>
                    {firstOfGroup && <p className="pt-3 text-xs font-semibold text-kicap-soft">{result.name}, {result.groupSize} nearby</p>}
                    <button
                      type="button"
                      onClick={() => void choose(result)}
                      disabled={resolving !== null}
                      aria-busy={resolving === result.googlePlaceId || undefined}
                      className="flex w-full items-start gap-3 py-3 text-left"
                    >
                      <span className="flex-1">
                        <span className="block font-semibold">{highlight(result.name, run.query)}</span>
                        <span className="block text-sm text-kicap-soft">
                          {[result.address ?? result.category ?? result.cuisine, result.distanceKm !== null ? `${result.distanceKm.toFixed(1)} km` : null].filter(Boolean).join(', ')}
                        </span>
                        <span className="mt-1 flex flex-wrap items-center gap-2 text-sm">
                          {result.openStatus === 'open' && <span className="font-semibold text-pandan">{result.closesAt ? `Open, closes ${result.closesAt}` : 'Open'}</span>}
                          {result.openStatus === 'closed' && <span className="font-semibold text-sambal-deep">Closed</span>}
                          {result.halal.status !== 'unknown' && <HalalBadge halal={result.halal} />}
                          {result.isCommunityFind && <span className="font-semibold text-pandan">Community find</span>}
                        </span>
                      </span>
                      {result.rating !== null && <span className="font-display font-semibold">★ {result.rating.toFixed(1)}</span>}
                    </button>
                  </li>
                )
              })}
            </ul>
            {response.meta.googleAvailable && !run.includeGoogle && (
              <Button variant="quiet" className="-ml-3" onClick={() => setRun({ ...run, includeGoogle: true })}>
                More nearby places: search Google
              </Button>
            )}
          </div>
        )}
      </div>
    </section>
  )
}

/** Bolds the first match of the query in the name, ignoring case and accents. */
function highlight(name: string, query: string) {
  const fold = (value: string) => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
  const at = fold(name).indexOf(fold(query))
  if (at < 0) return name
  return (
    <>
      {name.slice(0, at)}
      <strong className="font-extrabold">{name.slice(at, at + query.length)}</strong>
      {name.slice(at + query.length)}
    </>
  )
}
