import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { avatarSrc } from '../../api/account'
import { authApi } from '../../api/auth'
import { ApiError } from '../../api/client'
import { communityApi, type AmbassadorPickItem } from '../../api/community'
import { nearbyApi } from '../../api/nearby'
import type { User } from '../../api/types'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { priceLabel } from '../../lib/presentation'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { Sheet } from '../../ui/Sheet'
import { useContributionGate } from '../auth/ContributionGate'
import { AmbassadorBadge } from './PostRow'

/** Is this person the ambassador of the community they're looking at? */
export function isAmbassadorHere(user: User | null, communityName: string | null): boolean {
  return Boolean(user?.ambassadorOf && communityName && user.ambassadorOf.type === user.affiliationType && user.ambassadorOf.name === communityName)
}

function Crest({ type, size }: { type: 'university' | 'area'; size: number }) {
  return type === 'university' ? (
    <img src="/ambassador-crest.webp" alt="" width={size} height={size} className="shrink-0" style={{ width: size, height: size }} />
  ) : (
    <span className="relative shrink-0" style={{ width: size, height: size }}>
      <Mascot mood="wave" size={size} />
      <span aria-hidden="true" className="absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full bg-kunyit text-sm text-kicap">
        ★
      </span>
    </span>
  )
}

/** The community's ambassador picks, first thing in the feed. Ambassadors manage theirs here too. */
export function AmbassadorRail({ picks, community, onOpenPlace, onChanged }: { picks: AmbassadorPickItem[]; community: string; onOpenPlace: (id: number) => void; onChanged: () => void }) {
  const { user } = useSession()
  const mine = isAmbassadorHere(user, community)
  const [sharing, setSharing] = useState(false)
  if (picks.length === 0 && !mine) return null

  const names = [...new Set(picks.map((pick) => pick.ambassador.name))]
  return (
    <section aria-labelledby="picks-title">
      <div className="flex items-center gap-3">
        <Crest type={user?.affiliationType === 'area' ? 'area' : 'university'} size={56} />
        <div className="flex-1">
          <h2 id="picks-title" className="font-display text-2xl font-bold">
            Ambassador picks
          </h2>
          {picks.length > 0 && (
            <p className="text-sm text-kicap-soft">{names.length === 1 ? `Chosen by ${names[0]} for ${community}` : `Chosen by ${community}’s ambassadors`}</p>
          )}
        </div>
        {mine && (
          <button type="button" onClick={() => setSharing(true)} aria-label="Your ambassador card" className="press flex h-11 w-11 items-center justify-center rounded-full border-2 border-kicap bg-surface">
            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d="M12 3v12M7 8l5-5 5 5M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" />
            </svg>
          </button>
        )}
      </div>

      <ul className="-mx-5 mt-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-5 pb-2 [scrollbar-width:none] md:mx-0 md:px-0">
        {picks.length === 0 ? (
          <li className="flex min-h-44 w-60 shrink-0 snap-start flex-col justify-center rounded-card border-[1.5px] border-dashed border-kicap/30 p-4">
            <p className="font-display font-semibold">☆ Add your first pick</p>
            <p className="mt-1 text-sm text-kicap-soft">Open any place in Community or Nearby and tap Add to my picks. Members of your community will see it here.</p>
          </li>
        ) : (
          picks.map((pick) => <PickCard key={`${pick.id}-${pick.pickedAt}`} pick={pick} mine={mine} onOpen={() => onOpenPlace(pick.id)} onRemoved={onChanged} />)
        )}
      </ul>
      {mine && user && <ShareCardSheet open={sharing} onClose={() => setSharing(false)} user={user} />}
    </section>
  )
}

