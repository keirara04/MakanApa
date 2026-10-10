import { createContext, use, useCallback, useLayoutEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { decideApi, type Decision, type Recommendation, type SoloRequest, type TuneDirection, type TuneResponse } from '../../api/decide'
import { directionsUrl } from '../../lib/maps'
import { getStored, setStored } from '../../lib/storage'

/** What the thinking screen echoes back: "Nasi Kandar", "~RM20 per person", "Within 2 km". */
export interface AskedFor {
  mood: string
  budget: string
  distance: string
}

export type DecideStatus = 'idle' | 'thinking' | 'ready' | 'none' | 'error' | 'rerolling'

interface DecideState {
  status: DecideStatus
  request: SoloRequest | null
  asked: AskedFor | null
  decision: Decision | null
  pick: Recommendation | null
  error: unknown
  /** Name of the place just skipped with "Another one", for the why-not question. */
  skipped: string | null
  tunesUsed: TuneDirection[]
  /** Set when a tune found nothing better: offer to search wider. */
  widen: TuneResponse | null
  /** True for a pick fresh from a decide call (plays the thinking trace once). */
  fresh: boolean
}

interface DecideContextValue extends DecideState {
  decide: (request: SoloRequest, asked: AskedFor) => Promise<void>
  /** Run a saved-places pick through the same result screen machinery. */
  adopt: (decision: Decision, asked: AskedFor | null) => void
  retry: () => Promise<void>
  reroll: (options?: { exclude?: boolean }) => Promise<void>
  cancelReroll: () => void
  tune: (direction: TuneDirection) => Promise<void>
  choose: (restaurantId: number) => Promise<void>
  /** Opens directions (synchronously, so the browser allows the new tab), then records the accept. */
  accept: () => void
  reset: () => void
}

const MIN_THINKING_MS = 700
const initial: DecideState = { status: 'idle', request: null, asked: null, decision: null, pick: null, error: null, skipped: null, tunesUsed: [], widen: null, fresh: false }

const DecideContext = createContext<DecideContextValue | null>(null)

async function atLeast<T>(work: Promise<T>, ms: number): Promise<T> {
  const [result] = await Promise.all([work, new Promise((resolve) => setTimeout(resolve, ms))])
  return result
}

export function DecideProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<DecideState>(initial)
  // Latest state for the async actions, which outlive the render that created them.
  const current = useRef(state)
  useLayoutEffect(() => {
    current.current = state
  })
  // Bumped on cancel so a late reroll answer is ignored.
  const generation = useRef(0)

  const decide = useCallback(async (request: SoloRequest, asked: AskedFor) => {
    const run = ++generation.current
    setState({ ...initial, status: 'thinking', request, asked })
    try {
      const decision = await atLeast(decideApi.solo(request), MIN_THINKING_MS)
      if (run !== generation.current) return
      setState((state) => ({ ...state, decision, pick: decision.recommendation, status: decision.recommendation ? 'ready' : 'none', fresh: true }))
    } catch (error) {
      if (run === generation.current) setState((state) => ({ ...state, status: 'error', error }))
    }
  }, [])

  const adopt = useCallback((decision: Decision, asked: AskedFor | null) => {
    generation.current++
    setState({ ...initial, decision, asked, pick: decision.recommendation, status: decision.recommendation ? 'ready' : 'none' })
  }, [])

  const retry = useCallback(async () => {
    const { request, asked } = current.current
    if (request && asked) await decide(request, asked)
  }, [decide])

  const reroll = useCallback(async ({ exclude = false }: { exclude?: boolean } = {}) => {
    const { decision, pick } = current.current
    if (!decision || !pick) return
    if (exclude) {
      setStored('excluded', [...new Set([...(getStored('excluded') ?? []), pick.id])])
      setStored('saved', (getStored('saved') ?? []).filter((place) => place.id !== pick.id))
    }
    const run = ++generation.current
    setState((state) => ({ ...state, status: 'rerolling', widen: null }))
    try {
      const { recommendation } = await atLeast(decideApi.reroll(decision), MIN_THINKING_MS)
      if (run !== generation.current) return
      setState((state) => ({
        ...state,
        pick: recommendation,
        status: recommendation ? 'ready' : 'none',
        skipped: recommendation ? pick.name : null,
        fresh: false,
      }))
    } catch (error) {
      if (run === generation.current) setState((state) => ({ ...state, status: 'error', error }))
    }
  }, [])

  const cancelReroll = useCallback(() => {
    generation.current++
    setState((state) => ({ ...state, status: state.pick ? 'ready' : 'idle' }))
  }, [])

  const tune = useCallback(async (direction: TuneDirection) => {
    const { decision } = current.current
    if (!decision) return
    const response = await decideApi.tune(decision, direction)
    setState((state) => ({
      ...state,
      tunesUsed: [...state.tunesUsed, direction],
      pick: response.recommendation ?? state.pick,
      widen: response.recommendation ? null : response,
      fresh: false,
    }))
  }, [])

  const choose = useCallback(async (restaurantId: number) => {
    const { decision } = current.current
    if (!decision) return
    const { recommendation } = await decideApi.choose(decision, restaurantId)
    setState((state) => ({ ...state, pick: recommendation, skipped: null, fresh: false }))
  }, [])

  const accept = useCallback(() => {
    const { decision, pick } = current.current
    if (!decision || !pick) return
    window.open(directionsUrl(pick, getStored('mapsApp')), '_blank', 'noopener')

    void decideApi.interaction(decision, 'directions_opened').catch(() => {})
    void decideApi.accept(decision).catch(() => {})

    const recent = (getStored('recent') ?? []).filter((item) => item.id !== pick.id)
    setStored('recent', [
      {
        id: pick.id, name: pick.name, latitude: pick.latitude, longitude: pick.longitude, foodCategory: pick.foodCategory,
        priceLevel: pick.priceLevel, placeGoogleMapsUrl: pick.placeGoogleMapsUrl, at: Date.now(),
        source: current.current.request ? ('solo' as const) : ('saved' as const),
      },
      ...recent,
    ].slice(0, 5))

    const counted = getStored('nudgePicks') ?? []
    if (!counted.includes(decision.decisionId)) {
      setStored('nudgePicks', [...counted, decision.decisionId].slice(-20))
      // Every second accepted pick asks, later, what the place was like (iOS VibeFollowUp).
      const accepts = (getStored('acceptCount') ?? 0) + 1
      setStored('acceptCount', accepts)
      if (accepts % 2 === 0) {
        setStored('pendingVibe', { decisionId: decision.decisionId, clientToken: decision.clientToken, restaurantName: pick.name, acceptedAt: Date.now() })
      }
    }
  }, [])

  const reset = useCallback(() => {
    generation.current++
    setState(initial)
  }, [])

  const value = useMemo<DecideContextValue>(
    () => ({ ...state, decide, adopt, retry, reroll, cancelReroll, tune, choose, accept, reset }),
    [state, decide, adopt, retry, reroll, cancelReroll, tune, choose, accept, reset],
  )
  return <DecideContext value={value}>{children}</DecideContext>
}

export function useDecide(): DecideContextValue {
  const value = use(DecideContext)
  if (!value) throw new Error('useDecide must be used inside DecideProvider')
  return value
}
