import { useEffect, type ReactNode } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router'
import { api } from '../../api/client'
import { useSession } from '../../auth/session'
import { installationId } from '../../lib/storage'

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
  // Decide covers its whole flow (/decide, /decide/result), not just the home screen.
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
          <Avatar initial={initial(user?.name)} small />
          Settings
        </NavLink>
      </aside>

      <div className="flex min-h-dvh min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between px-5 pb-2 pt-[max(1rem,env(safe-area-inset-top))] md:hidden">
          <Wordmark />
          <Link to="/settings" aria-label="Settings" className="press">
            <Avatar initial={initial(user?.name)} />
          </Link>
        </header>

        <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col px-5 pb-28 md:px-10 md:pb-14 md:pt-12">
          <Outlet />
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

function Avatar({ initial, small = false }: { initial: string | null; small?: boolean }) {
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

/** POST app-sessions/start when the app is in front, /end when it goes to the background, like iOS. */
function useAppSessions() {
  useEffect(() => {
    let sessionId: number | null = null

    const start = () => {
      api<{ sessionId: number }>('app-sessions/start', { method: 'POST', body: { installationId: installationId(), platform: 'web' } })
        .then((response) => (sessionId = response.sessionId))
        .catch(() => {})
    }
    const end = () => {
      if (sessionId === null) return
      void api(`app-sessions/${sessionId}/end`, { method: 'POST', keepalive: true }).catch(() => {})
      sessionId = null
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