function PickCard({ pick, mine, onOpen, onRemoved }: { pick: AmbassadorPickItem; mine: boolean; onOpen: () => void; onRemoved: () => void }) {
  // No community photo: borrow the place's first Google photo (signed for 30 minutes).
  const { data } = useQuery({ queryKey: ['place', pick.id], queryFn: ({ signal }) => nearbyApi.details(pick.id, signal), enabled: !pick.photoUrl, staleTime: 25 * 60_000 })
  const photo = pick.photoUrl ?? data?.photos[0]?.url ?? null
  const kind = pick.cuisines[0]?.replace(/_/g, ' ') ?? pick.foodCategory?.replace(/_restaurant$/, '').replace(/_/g, ' ') ?? null
  const details = [kind && kind.charAt(0).toUpperCase() + kind.slice(1), priceLabel(pick.priceLevel), pick.distanceKm !== null && pick.distanceKm < 50 ? `${pick.distanceKm.toFixed(1)} km` : null].filter(Boolean)

  return (
    <li className="w-60 shrink-0 snap-start">
      <div className="relative overflow-hidden rounded-card border border-hairline bg-surface">
        <button type="button" onClick={onOpen} className="block w-full text-left">
          <div className="relative h-36 bg-cream">
            {photo ? (
              <img src={photo} alt="" loading="lazy" className="h-full w-full object-cover" />
            ) : (
              // No photo yet: a plate mark instead of an empty box.
              <span aria-hidden="true" className="flex h-full w-full items-center justify-center bg-kunyit/20 text-kicap/40">
                <svg viewBox="0 0 24 24" className="h-12 w-12" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                  <circle cx="12" cy="13" r="7" />
                  <circle cx="12" cy="13" r="3.5" />
                  <path d="M4 3v5M2.5 3v3.5a1.5 1.5 0 0 0 3 0V3M4 9.5V21M20 3c-1.4.8-2 2.6-2 4.5V11h2v10" />
                </svg>
              </span>
            )}
            {!pick.photoUrl && photo && <span className="absolute bottom-2 left-2 rounded-full bg-kicap/70 px-2 py-0.5 text-xs text-white">Google Maps</span>}
          </div>
          <div className="p-3">
            <p className="truncate font-display text-lg font-semibold">{pick.name}</p>
            <p className="truncate text-sm text-kicap-soft">{details.join(', ')}</p>
            <p className="mt-1 line-clamp-2 min-h-10 text-sm italic">{pick.note ? `“${pick.note}”` : ''}</p>
            <p className="mt-2 flex items-center gap-2 text-xs font-semibold">
              <img src={avatarSrc(pick.ambassador.avatarKey)} alt="" className="h-5 w-5 rounded-full bg-kunyit/25" />
              {pick.ambassador.name}
            </p>
          </div>
        </button>
        {mine && (
          <button
            type="button"
            onClick={() => void nearbyApi.removeAmbassadorPick(pick.id).then(onRemoved).catch(() => {})}
            className="absolute right-2 top-2 rounded-full bg-surface/90 px-3 py-1 text-xs font-semibold text-sambal-deep"
          >
            Remove from my picks
          </button>
        )}
      </div>
    </li>
  )
}

/** "Become an ambassador for X": the application form, or where an application stands. Open it through the contribution gate (accounts only). */
export function ApplySheet({ open, onClose, community }: { open: boolean; onClose: () => void; community: string }) {
  const { user, setUser } = useSession()
  const { gate, run } = useContributionGate('apply to be an ambassador')
  const queryClient = useQueryClient()
  const { data, isLoading, isError, refetch } = useQuery({ queryKey: ['ambassador-application'], queryFn: communityApi.application, enabled: open && Boolean(user && !user.isGuest) })
  const [reason, setReason] = useState('')
  const [instagram, setInstagram] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const application = data?.application

  // Approved but the session doesn't know yet: refresh it so the ambassador tools appear. Once
  // per application: if the role was since taken away, the refresh changes nothing, and asking
  // again on every session change looped.
  const refreshedFor = useRef<number | null>(null)
  useEffect(() => {
    if (application?.status !== 'approved' || user?.ambassadorOf || refreshedFor.current === application.id) return
    refreshedFor.current = application.id
    void authApi.me().then(({ user: fresh }) => setUser(fresh)).catch(() => {})
  }, [application, user?.ambassadorOf, setUser])

  function send() {
    run(async () => {
      setBusy(true)
      setError(null)
      try {
        const { application: created } = await communityApi.apply(reason.trim(), instagram.trim() || null)
        queryClient.setQueryData(['ambassador-application'], { application: created })
      } catch (caught) {
        setError(caught instanceof ApiError ? errorMessage(caught) : 'Couldn’t send it. Try again in a bit.')
      } finally {
        setBusy(false)
      }
    })
  }

  return (
    <>
      <Sheet open={open && !user?.isGuest} onClose={onClose} title="Become an ambassador">
        {isLoading && <p role="status">Checking your application…</p>}
        {isError && (
          <>
            <p>Couldn’t check your application.</p>
            <Button variant="secondary" onClick={() => void refetch()}>
              Try again
            </Button>
          </>
        )}
        {application?.status === 'pending' && (
          <>
            <h3 className="font-display text-xl font-bold">Application sent</h3>
            <p className="text-kicap-soft">The team will look at it soon, and you’ll get a notification when they do.</p>
            <Button onClick={onClose}>Got it</Button>
          </>
        )}
        {application?.status === 'approved' && (
          <>
            <h3 className="font-display text-xl font-bold">You’re the ambassador for {application.communityName ?? community}</h3>
            <p className="text-kicap-soft">Your ambassador tools are now in Community.</p>
            <Button onClick={onClose}>Got it</Button>
          </>
        )}
        {data && (!application || application.status === 'declined') && (
          <>
            <div className="flex items-center gap-3">
              <Mascot mood="excited" size={72} />
              <h3 className="font-display text-xl font-bold">Represent {community} on MakanApa</h3>
            </div>
            <ul className="space-y-1.5 rounded-row bg-cream px-4 py-3 text-sm">
              <li>★ Hand-pick the best places to eat around {community}</li>
              <li>★ Everyone in {community} sees your picks in Community</li>
              <li>★ Get your own ambassador card to share</li>
            </ul>
            {application?.status === 'declined' && (
              <div className="rounded-row bg-kunyit/20 px-4 py-3">
                <p className="font-semibold">Not this time</p>
                <p className="text-sm">{application.reviewNote ?? 'Your last application wasn’t approved, but you’re welcome to apply again.'}</p>
              </div>
            )}
            <label className="flex flex-col gap-1">
              <span className="font-display font-semibold">Why you?</span>
              <textarea
                value={reason}
                onChange={(event) => setReason(event.target.value.slice(0, 500))}
                rows={4}
                placeholder="How well do you know the food around here?"
                className="rounded-row border-2 border-hairline bg-cream p-3"
              />
              <span className="flex justify-between text-xs text-kicap-soft">
                <span>At least 20 characters.</span>
                <span>{reason.length}/500</span>
              </span>
            </label>
            <label className="flex flex-col gap-1">
              <span className="font-display font-semibold">Instagram (optional)</span>
              <input value={instagram} onChange={(event) => setInstagram(event.target.value.slice(0, 60))} placeholder="@handle" autoCapitalize="none" autoCorrect="off" className="min-h-12 rounded-row border-2 border-hairline bg-cream px-3" />
            </label>
            {error && <p role="alert" className="text-sambal-deep">{error}</p>}
            <Button onClick={send} disabled={reason.trim().length < 20} busy={busy}>
              Send application
            </Button>
          </>
        )}
        <Button variant="quiet" onClick={onClose}>
          Close
        </Button>
      </Sheet>
      {gate}
    </>
  )
}

