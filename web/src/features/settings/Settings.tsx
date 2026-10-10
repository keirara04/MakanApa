import { useQuery } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { Link, useNavigate } from 'react-router'
import { accountApi, avatarSrc, type Contributions } from '../../api/account'
import { authApi } from '../../api/auth'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { getStored, setStored, type Stored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { Sheet } from '../../ui/Sheet'

const MAPS_APPS: { value: NonNullable<Stored['mapsApp']>; label: string }[] = [
  { value: 'google', label: 'Google Maps' },
  { value: 'waze', label: 'Waze' },
  { value: 'naver', label: 'Naver Map' },
]
const SUPPORT_EMAIL = 'hakeemiridza@gmail.com'

export function Settings() {
  const { user, setUser, logout } = useSession()
  const navigate = useNavigate()
  const [halalBusy, setHalalBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [mapsApp, setMapsApp] = useState(getStored('mapsApp') ?? 'google')
  const [sheet, setSheet] = useState<'about' | 'logout' | 'delete' | null>(null)
  const { data: contributions } = useQuery({ queryKey: ['contributions', user?.id], queryFn: accountApi.contributions, enabled: Boolean(user && !user.isGuest) })

  if (!user) return null

  async function toggleHalal() {
    setHalalBusy(true)
    setError(null)
    try {
      const { user: updated } = await authApi.updateProfile({ halalPreference: !user!.halalPreference })
      setUser(updated)
      setStored('halalOnly', updated.halalPreference)
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setHalalBusy(false)
    }
  }

  return (
    <div className="flex flex-col gap-8 pt-2 md:max-w-xl">
      <h1 className="font-display text-3xl font-bold tracking-tight md:text-4xl">Settings</h1>

      {user.isGuest ? (
        <Link to="/sign-in" className="sketch flex items-center gap-4 px-5 py-5" style={{ ['--sketch-radius' as string]: '20px' }}>
          <Mascot mood="wave" size={64} />
          <span className="flex-1">
            <span className="block font-display text-xl font-semibold">Using MakanApa as a guest</span>
            <span className="text-sm text-kicap-soft">Sign in to add places and post. Your picks, saves and taste profile come with you.</span>
          </span>
          <Chevron />
        </Link>
      ) : (
        <Link to="/settings/profile" className="sketch flex items-center gap-4 px-5 py-5" style={{ ['--sketch-radius' as string]: '20px' }} aria-label="Edit your profile">
          <img src={avatarSrc(user.avatarKey)} alt="" width={64} height={64} className="h-16 w-16 shrink-0 rounded-full border-[3px] border-white bg-kunyit/25 object-cover" />
          <span className="flex min-w-0 flex-1 flex-col gap-1">
            <span className="font-display text-xl font-semibold">{user.name?.trim() || 'Your profile'}</span>
            {user.email && <span className="truncate text-sm text-kicap-soft">{user.email}</span>}
            <span className="flex flex-wrap gap-1.5">
              {(user.university ?? user.area) && <Pill tone="sambal">{user.university ?? user.area}</Pill>}
              {user.ambassadorOf && <Pill tone="kunyit">★ Ambassador, {user.ambassadorOf.name}</Pill>}
              {contributions?.trustedContributor && <Pill tone="pandan">Trusted contributor</Pill>}
            </span>
            {contributions && <ContributionLine contributions={contributions} />}
          </span>
          <Chevron />
        </Link>
      )}

      <Group title="Your MakanApa">
        <NavRow to="/settings/selera" title="Your Selera" detail="What I’ve learned about your taste" />
        <NavRow to="/settings/saved" title="Saved places" detail="Places you kept" />
      </Group>

      <Group title="Preferences">
        <div className="flex items-center gap-4 py-4">
          <div className="flex-1">
            <p className="font-display text-lg font-semibold">Hide non-halal</p>
            <p className="text-sm text-kicap-soft">Hides places known to be non-halal</p>
          </div>
          <button
            type="button"
            role="switch"
            aria-checked={user.halalPreference}
            aria-label="Hide non-halal places"
            disabled={halalBusy}
            onClick={() => void toggleHalal()}
            className={`relative h-8 w-14 shrink-0 rounded-full transition-colors ${user.halalPreference ? 'bg-pandan' : 'bg-hairline'}`}
          >
            <span className={`absolute top-1 h-6 w-6 rounded-full bg-white shadow transition-[left] ${user.halalPreference ? 'left-7' : 'left-1'}`} />
          </button>
        </div>
        <fieldset className="py-4">
          <legend className="font-display text-lg font-semibold">Open directions in</legend>
          <p className="text-sm text-kicap-soft">Used by Let’s eat and Directions</p>
          <div className="mt-3 flex flex-wrap gap-2">
            {MAPS_APPS.map((app) => (
              <label key={app.value} className="press cursor-pointer">
                <input
                  type="radio"
                  name="mapsApp"
                  value={app.value}
                  checked={mapsApp === app.value}
                  onChange={() => {
                    setMapsApp(app.value)
                    setStored('mapsApp', app.value)
                  }}
                  className="peer sr-only"
                />
                <span className="inline-flex min-h-11 items-center rounded-full border-2 border-hairline px-4 font-display font-semibold text-kicap-soft peer-checked:border-kicap peer-checked:bg-surface peer-checked:text-kicap peer-focus-visible:outline-3 peer-focus-visible:outline-sambal">
                  {app.label}
                </span>
              </label>
            ))}
          </div>
        </fieldset>
        {error && <p role="alert" className="pb-3 text-sambal-deep">{error}</p>}
      </Group>

      <Group title="Privacy and safety">
        <NavRow to="/settings/blocked" title="Blocked people" detail="People you’ve hidden" />
        <LinkRow href="https://trymakanapa.com/privacy" title="Privacy Policy" />
        <LinkRow href="https://trymakanapa.com/terms" title="Terms of Use" />
        <LinkRow href="https://trymakanapa.com/community-guidelines" title="Community Guidelines" />
      </Group>

      <Group title="Help">
        <ButtonRow title="What is MakanApa?" onClick={() => setSheet('about')} />
        <ButtonRow
          title="Replay the intro"
          onClick={() => {
            setStored('onboarded', false)
            navigate('/welcome')
          }}
        />
        <LinkRow href="https://trymakanapa.com/support" title="Help and support" />
        <LinkRow href={`mailto:${SUPPORT_EMAIL}?subject=MakanApa%20support`} title="Contact and report a problem" detail={SUPPORT_EMAIL} />
      </Group>

      <div className="flex flex-col items-start gap-2">
        {!user.isGuest && (
          <Button variant="secondary" className="md:max-w-xs" onClick={() => setSheet('logout')}>
            Log out
          </Button>
        )}
        <button type="button" onClick={() => setSheet('delete')} className="min-h-11 text-sm font-semibold text-kicap-soft underline">
          Delete account
        </button>
      </div>

      <footer className="flex flex-col items-center gap-1 pb-4 text-center">
        <Mascot mood="wave" size={72} />
        <p className="font-hand text-2xl text-kicap-soft">Less thinking. More eating.</p>
        <p className="text-xs text-kicap-soft">MakanApa for the web</p>
      </footer>

      <Sheet open={sheet === 'about'} onClose={() => setSheet(null)} title="What is MakanApa?">
        <p>MakanApa helps you decide what to eat. Tell it your mood and budget, and it picks food nearby. Less thinking, more eating.</p>
        <Button onClick={() => setSheet(null)}>Got it</Button>
      </Sheet>

      <Sheet open={sheet === 'logout'} onClose={() => setSheet(null)} title="Log out of MakanApa?">
        <p className="text-kicap-soft">Your saved places stay on this device. Sign in again to pick up where you left off.</p>
        <Button onClick={() => void logout().then(() => navigate('/sign-in', { replace: true }))}>Log out</Button>
        <Button variant="quiet" onClick={() => setSheet(null)}>
          Cancel
        </Button>
      </Sheet>

      <DeleteAccount open={sheet === 'delete'} onClose={() => setSheet(null)} needsPassword={!user.isGuest} />
    </div>
  )
}

function DeleteAccount({ open, onClose, needsPassword }: { open: boolean; onClose: () => void; needsPassword: boolean }) {
  const { deleteAccount } = useSession()
  const navigate = useNavigate()
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function confirm() {
    setBusy(true)
    setError(null)
    try {
      await deleteAccount(password.trim() || null)
      navigate('/welcome', { replace: true })
    } catch {
      setError('Couldn’t delete your account. Check your password and try again.')
      setBusy(false)
    }
  }

  return (
    <Sheet open={open} onClose={() => !busy && onClose()} title="Delete your account?">
      <p>
        This permanently deletes your account, profile, community posts, photos and taste profile. Places you added, your past picks and saves stay in
        MakanApa without your name. This can’t be undone.
      </p>
      {needsPassword && (
        <label className="flex flex-col gap-1.5">
          <span className="font-display font-semibold">Password</span>
          <input
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            onKeyDown={(event) => event.key === 'Enter' && void confirm()}
            className="min-h-13 rounded-row border-2 border-hairline bg-cream px-4"
          />
          <span className="text-sm text-kicap-soft">Leave blank if you sign in with Google.</span>
        </label>
      )}
      {error && <p role="alert" className="text-sambal-deep">{error}</p>}
      <button
        type="button"
        onClick={() => void confirm()}
        disabled={busy}
        className="press min-h-14 rounded-full bg-kicap px-6 font-display text-xl font-semibold text-cream disabled:opacity-60"
      >
        {busy ? 'Deleting…' : 'Delete my account'}
      </button>
      <Button variant="quiet" onClick={onClose} disabled={busy}>
        Cancel
      </Button>
    </Sheet>
  )
}

function ContributionLine({ contributions }: { contributions: Contributions }) {
  const plural = (count: number, one: string, many: string) => `${count} ${count === 1 ? one : many}`
  const parts = [
    contributions.placesAdded && plural(contributions.placesAdded, 'place', 'places'),
    contributions.halalVerified && plural(contributions.halalVerified, 'halal check', 'halal checks'),
    contributions.photosAdded && plural(contributions.photosAdded, 'photo', 'photos'),
    contributions.posts && plural(contributions.posts, 'post', 'posts'),
  ].filter(Boolean)
  return parts.length ? <span className="text-sm text-kicap-soft">{parts.join(', ')}</span> : null
}

function Pill({ tone, children }: { tone: 'sambal' | 'kunyit' | 'pandan'; children: ReactNode }) {
  const tones = { sambal: 'bg-sambal/10 text-sambal-deep', kunyit: 'bg-kunyit/30 text-kicap', pandan: 'bg-pandan/15 text-pandan' }
  return <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${tones[tone]}`}>{children}</span>
}

export function Chevron() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0 text-kicap-soft" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
      <path d="m9 6 6 6-6 6" />
    </svg>
  )
}

function Group({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section>
      <h2 className="font-display text-base font-semibold text-kicap-soft">{title}</h2>
      <div className="mt-2 divide-y-2 divide-hairline border-y-2 border-hairline">{children}</div>
    </section>
  )
}

function NavRow({ to, title, detail }: { to: string; title: string; detail: string }) {
  return (
    <Link to={to} className="flex min-h-16 items-center gap-3 py-3">
      <span className="flex-1">
        <span className="block font-display text-lg font-semibold">{title}</span>
        <span className="text-sm text-kicap-soft">{detail}</span>
      </span>
      <Chevron />
    </Link>
  )
}

function LinkRow({ href, title, detail }: { href: string; title: string; detail?: string }) {
  return (
    <a href={href} target="_blank" rel="noreferrer" className="flex min-h-13 flex-col justify-center py-3">
      <span className="font-display text-lg font-semibold">{title}</span>
      {detail && <span className="text-sm text-kicap-soft">{detail}</span>}
    </a>
  )
}

function ButtonRow({ title, onClick }: { title: string; onClick: () => void }) {
  return (
    <button type="button" onClick={onClick} className="flex min-h-13 w-full items-center py-3 text-left font-display text-lg font-semibold">
      {title}
    </button>
  )
}
