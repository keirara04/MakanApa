import { useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import { authApi } from '../../api/auth'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { getStored, setStored, type Stored } from '../../lib/storage'
import { Button } from '../../ui/Button'

const MAPS_APPS: { value: NonNullable<Stored['mapsApp']>; label: string }[] = [
  { value: 'google', label: 'Google Maps' },
  { value: 'waze', label: 'Waze' },
  { value: 'naver', label: 'Naver Map' },
]

/** Phase 1 settings. Profile editing, Selera, notifications and account deletion come in Phase 4. */
export function Settings() {
  const { user, setUser, logout } = useSession()
  const navigate = useNavigate()
  const [halalBusy, setHalalBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [mapsApp, setMapsApp] = useState(getStored('mapsApp') ?? 'google')

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
      <header className="flex items-center justify-between">
        <h1 className="font-display text-3xl font-bold tracking-tight md:text-4xl">Settings</h1>
      </header>

      <section className="sketch px-5 py-5" style={{ ['--sketch-radius' as string]: '20px' }}>
        {user.isGuest ? (
          <>
            <p className="font-display text-xl font-semibold">You’re using MakanApa without an account</p>
            <p className="mt-1 text-kicap-soft">Sign in to add places and post. Your picks, saves and taste profile come with you.</p>
            <Button className="mt-4 md:max-w-xs" onClick={() => navigate('/sign-in')}>
              Sign in or create an account
            </Button>
          </>
        ) : (
          <>
            <p className="font-display text-xl font-semibold">{user.name ?? 'Your account'}</p>
            {user.email && <p className="mt-0.5 text-kicap-soft">{user.email}</p>}
            {user.ambassadorOf && <p className="mt-2 text-sm font-semibold text-sambal-deep">Ambassador for {user.ambassadorOf.name}</p>}
          </>
        )}
      </section>

      <Group title="Preferences">
        <Row label="Hide non-halal places" detail="Places not yet verified still show, clearly marked.">
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
        </Row>
        <fieldset className="py-4">
          <legend className="font-display text-lg font-semibold">Directions open in</legend>
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
        {error && (
          <p role="alert" className="pb-3 text-sambal-deep">
            {error}
          </p>
        )}
      </Group>

      <Group title="Help and legal">
        <LinkRow href="https://trymakanapa.com/support">Support</LinkRow>
        <LinkRow href="https://trymakanapa.com/privacy">Privacy Policy</LinkRow>
        <LinkRow href="https://trymakanapa.com/terms">Terms of Use</LinkRow>
        <LinkRow href="https://trymakanapa.com/community-guidelines">Community Guidelines</LinkRow>
        <button
          type="button"
          className="flex min-h-13 w-full items-center py-3 text-left font-display text-lg font-semibold"
          onClick={() => {
            setStored('onboarded', false)
            navigate('/welcome')
          }}
        >
          Replay the intro
        </button>
      </Group>

      {!user.isGuest && (
        <Button
          variant="secondary"
          className="md:max-w-xs"
          onClick={() => {
            void logout().then(() => navigate('/sign-in', { replace: true }))
          }}
        >
          Log out
        </Button>
      )}
    </div>
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

function Row({ label, detail, children }: { label: string; detail?: string; children: ReactNode }) {
  return (
    <div className="flex items-center gap-4 py-4">
      <div className="flex-1">
        <p className="font-display text-lg font-semibold">{label}</p>
        {detail && <p className="text-sm text-kicap-soft">{detail}</p>}
      </div>
      {children}
    </div>
  )
}

function LinkRow({ href, children }: { href: string; children: ReactNode }) {
  return (
    <a href={href} target="_blank" rel="noreferrer" className="flex min-h-13 items-center py-3 font-display text-lg font-semibold">
      {children}
    </a>
  )
}