type Format = 'story' | 'post'
const FORMATS: Record<Format, { width: number; height: number }> = { story: { width: 360, height: 640 }, post: { width: 360, height: 450 } }

/** The ambassador card as a PNG (1080 wide): story 1080×1920 or post 1080×1350. */
export async function renderAmbassadorCard(user: User, format: Format): Promise<Blob> {
  const { width, height } = FORMATS[format]
  const scale = 3
  const canvas = document.createElement('canvas')
  canvas.width = width * scale
  canvas.height = height * scale
  const context = canvas.getContext('2d')!
  context.scale(scale, scale)
  await document.fonts.load('700 34px Fredoka')

  const university = user.ambassadorOf?.type !== 'area'
  const art = new Image()
  art.src = university ? '/ambassador-crest.webp' : '/mascot/wave-640.webp'
  await art.decode()

  // Sambal frame, cream card with a thin kunyit line inside.
  context.fillStyle = '#e94b35'
  context.fillRect(0, 0, width, height)
  context.fillStyle = '#fff4dd'
  context.beginPath()
  context.roundRect(12, 12, width - 24, height - 24, 24)
  context.fill()
  context.strokeStyle = 'rgba(244, 185, 66, 0.65)'
  context.lineWidth = 1
  context.beginPath()
  context.roundRect(20, 20, width - 40, height - 40, 18)
  context.stroke()

  const story = format === 'story'
  const artSize = story ? 290 : 220
  const nameSize = story ? 34 : 28
  const blockHeight = artSize + 16 + nameSize * 1.2 + 16 + 48
  let y = (height - blockHeight) / 2

  // Soft kunyit glow behind the art.
  const glow = context.createRadialGradient(width / 2, y + artSize / 2, 0, width / 2, y + artSize / 2, artSize * 0.6)
  glow.addColorStop(0, 'rgba(244, 185, 66, 0.48)')
  glow.addColorStop(0.5, 'rgba(244, 185, 66, 0.22)')
  glow.addColorStop(1, 'rgba(244, 185, 66, 0)')
  context.fillStyle = glow
  context.fillRect(0, y - 40, width, artSize + 80)
  context.drawImage(art, (width - artSize) / 2, y, artSize, artSize)
  y += artSize + 16

  context.fillStyle = '#29201d'
  context.textAlign = 'center'
  context.textBaseline = 'top'
  let size = nameSize
  const name = user.name?.trim() || 'MakanApa ambassador'
  context.font = `700 ${size}px Fredoka`
  while (context.measureText(name).width > width - 56 && size > nameSize * 0.65) {
    size -= 1
    context.font = `700 ${size}px Fredoka`
  }
  context.fillText(name, width / 2, y)
  y += nameSize * 1.2 + 16

  const label = user.ambassadorOf?.name ?? ''
  context.font = '600 15px Fredoka'
  const pillWidth = Math.min(width - 56, context.measureText(label).width + 36)
  context.fillStyle = '#fffbf3'
  context.strokeStyle = '#f4b942'
  context.lineWidth = 1.25
  context.beginPath()
  context.roundRect((width - pillWidth) / 2, y, pillWidth, 40, 20)
  context.fill()
  context.stroke()
  context.fillStyle = '#29201d'
  context.textBaseline = 'middle'
  context.fillText(label, width / 2, y + 20)

  return new Promise((resolve, reject) => canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Could not draw the card'))), 'image/png'))
}

function ShareCardSheet({ open, onClose, user }: { open: boolean; onClose: () => void; user: User }) {
  const [format, setFormat] = useState<Format>('story')
  const [preview, setPreview] = useState<{ format: Format; url: string; blob: Blob } | null>(null)
  const [error, setError] = useState(false)

  const [attempt, setAttempt] = useState(0)
  useEffect(() => {
    if (!open) return
    let url: string | null = null
    let cancelled = false
    setError(false)
    renderAmbassadorCard(user, format)
      .then((blob) => {
        if (cancelled) return
        url = URL.createObjectURL(blob)
        setPreview({ format, url, blob })
        setError(false)
      })
      .catch(() => !cancelled && setError(true))
    return () => {
      cancelled = true
      if (url) {
        URL.revokeObjectURL(url)
        setPreview((current) => (current?.url === url ? null : current))
      }
    }
  }, [open, format, user, attempt])

  async function share() {
    if (!preview) return
    const file = new File([preview.blob], `makanapa-ambassador-${preview.format}.png`, { type: 'image/png' })
    // A file only, no caption: Instagram rejects shares that arrive with text.
    if (navigator.canShare?.({ files: [file] })) {
      await navigator.share({ files: [file] }).catch(() => {})
    } else {
      const link = document.createElement('a')
      link.href = preview.url
      link.download = file.name
      link.click()
    }
  }

  return (
    <Sheet open={open} onClose={onClose} title="Your ambassador card">
      <div role="radiogroup" aria-label="Card format" className="grid grid-cols-2 gap-2">
        {(['story', 'post'] as const).map((value) => (
          <button key={value} type="button" role="radio" aria-checked={format === value} onClick={() => setFormat(value)} className={`min-h-11 rounded-full font-display font-semibold ${format === value ? 'bg-kicap text-cream' : 'bg-kicap/5'}`}>
            {value === 'story' ? 'Story' : 'Post'}
          </button>
        ))}
      </div>
      {preview?.format === format ? (
        <img src={preview.url} alt={`Ambassador card: ${user.name ?? 'MakanApa ambassador'}, MakanApa ambassador for ${user.ambassadorOf?.name ?? ''}`} className="mx-auto max-h-[45dvh] rounded-[20px] shadow-lift" />
      ) : error ? (
        <Button variant="secondary" onClick={() => setAttempt((count) => count + 1)}>
          Try again
        </Button>
      ) : (
        <p role="status">Drawing your card…</p>
      )}
      <p className="text-center text-sm text-kicap-soft">Choose a size, then share your ambassador card.</p>
      <Button onClick={() => void share()} disabled={preview?.format !== format}>
        Share card
      </Button>
      <Button variant="quiet" onClick={onClose}>
        Done
      </Button>
    </Sheet>
  )
}

/** Once per account and role: the celebration when someone becomes an ambassador. */
export function AmbassadorWelcome() {
  const { user } = useSession()
  const marker = user?.ambassadorOf ? `${user.id}|${user.ambassadorOf.type}:${user.ambassadorOf.name}` : null
  const [seen, setSeen] = useState(() => {
    try {
      return localStorage.getItem('makanapa:ambassador-celebrated')
    } catch {
      return null
    }
  })
  if (!marker || seen === marker || !user?.ambassadorOf) return null

  function close() {
    try {
      localStorage.setItem('makanapa:ambassador-celebrated', marker!)
    } catch {
      // Without storage it shows again next visit; harmless.
    }
    setSeen(marker)
  }

  return (
    <div role="dialog" aria-modal="true" aria-labelledby="welcome-title" className="fixed inset-0 z-50 flex items-center justify-center bg-cream px-6">
      <div className="tape-in flex max-w-[540px] flex-col items-center gap-5 text-center">
        <Crest type={user.ambassadorOf.type} size={200} />
        <h1 id="welcome-title" className="font-display text-4xl font-bold">
          You’re an ambassador!
        </h1>
        <p className="text-lg text-kicap-soft">You now represent {user.ambassadorOf.name} on MakanApa. Your posts carry an Ambassador badge from today.</p>
        <AmbassadorBadge name={user.ambassadorOf.name} />
        <Button onClick={close} className="max-w-xs">
          Continue
        </Button>
      </div>
    </div>
  )
}
