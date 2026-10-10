import { useQuery } from '@tanstack/react-query'
import { nearbyApi } from '../../api/nearby'
import { errorMessage } from '../../lib/errors'
import { useLocation } from '../../lib/location'
import { getStored, setStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Sheet } from '../../ui/Sheet'
import { PlaceSheet } from './PlaceSheet'

/**
 * The place sheet opened from anywhere that only knows a place's id (a tagged post, trending,
 * ambassador picks). Details load first, from the same cache the sheet uses.
 */
export function PlaceById({ id, onClose }: { id: number | null; onClose: () => void }) {
  const location = useLocation()
  const { data, error } = useQuery({
    queryKey: ['place', id],
    queryFn: ({ signal }) => nearbyApi.details(id!, signal),
    enabled: id !== null,
    staleTime: 10 * 60_000,
  })

  const place =
    id !== null && data
      ? { id: data.id, name: data.name, latitude: data.latitude, longitude: data.longitude, rating: data.rating, priceLevel: data.priceLevel, openStatus: data.openStatus, halal: data.halal }
      : null

  if (id !== null && error && !data) {
    return (
      <Sheet open onClose={onClose} title="Couldn’t open this place">
        <p>{errorMessage(error)}</p>
        <Button onClick={onClose}>Close</Button>
      </Sheet>
    )
  }

  return (
    <PlaceSheet
      place={place}
      search={null}
      origin={{ from: 'community' }}
      user={location.state.status === 'ready' ? location.state.coords : null}
      onClose={onClose}
      onExclude={(placeId) => {
        setStored('excluded', [...new Set([...(getStored('excluded') ?? []), placeId])])
        setStored('saved', (getStored('saved') ?? []).filter((item) => item.id !== placeId))
      }}
      onBackToResults={null}
    />
  )
}
