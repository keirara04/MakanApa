import { useEffect, useRef, useState } from 'react'
import { Navigate, useNavigate } from 'react-router'
import { decideApi } from '../../api/decide'
import { ApiError } from '../../api/client'
import { useSession } from '../../auth/session'
import { getPosition } from '../../lib/location'
import { getStored, useStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { useDecide } from './state'

const SHUFFLE_MS = 1400

/**
 * "Pick from my saved": the saved places as a fanned deck that shuffles while MakanApa picks,
 * then the pick opens on the normal result screen (where Another one reshuffles).
 */
export function SavedPick() {
  const saved = useStored('saved') ?? []
  const { user } = useSession()
  const { adopt } = useDecide()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const started = useRef(false)

  useEffect(() => {
    if (started.current || saved.length < 2) return
    started.current = true
    void (async () => {
      // Without location, the middle of the saved places stands in (as on iOS).
      const coords = await getPosition().catch(() => ({
        latitude: saved.reduce((sum, place) => sum + place.latitude, 0) / saved.length,
        longitude: saved.reduce((sum, place) => sum + place.longitude, 0) / saved.length,
      }))
      try {
        const [decision] = await Promise.all([
          decideApi.saved({
            ...coords,
            savedPlaceIds: saved.map((place) => place.id),
            halal: user?.halalPreference ?? getStored('halalOnly') ?? false,
            ignoreContext: getStored('ignoredContext') ?? [],
          }),
          new Promise((resolve) => setTimeout(resolve, SHUFFLE_MS)),
        ])
        // No "Why this?" echo for saved picks: the person didn't ask for a mood, budget or distance.
        adopt(decision, null)
        navigate('/decide/result', { replace: true })
      } catch (caught) {
        setError(
          caught instanceof ApiError && caught.status === 429
            ? 'That’s a lot of shuffling. Try again in a minute.'
            : 'Couldn’t pick right now. Check your connection and try again.',
        )
      }
    })()
  }, [saved, user, adopt, navigate])

  if (saved.length < 2) return <Navigate to="/" replace />

  const deck = saved.slice(0, 6)
  return (
    <section className="flex flex-1 flex-col items-center justify-center gap-10 text-center">
      <div className="relative h-56 w-44" aria-hidden="true">
        {deck.map((place, index) => (
          <div
            key={place.id}
            className="deck-card absolute inset-0 flex items-end rounded-card border-2 border-kicap bg-surface p-4 shadow-lift"
            style={{ ['--i' as string]: index, ['--n' as string]: deck.length }}
          >
            <span className="text-left font-display text-lg font-semibold leading-tight text-kicap-soft">{place.name}</span>
          </div>
        ))}
      </div>
      <div>
        <h1 className="font-display text-3xl font-bold tracking-tight">{error ? 'Couldn’t shuffle' : 'Shuffling your saved places…'}</h1>
        <p className="mt-2 text-kicap-soft">
          In the deck: {deck.slice(0, 2).map((place) => place.name).join(', ')}
          {saved.length > 2 ? ` and ${saved.length - 2} more` : ''}
        </p>
      </div>
      {error && (
        <div className="flex w-full max-w-xs flex-col items-center gap-2">
          <Mascot mood="sad" size={96} />
          <p role="alert">{error}</p>
          <Button variant="secondary" onClick={() => navigate('/')}>
            Back home
          </Button>
        </div>
      )}
    </section>
  )
}
