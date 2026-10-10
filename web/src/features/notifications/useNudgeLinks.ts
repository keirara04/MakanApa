import { useEffect } from 'react'
import { useLocation, useNavigate } from 'react-router'
import { api } from '../../api/client'
import { nearbyApi, type NearbyPlace } from '../../api/nearby'

type NudgeEvent = 'opened' | 'place_opened' | 'makan_sini' | 'quick_pick_started'

export function recordNudge(nudgeId: string | number, event: NudgeEvent): void {
  void api(`me/nudges/${nudgeId}/events`, { method: 'POST', body: { event } }).catch(() => {})
}

/** A tapped mealtime push lands with ?nudge=ID: count it as opened, once, then tidy the URL. */
export function useNudgeOpened(): void {
  const { search, pathname } = useLocation()
  const navigate = useNavigate()
  useEffect(() => {
    const params = new URLSearchParams(search)
    const nudge = params.get('nudge')
    if (!nudge) return
    recordNudge(nudge, 'opened')
    params.delete('nudge')
    if (params.has('place')) params.set('fromNudge', nudge)
    navigate({ pathname, search: params.toString() ? `?${params}` : '' }, { replace: true })
  }, [search, pathname, navigate])
}

/** Nearby with ?place=ID (from a push or a shared link): open that place's sheet. */
export function usePlaceLink(open: (place: NearbyPlace) => void): void {
  const { search } = useLocation()
  const navigate = useNavigate()
  useEffect(() => {
    const params = new URLSearchParams(search)
    const id = Number(params.get('place'))
    if (!id) return
    const nudge = params.get('fromNudge')
    navigate({ pathname: '/nearby', search: '' }, { replace: true })
    nearbyApi
      .details(id)
      .then((details) => {
        open({
          id: details.id, name: details.name, latitude: details.latitude, longitude: details.longitude, rating: details.rating,
          priceLevel: details.priceLevel, openStatus: details.openStatus, halal: details.halal,
        })
        if (nudge) recordNudge(nudge, 'place_opened')
      })
      .catch(() => {})
  }, [search, navigate, open])
}
