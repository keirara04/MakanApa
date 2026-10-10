import { useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useLocation, useNavigate } from 'react-router'
import { api } from '../../api/client'
import { nearbyApi, type NearbyPlace, type OpenedFrom } from '../../api/nearby'

/** How a place sheet was opened, for "Eat here" (and the nudge it came from). */
export interface PlaceOrigin {
  from: OpenedFrom
  nudgeId?: string
}

type NudgeEvent = 'opened' | 'place_opened' | 'makan_sini' | 'quick_pick_started'

export function recordNudge(nudgeId: string | number, event: NudgeEvent): void {
  void api(`me/nudges/${nudgeId}/events`, { method: 'POST', body: { event } }).catch(() => {})
}

// Each push counts as opened once, even if this runs twice for it.
const counted = new Set<string>()

/** A tapped mealtime push lands with ?nudge=ID: count it as opened, once, then tidy the URL. */
export function useNudgeOpened(): void {
  const { search, pathname } = useLocation()
  const navigate = useNavigate()
  useEffect(() => {
    const params = new URLSearchParams(search)
    const nudge = params.get('nudge')
    if (!nudge) return
    if (!counted.has(nudge)) recordNudge(nudge, 'opened')
    counted.add(nudge)
    params.delete('nudge')
    if (params.has('place')) params.set('fromNudge', nudge)
    // A mealtime push that names no place asks for a one-tap pick (Home runs it).
    else params.set('quickPick', nudge)
    navigate({ pathname, search: params.toString() ? `?${params}` : '' }, { replace: true })
  }, [search, pathname, navigate])
}

/** Nearby with ?place=ID (from a push or a shared link): open that place's sheet. */
export function usePlaceLink(open: (place: NearbyPlace, search: null, origin: PlaceOrigin) => void): void {
  const { search } = useLocation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  useEffect(() => {
    const params = new URLSearchParams(search)
    const id = Number(params.get('place'))
    if (!id) return
    const nudge = params.get('fromNudge')
    navigate({ pathname: '/nearby', search: '' }, { replace: true })
    // Through the cache the place sheet reads, so its details aren't fetched a second time.
    queryClient
      .fetchQuery({ queryKey: ['place', id], queryFn: () => nearbyApi.details(id), staleTime: 10 * 60_000 })
      .then((details) => {
        open({
          id: details.id, name: details.name, latitude: details.latitude, longitude: details.longitude, rating: details.rating,
          priceLevel: details.priceLevel, openStatus: details.openStatus, halal: details.halal,
        }, null, nudge ? { from: 'nudge', nudgeId: nudge } : { from: 'share' })
        if (nudge) recordNudge(nudge, 'place_opened')
      })
      .catch(() => {})
  }, [search, navigate, open, queryClient])
}
