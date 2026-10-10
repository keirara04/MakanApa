import { useEffect } from 'react'
import { Link, NavLink, Outlet } from 'react-router'
import { api } from '../../api/client'
import { useSession } from '../../auth/session'
import { installationId } from '../../lib/storage'

/** Signed-in frame: the three iOS tabs at the bottom, settings from the avatar at the top. */
export function AppShell() {
  const { user } = useSession()
  useAppSessions()

  return (
    <div className="mx-auto flex min-h-dvh w-full max-w-[480px] flex-col">
      <header className="flex items-center justify-between px-5 pb-2 pt-[max(1rem,env(safe-area-inset-top))]">
        <p className="font-display text-2xl font-bold tracking-tight">
          Makan<span className="text-sambal">Apa?</span>
        </p>
        <Link
          to="/settings"
          aria-label="Settings"
          className="press flex h-11 w-11 items-center justify-center rounded-full border-2 border-kicap bg-surface font-display text-lg font-bold"
        >
          {user?.name?.trim()?.[0]?.toUpperCase() ?? (
            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
              <circle cx="12" cy="8" r="4" />
              <path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" />
            </svg>
          )}
        </Link>
      </header>

      <main className="flex flex-1 flex-col px-5 pb-28">
        <Outlet />
      </main>

      <nav
        aria-label="Main"
        className="fixed inset-x-0 bottom-0 z-20 mx-auto flex w-full max-w-[480px] justify-around border-t-2 border-hairline bg-surface/95 px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur"
      >
        <Tab to="/" label="Decide" icon={<path d="M12 3c.6 4.6 4 8 8.6 8.6C16 12.2 12.6 15.6 12 20.2 11.4 15.6 8 12.2 3.4 11.6 8 11 11.4 7.6 12 3z" />} />
        <Tab to="/nearby" label="Nearby" icon={<><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" /></>} />
        <Tab to="/community" label="Community" icon={<><circle cx="9" cy="8" r="3.2" /><circle cx="17" cy="9" r="2.5" /><path d="M3 20c.8-3.6 3.2-5.5 6-5.5s5.2 1.9 6 5.5M15.5 14.6c2.6-.3 4.6 1.3 5.5 4.4" /></>} />
      </nav>
    </div>
  )
}

function Tab({ to, label, icon }: { to: string; label: string; icon: React.ReactNode }) {
  return (
    <NavLink
      to={to}
      end
      className={({ isActive }) =>
        `press flex min-h-14 min-w-20 flex-col items-center justify-center gap-0.5 rounded-row font-display text-sm font-semibold ${isActive ? 'text-sambal-deep' : 'text-kicap-soft'}`
      }
    >
      <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        {icon}
      </svg>
      {label}
    </NavLink>
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
