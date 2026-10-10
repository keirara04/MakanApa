import { useEffect, useState } from 'react'
import { communityApi, type Post, type TaggablePlace } from '../../api/community'
import { ApiError } from '../../api/client'
import { errorMessage } from '../../lib/errors'
import { useLocation } from '../../lib/location'
import { Button } from '../../ui/Button'
import { Sheet } from '../../ui/Sheet'

const MAX = 280

/** Characters the way the server counts them (code points), so emoji count as one. */
export function length(text: string): number {
  return Array.from(text.trim()).length
}

/** A new post (with an optional tagged place) or a reply. Callers run the contribution gate first. */
export function Composer({ open, parent, onClose, onPosted }: { open: boolean; parent: Post | null; onClose: () => void; onPosted: (post: Post) => void }) {
  const [text, setText] = useState('')
  const [place, setPlace] = useState<TaggablePlace | null>(null)
  const [tagging, setTagging] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const count = length(text)
  const left = MAX - count

  function reset() {
    setText('')
    setPlace(null)
    setError(null)
  }

  async function post() {
    setBusy(true)
    setError(null)
    try {
      const { post: created } = await communityApi.create({ body: text.trim(), restaurantId: parent ? null : place?.id, parentId: parent?.id ?? null })
      reset()
      onPosted(created)
      onClose()
    } catch (caught) {
      setError(caught instanceof ApiError ? errorMessage(caught) : 'Couldn’t post that right now. Try again in a bit.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <Sheet open={open && !tagging} onClose={() => !tagging && !text.trim() && onClose()} title={parent ? 'Reply' : 'New post'}>
        {parent && (
          <div className="border-l-2 border-hairline pl-3">
            <p className="text-sm font-semibold">Replying to {parent.author.name}</p>
            <p className="line-clamp-3 text-sm text-kicap-soft">{parent.body}</p>
          </div>
        )}
        <label>
          <span className="sr-only">{parent ? 'Your reply' : 'Your post'}</span>
          <textarea
            autoFocus
            value={text}
            onChange={(event) => setText(event.target.value)}
            rows={5}
            placeholder={parent ? 'Write a reply…' : 'Share a thought… best teh tarik? new spot?'}
            className="min-h-32 w-full rounded-row border-2 border-hairline bg-cream p-3 text-lg placeholder:text-kicap-soft focus:border-kicap focus:outline-none"
          />
        </label>
        {!parent &&
          (place ? (
            <span className="inline-flex min-h-11 items-center gap-2 self-start rounded-full border-2 border-hairline bg-surface pl-3 pr-1 font-semibold">
              {place.name}
              <button type="button" aria-label="Remove tagged place" onClick={() => setPlace(null)} className="h-9 w-9 text-kicap-soft">
                ✕
              </button>
            </span>
          ) : (
            <button type="button" onClick={() => setTagging(true)} className="min-h-11 self-start rounded-full bg-sambal/10 px-4 font-semibold text-sambal-deep">
              Tag a place
            </button>
          ))}
        {error && <p role="alert" className="text-sambal-deep">{error}</p>}
        <div className="flex items-center justify-between gap-3 text-sm">
          <p className="text-kicap-soft">Keep it friendly and about food. No links, ads or personal attacks.</p>
          <span aria-live="polite" className={`shrink-0 font-semibold tabular-nums ${left < 0 ? 'text-sambal-deep' : count > 260 ? 'text-kicap' : 'text-kicap-soft'}`}>
            {left}
          </span>
        </div>
        <Button onClick={() => void post()} disabled={count === 0 || left < 0} busy={busy}>
          {parent ? 'Reply' : 'Post'}
        </Button>
        <Button
          variant="quiet"
          onClick={() => {
            reset()
            onClose()
          }}
        >
          Cancel
        </Button>
      </Sheet>
      <TagPicker
        open={open && tagging}
        onClose={() => setTagging(false)}
        onPick={(picked) => {
          setPlace(picked)
          setTagging(false)
        }}
      />
    </>
  )
}

function TagPicker({ open, onClose, onPick }: { open: boolean; onClose: () => void; onPick: (place: TaggablePlace) => void }) {
  const location = useLocation()
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<TaggablePlace[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const trimmed = query.trim()
    if (trimmed.length < 2) {
      setResults(null)
      return
    }
    const controller = new AbortController()
    const coords = location.state.status === 'ready' ? location.state.coords : null
    const timer = setTimeout(() => {
      communityApi
        .searchPlaces(trimmed, coords, controller.signal)
        .then((response) => setResults(response.existing))
        .catch((caught) => !controller.signal.aborted && setError(errorMessage(caught)))
    }, 400)
    return () => {
      clearTimeout(timer)
      controller.abort()
    }
  }, [query, location.state])

  return (
    <Sheet open={open} onClose={onClose} title="Tag a place">
      <input
        type="search"
        value={query}
        onChange={(event) => setQuery(event.target.value)}
        placeholder="Search places on MakanApa"
        aria-label="Search places on MakanApa"
        className="min-h-12 rounded-full border-2 border-hairline bg-cream px-5 [&::-webkit-search-cancel-button]:appearance-none"
      />
      {error && <p role="alert">{error}</p>}
      {results?.length === 0 && <p className="text-kicap-soft">No places on MakanApa match that yet.</p>}
      <ul className="max-h-[45dvh] divide-y divide-hairline overflow-y-auto">
        {results?.map((place) => (
          <li key={place.id}>
            <button type="button" onClick={() => onPick(place)} className="flex min-h-14 w-full flex-col justify-center py-2 text-left">
              <span className="font-semibold">{place.name}</span>
              <span className="text-sm text-kicap-soft">{place.foodCategory?.replace(/_/g, ' ') ?? place.address}</span>
            </button>
          </li>
        ))}
      </ul>
      <Button variant="quiet" onClick={onClose}>
        Cancel
      </Button>
    </Sheet>
  )
}
