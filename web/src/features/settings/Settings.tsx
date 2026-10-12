import { useQuery } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { Link, useNavigate } from 'react-router'
import { accountApi, avatarSrc, type Contributions } from '../../api/account'
import { authApi } from '../../api/auth'
import { useSession } from '../../auth/session'
import { ApiError } from '../../api/client'
import { errorMessage } from '../../lib/errors'
import { isInstalled } from '../../lib/install'
import { getStored, setStored, type Stored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { Sheet } from '../../ui/Sheet'
import { InstallGuide } from '../install/InstallGuide'

const MAPS_APPS: { value: NonNullable<Stored['mapsApp']>; label: string }[] = [
  { value: 'google', label: 'Google Maps' },
  { value: 'waze', label: 'Waze' },
  { value: 'naver', label: 'Naver Map' },
  { value: 'kakao', label: 'Kakao Map' },
]
const SUPPORT_EMAIL = 'hakeemiridza@gmail.com'

export function Settings() {
  const { user, setUser, logout } = useSession()
  const navigate = useNavigate()
  const [halalBusy, setHalalBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [mapsApp, setMapsApp] = useState(getStored('mapsApp') ?? 'google')
  const [sheet, setSheet] = useState<'about' | 'install' | 'logout' | 'delete' | null>(null)
  const [installed] = useState(isInstalled)
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
    <div className="flex flex-col gap-7 pt-2 md:max-w-xl">
      <h1 className="font-display text-3xl font-bold tracking-tight md:text-4xl">Settings</h1>

      {user.isGuest ? (
        <section className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-5 shadow-sm">
          <div className="flex items-start gap-4">
            <Mascot mood="wave" size={56} className="h-14 w-14 shrink-0" />
            <div>
              <h2 className="font-display text-xl font-bold">You’re using MakanApa as a guest</h2>
              <p className="mt-1 text-sm text-kicap-soft">Sign in to add places and post. Your picks, saves and taste profile come with you.</p>
            </div>
          </div>
          <Button className="min-h-12 text-lg" onClick={() => navigate('/sign-in')}>
            Create an account or sign in
          </Button>
        </section>
      ) : (
        <Link
          to="/settings/profile"
          aria-label="Edit your profile"
          className="press flex items-center gap-4 rounded-card border border-hairline bg-surface p-5 shadow-sm"
        >
          <img src={avatarSrc(user.avatarKey)} alt="" width={64} height={64} className="h-16 w-16 shrink-0 rounded-full border-[3px] border-cream bg-kunyit/25 object-cover" />
          <span className="flex min-w-0 flex-1 flex-col gap-1">
            <span className="font-display text-xl font-bold">{user.name?.trim() || 'Your profile'}</span>
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
        <NavRow to="/settings/selera" icon="selera" tone="sambal" title="Your Selera" detail="What I’ve learned about your taste" />
        <NavRow to="/settings/saved" icon="saved" tone="sambal" title="Saved places" detail="Places you kept" />
        <NavRow to="/community/mine" icon="mine" tone="sambal" title="My places" detail="Places you’ve added or edited" />
      </Group>

      <Group title="Preferences">
        <div className={ROW}>
          <RowBody icon="halal" tone="pandan" title="Hide non-halal" detail="Hides places known to be non-halal" />
          <button
            type="button"
            role="switch"
            aria-checked={user.halalPreference}
            aria-label="Hide non-halal places"
            disabled={halalBusy}
            onClick={() => void toggleHalal()}
            className={`relative h-8 w-14 shrink-0 rounded-full transition-colors motion-reduce:transition-none ${user.halalPreference ? 'bg-pandan' : 'bg-kicap/15'}`}
          >
            <span className={`absolute top-1 h-6 w-6 rounded-full bg-white shadow transition-[left] motion-reduce:transition-none ${user.halalPreference ? 'left-7' : 'left-1'}`} />
          </button>
        </div>
        <fieldset className="flex flex-col gap-3 px-4 py-3">
          <legend className="sr-only">Open directions in</legend>
          <div className="flex items-center gap-3">
            <RowBody icon="directions" tone="pandan" title="Open directions in" detail="Used by Let’s eat and Directions" />
          </div>
          <div className="flex gap-1 rounded-full bg-cream p-1">
            {MAPS_APPS.map((app) => (
              <label key={app.value} className="press flex-auto cursor-pointer">
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
                <span className="flex min-h-10 items-center justify-center rounded-full px-3 text-center font-display text-sm font-semibold whitespace-nowrap text-kicap-soft peer-checked:bg-surface peer-checked:text-kicap peer-checked:shadow-sm peer-focus-visible:outline-3 peer-focus-visible:outline-sambal">
                  {app.label}
                </span>
              </label>
            ))}
          </div>
        </fieldset>
        <NavRow to="/settings/notifications" icon="bell" tone="pandan" title="Notifications" detail="Choose what pings you" />
        {error && (
          <p role="alert" className="px-4 py-3 text-sambal-deep">
            {error}
          </p>
        )}
      </Group>

      <Group title="Privacy and safety">
        <NavRow to="/settings/blocked" icon="blocked" tone="kicap" title="Blocked people" detail="People you’ve hidden" />
        <LinkRow href="https://trymakanapa.com/privacy" icon="privacy" tone="kicap" title="Privacy Policy" />
        <LinkRow href="https://trymakanapa.com/terms" icon="terms" tone="kicap" title="Terms of Use" />
        <LinkRow href="https://trymakanapa.com/community-guidelines" icon="guidelines" tone="kicap" title="Community Guidelines" />
      </Group>

      <Group title="Help">
        {!installed && <ButtonRow icon="install" tone="kunyit" title="Add to your home screen" detail="Use MakanApa like an app" onClick={() => setSheet('install')} />}
        <ButtonRow icon="about" tone="kunyit" title="What is MakanApa?" onClick={() => setSheet('about')} />
        <ButtonRow
          icon="replay"
          tone="kunyit"
          title="Replay the intro"
          // Only a replay: the onboarded flag stays as is (it decides where a signed-out visit lands),
          // and finishing or closing it comes back here.
          onClick={() => navigate('/welcome', { state: { from: '/settings' } })}
        />
        <LinkRow href="https://trymakanapa.com/support" icon="help" tone="kunyit" title="Help and support" />
        <LinkRow href={`mailto:${SUPPORT_EMAIL}?subject=MakanApa%20support`} icon="mail" tone="kunyit" title="Contact and report a problem" detail={SUPPORT_EMAIL} />
      </Group>

      <Group title="Account">
        {!user.isGuest && <ButtonRow icon="logout" tone="kicap" title="Log out" onClick={() => setSheet('logout')} />}
        <ButtonRow icon="delete" tone="sambal" title="Delete account" danger onClick={() => setSheet('delete')} />
      </Group>

      <footer className="flex flex-col items-center gap-1 pb-4 text-center">
        <Mascot mood="wave" size={56} />
        <p className="font-hand text-2xl text-kicap-soft">Less thinking. More eating.</p>
        <p className="text-xs text-kicap-soft">MakanApa for the web</p>
      </footer>

      <Sheet open={sheet === 'about'} onClose={() => setSheet(null)} title="What is MakanApa?">
        <p>MakanApa helps you decide what to eat. Tell it your mood and budget, and it picks food nearby. Less thinking, more eating.</p>
        <Button onClick={() => setSheet(null)}>Got it</Button>
      </Sheet>

      <InstallGuide open={sheet === 'install'} onClose={() => setSheet(null)} />

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
      await deleteAccount(password === '' ? null : password)
      navigate('/welcome', { replace: true })
    } catch (caught) {
      setError(caught instanceof ApiError && caught.status === 401 ? 'That password doesn’t match. Try again.' : errorMessage(caught))
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

const ROW = 'flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left'

const TONES = {
  sambal: 'bg-sambal/12 text-sambal-deep',
  pandan: 'bg-pandan/15 text-pandan',
  kunyit: 'bg-kunyit/30 text-kicap',
  kicap: 'bg-kicap/8 text-kicap',
}
type Tone = keyof typeof TONES

/** One stroke icon per row, drawn on a 24px grid. */
const ICONS = {
  selera: <path d="M12 3c.6 4.6 4 8 8.6 8.6C16 12.2 12.6 15.6 12 20.2 11.4 15.6 8 12.2 3.4 11.6 8 11 11.4 7.6 12 3z" />,
  saved: <path d="M6 4h12v17l-6-4-6 4z" />,
  mine: (
    <>
      <path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z" />
      <path d="M12 6.5v6M9 9.5h6" />
    </>
  ),
  halal: (
    <>
      <path d="M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6z" />
      <path d="m9 12 2 2 4-4" />
    </>
  ),
  directions: <path d="M12 2 4 20l8-4 8 4z" transform="rotate(45 12 12)" />,
  bell: (
    <>
      <path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z" />
      <path d="M10 20.5a2 2 0 0 0 4 0" />
    </>
  ),
  blocked: (
    <>
      <circle cx="12" cy="12" r="8.5" />
      <path d="m6 6 12 12" />
    </>
  ),
  privacy: (
    <>
      <rect x="5" y="10.5" width="14" height="10" rx="2.5" />
      <path d="M8.5 10.5V7.5a3.5 3.5 0 0 1 7 0v3" />
    </>
  ),
  terms: (
    <>
      <path d="M7 3h7l4 4v14H7z" />
      <path d="M10 12h5M10 16h5" />
    </>
  ),
  guidelines: (
    <>
      <circle cx="9" cy="8" r="3" />
      <circle cx="17" cy="9" r="2.3" />
      <path d="M3.5 19.5c.8-3.4 3-5.2 5.5-5.2s4.7 1.8 5.5 5.2M15.5 14.4c2.4-.2 4.2 1.3 5 4.1" />
    </>
  ),
  install: (
    <>
      <rect x="6.5" y="2.5" width="11" height="19" rx="2.5" />
      <path d="M12 7.5v6M9.5 11 12 13.5 14.5 11M10.5 18.5h3" />
    </>
  ),
  about: (
    <>
      <circle cx="12" cy="12" r="8.5" />
      <path d="M12 11v5.5M12 7.6v.1" />
    </>
  ),
  replay: (
    <>
      <path d="M4 12a8 8 0 1 0 2.3-5.7" />
      <path d="M4 4v4h4" />
    </>
  ),
  help: (
    <>
      <circle cx="12" cy="12" r="8.5" />
      <path d="M9.7 9.5a2.4 2.4 0 1 1 3.4 2.2c-.7.3-1.1.9-1.1 1.6v.4M12 16.6v.1" />
    </>
  ),
  mail: (
    <>
      <rect x="3.5" y="5.5" width="17" height="13" rx="2.5" />
      <path d="m4.5 7 7.5 6 7.5-6" />
    </>
  ),
  logout: (
    <>
      <path d="M14 4h4.5v16H14" />
      <path d="M10 8l-4 4 4 4M6 12h9" />
    </>
  ),
  delete: (
    <>
      <path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13" />
    </>
  ),
}
type IconName = keyof typeof ICONS

function Group({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section>
      <h2 className="px-1 font-display text-sm font-semibold text-kicap-soft">{title}</h2>
      <div className="mt-2 divide-y divide-hairline overflow-hidden rounded-card border border-hairline bg-surface shadow-sm">{children}</div>
    </section>
  )
}

function RowBody({ icon, tone, title, detail, danger = false }: { icon: IconName; tone: Tone; title: string; detail?: string; danger?: boolean }) {
  return (
    <>
      <span aria-hidden="true" className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-[11px] ${TONES[tone]}`}>
        <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          {ICONS[icon]}
        </svg>
      </span>
      <span className="min-w-0 flex-1">
        <span className={`block font-display text-base font-semibold ${danger ? 'text-sambal-deep' : ''}`}>{title}</span>
        {detail && <span className="block text-sm [overflow-wrap:anywhere] text-kicap-soft">{detail}</span>}
      </span>
    </>
  )
}

type RowProps = { icon: IconName; tone: Tone; title: string; detail?: string }

function NavRow({ to, ...row }: RowProps & { to: string }) {
  return (
    <Link to={to} className={`${ROW} transition-colors hover:bg-cream/60 active:bg-cream motion-reduce:transition-none`}>
      <RowBody {...row} />
      <Chevron />
    </Link>
  )
}

/** Leaves the app (a website or the mail app), so it says so. */
function LinkRow({ href, ...row }: RowProps & { href: string }) {
  return (
    <a href={href} target="_blank" rel="noreferrer" className={`${ROW} transition-colors hover:bg-cream/60 active:bg-cream motion-reduce:transition-none`}>
      <RowBody {...row} />
      <svg viewBox="0 0 24 24" className="h-4 w-4 shrink-0 text-kicap-soft" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M14 5h5v5M19 5l-8 8M17 14v5H5V7h5" />
      </svg>
      <span className="sr-only">(opens outside MakanApa)</span>
    </a>
  )
}

function ButtonRow({ onClick, danger = false, ...row }: RowProps & { onClick: () => void; danger?: boolean }) {
  return (
    <button type="button" onClick={onClick} className={`${ROW} transition-colors hover:bg-cream/60 active:bg-cream motion-reduce:transition-none`}>
      <RowBody {...row} danger={danger} />
    </button>
  )
}
