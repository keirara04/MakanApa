import { Suspense, useEffect, type ReactNode } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import { avatarSrc } from '../../api/account'
import { api } from '../../api/client'
import { useSession } from '../../auth/session'
import { useOnline } from '../../lib/install'
import { MAPS_KEY } from '../../lib/maps'
import { installationId } from '../../lib/storage'
import { AmbassadorWelcome } from '../community/Ambassador'
import { useNudgeOpened } from '../notifications/useNudgeLinks'

const SECTIONS: { to: string; label: string; icon: ReactNode }[] = [
  { to: '/', label: 'Decide', icon: <path d="M12 3c.6 4.6 4 8 8.6 8.6C16 12.2 12.6 15.6 12 20.2 11.4 15.6 8 12.2 3.4 11.6 8 11 11.4 7.6 12 3z" /> },
  { to: '/nearby', label: 'Nearby', icon: <><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" /></> },
  { to: '/community', label: 'Community', icon: <><circle cx="9" cy="8" r="3.2" /><circle cx="17" cy="9" r="2.5" /><path d="M3 20c.8-3.6 3.2-5.5 6-5.5s5.2 1.9 6 5.5M15.5 14.6c2.6-.3 4.6 1.3 5.5 4.4" /></> },
]

/**
 * Signed-in frame. Phones get the iOS layout: a top bar and the three tabs at the bottom.
 * From tablet width the tabs move into a left sidebar with settings at its foot.
 */
export function AppShell() {
  const { user } = useSession()
  const { pathname } = useLocation()
  useAppSessions()
  useNudgeOpened()
  useWorkerNavigation()
  // Decide covers its whole flow (/decide, /decide/result), not just the home screen.
  // Nearby with a map is full screen on phones, as on iOS: no top bar, no page scroll.
  const fullBleed = pathname === '/nearby' && Boolean(MAPS_KEY)
  const active = (to: string) => (to === '/' ? pathname === '/' || pathname.startsWith('/decide') : pathname.startsWith(to))

  return (
    <div className="min-h-dvh md:flex">
      <aside className="sticky top-0 hidden h-dvh w-60 shrink-0 flex-col border-r-2 border-hairline bg-surface/60 px-4 py-8 md:flex lg:w-64">
        <Wordmark className="px-3" />
        <nav aria-label="Main" className="mt-10 flex flex-col gap-1">
          {SECTIONS.map((section) => (
            <Link
              key={section.to}
              to={section.to}
              aria-current={active(section.to) ? 'page' : undefined}
              className={`press flex min-h-12 items-center gap-3 rounded-row px-3 font-display text-lg font-semibold ${active(section.to) ? 'bg-cream text-sambal-deep' : 'text-kicap-soft hover:text-kicap'}`}
            >
              <Icon>{section.icon}</Icon>
              {section.label}
            </Link>
          ))}
        </nav>
        <NavLink
          to="/settings"
          className={({ isActive }) =>
            `press mt-auto flex min-h-12 items-center gap-3 rounded-row px-3 font-display text-lg font-semibold ${isActive ? 'bg-cream text-sambal-deep' : 'text-kicap-soft hover:text-kicap'}`
          }
        >
          <Avatar avatarKey={user?.avatarKey} initial={initial(user?.name)} small />
          Settings
        </NavLink>
      </aside>

      <div className={`flex min-h-dvh min-w-0 flex-1 flex-col ${fullBleed ? 'max-md:h-dvh max-md:overflow-hidden' : ''}`}>
        <header className={`${fullBleed ? 'hidden' : 'flex'} items-center justify-between px-5 pb-2 pt-[max(1rem,env(safe-area-inset-top))] md:hidden`}>
          <Wordmark />
          <Link to="/settings" aria-label="Settings" className="press">
            <Avatar avatarKey={user?.avatarKey} initial={initial(user?.name)} />
          </Link>
        </header>

        <AmbassadorWelcome />
        <Offline />
        <main className={`mx-auto flex w-full flex-1 flex-col px-5 pb-28 md:px-10 md:pb-14 md:pt-12 ${pathname === '/nearby' ? 'max-w-6xl' : 'max-w-3xl'} ${fullBleed ? 'max-md:min-h-0 max-md:px-0 max-md:pb-(--tab-bar)' : ''}`}>
          <Suspense
            fallback={
              <p role="status" className="pt-6 text-kicap-soft">
                Loading…
              </p>
            }
          >
            <Outlet />
          </Suspense>
        </main>
      </div>

      <nav
        aria-label="Main"
        className="fixed inset-x-0 bottom-0 z-20 flex justify-around border-t-2 border-hairline bg-surface/95 px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur md:hidden"
      >
        {SECTIONS.map((section) => (
          <Link
            key={section.to}
            to={section.to}
            aria-current={active(section.to) ? 'page' : undefined}
            className={`press flex min-h-14 min-w-20 flex-col items-center justify-center gap-0.5 rounded-row font-display text-sm font-semibold ${active(section.to) ? 'text-sambal-deep' : 'text-kicap-soft'}`}
          >
            <Icon>{section.icon}</Icon>
            {section.label}
          </Link>
        ))}
      </nav>
    </div>
  )
}

function Wordmark({ className = '' }: { className?: string }) {
  return (
    <p className={`font-display text-2xl font-bold tracking-tight ${className}`}>
      Makan<span className="text-sambal">Apa?</span>
    </p>
  )
}

function Icon({ children }: { children: ReactNode }) {
  return (
    <svg viewBox="0 0 24 24" className="h-6 w-6 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {children}
    </svg>
  )
}

function initial(name: string | null | undefined): string | null {
  return name?.trim()?.[0]?.toUpperCase() ?? null
}

/** The character the person picked, else their initial, else a plain person icon. */
function Avatar({ avatarKey, initial, small = false }: { avatarKey?: string | null; initial: string | null; small?: boolean }) {
  if (avatarKey) {
    return <img src={avatarSrc(avatarKey)} alt="" aria-hidden="true" className={`shrink-0 rounded-full border-2 border-kicap bg-kunyit/25 object-cover ${small ? 'h-8 w-8' : 'h-11 w-11'}`} />
  }
  return (
    <span
      aria-hidden="true"
      className={`flex shrink-0 items-center justify-center rounded-full border-2 border-kicap bg-surface font-display font-bold text-kicap ${small ? 'h-8 w-8 text-sm' : 'h-11 w-11 text-lg'}`}
    >
      {initial ?? (
        <svg viewBox="0 0 24 24" className={small ? 'h-4 w-4' : 'h-5 w-5'} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <circle cx="12" cy="8" r="4" />
          <path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" />
        </svg>
      )}
    </span>
  )
}

/**
 * Offline: the app itself still opens (it's cached), and saved places and recent picks work;
 * anything that asks MakanApa needs the connection back.
 */
function Offline() {
  const online = useOnline()
  if (online) return null
  return (
    <p role="status" className="mx-5 mt-2 rounded-row bg-kicap px-4 py-3 text-cream md:mx-10 md:mt-6">
      You’re offline. Saved places and recent picks still work. Picks, Nearby and the community need a connection.
    </p>
  )
}

/** A tapped notification when the worker can't move this tab itself: it asks the page to. */
function useWorkerNavigation() {
  const navigate = useNavigate()
  useEffect(() => {
    const worker = navigator.serviceWorker
    if (!worker) return
    const onMessage = (event: MessageEvent) => {
      if (event.data?.type !== 'NAVIGATE' || typeof event.data.url !== 'string') return
      const url = new URL(event.data.url, location.origin)
      if (url.origin === location.origin) navigate(url.pathname + url.search)
    }
    worker.addEventListener('message', onMessage)
    return () => worker.removeEventListener('message', onMessage)
  }, [navigate])
}

/** POST app-sessions/start when the app is in front, /end when it goes to the background, like iOS. */
function useAppSessions() {
  useEffect(() => {
    // The session being started, so hiding the tab before start answers still ends it.
    let current: Promise<number | null> | null = null

    const start = () => {
      current = api<{ sessionId: number }>('app-sessions/start', { method: 'POST', body: { installationId: installationId(), platform: 'web' } })
        .then((response) => response.sessionId)
        .catch(() => null)
    }
    // A plain request: the tab is only hidden, still alive. (A cross-origin keepalive request
    // with a token never reaches the API in Chromium.) Closing the tab may lose it; the
    // server keeps a session without an end.
    const end = () => {
      const ending = current
      current = null
      void ending?.then((sessionId) => sessionId !== null && api(`app-sessions/${sessionId}/end`, { method: 'POST' }).catch(() => {}))
    }
    const onVisibility = () => (document.visibilityState === 'visible' ? start() : end())

    start()
    document.addEventListener('visibilitychange', onVisibility)
    return () => {
      document.removeEventListener('visibilitychange', onVisibility)
      end()
    }
  }, [])
}
