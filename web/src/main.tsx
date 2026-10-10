import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router'
import { useRegisterSW } from 'virtual:pwa-register/react'
import { SessionProvider, useSession } from './auth/session'
import { SignIn } from './features/auth/SignIn'
import { DecideHome } from './features/decide/Home'
import { Preferences } from './features/decide/Preferences'
import { Result } from './features/decide/Result'
import { SavedPick } from './features/decide/SavedPick'
import { DecideProvider } from './features/decide/state'
import { Nearby } from './features/nearby/Nearby'
import { NearbyProvider } from './features/nearby/state'
import { Onboarding } from './features/onboarding/Onboarding'
import { Settings } from './features/settings/Settings'
import { AppShell } from './features/shell/AppShell'
import { ComingSoon } from './features/shell/ComingSoon'
import './index.css'
import { getStored } from './lib/storage'
import { Button } from './ui/Button'
import { Mascot } from './ui/Mascot'
import { Screen } from './ui/Screen'
import { SketchFilters } from './ui/SketchFilters'

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: false } },
})

function AppRoutes() {
  const { session, retry } = useSession()

  if (session.status === 'loading') {
    return (
      <Screen className="items-center justify-center text-center">
        <Mascot mood="thinking" size={140} />
        <p className="mt-4 font-display text-xl font-semibold text-kicap-soft" role="status">
          Getting things ready…
        </p>
      </Screen>
    )
  }

  if (session.status === 'offline') {
    return (
      <Screen className="items-center justify-center text-center">
        <Mascot mood="sad" size={140} />
        <h1 className="mt-4 font-display text-2xl font-bold">Can’t reach MakanApa</h1>
        <p className="mt-2 max-w-xs text-kicap-soft">Check your internet connection, then try again.</p>
        <Button className="mt-6 max-w-xs" onClick={retry}>
          Try again
        </Button>
      </Screen>
    )
  }

  if (session.status === 'signedOut') {
    return (
      <Routes>
        <Route path="/welcome" element={<Onboarding />} />
        <Route path="/sign-in" element={<SignIn />} />
        <Route path="*" element={<Navigate to={getStored('onboarded') ? '/sign-in' : '/welcome'} replace />} />
      </Routes>
    )
  }

  return (
    <DecideProvider>
    <NearbyProvider>
    <Routes>
      <Route path="/welcome" element={<Onboarding />} />
      <Route path="/sign-in" element={<SignIn />} />
      <Route element={<AppShell />}>
        <Route path="settings" element={<Settings />} />
        <Route index element={<DecideHome />} />
        <Route path="decide" element={<Preferences />} />
        <Route path="decide/result" element={<Result />} />
        <Route path="decide/saved" element={<SavedPick />} />
        <Route path="nearby" element={<Nearby />} />
        <Route path="nearby/result" element={<Result />} />
        <Route path="community" element={<ComingSoon mood="excited" title="Your community" detail="Trending picks and posts arrive in a later build." />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
    </NearbyProvider>
    </DecideProvider>
  )
}

/** Shown when a new version of the web app has downloaded; reloading switches to it. */
function UpdateBanner() {
  const {
    needRefresh: [needRefresh],
    updateServiceWorker,
  } = useRegisterSW()

  if (!needRefresh) return null
  return (
    <div role="status" className="fixed inset-x-0 top-0 z-50 mx-auto flex max-w-[480px] items-center gap-3 bg-kicap px-5 py-3 text-white">
      <p className="flex-1 text-sm">A new version of MakanApa is ready.</p>
      <button type="button" className="min-h-11 font-display font-semibold underline" onClick={() => void updateServiceWorker(true)}>
        Reload
      </button>
    </div>
  )
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <SessionProvider>
        <BrowserRouter>
          <SketchFilters />
          <UpdateBanner />
          <AppRoutes />
        </BrowserRouter>
      </SessionProvider>
    </QueryClientProvider>
  </StrictMode>,
)
