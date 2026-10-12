import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { lazy, StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router'
import { useRegisterSW } from 'virtual:pwa-register/react'
import { SessionProvider, useSession } from './auth/session'
import { ForgotPassword, ResetPassword } from './features/auth/PasswordReset'
import { SignIn } from './features/auth/SignIn'
import { DecideHome } from './features/decide/Home'
import { Preferences } from './features/decide/Preferences'
import { Result } from './features/decide/Result'
import { SavedPick } from './features/decide/SavedPick'
import { DecideProvider } from './features/decide/state'
import { NearbyProvider } from './features/nearby/state'
import { Onboarding } from './features/onboarding/Onboarding'
import { AppShell } from './features/shell/AppShell'
import './index.css'
import './lib/install'
import { useTransitionedLocation } from './lib/pageTransition'
import { getStored } from './lib/storage'
import { Button } from './ui/Button'
import { Mascot } from './ui/Mascot'
import { Screen } from './ui/Screen'
import { SketchFilters } from './ui/SketchFilters'

// Loaded when first opened (and cached for offline by the service worker): the first screens
// (welcome, sign-in, Decide) don't wait for Nearby's map, the community or settings.
const Community = lazy(() => import('./features/community/Community').then((module) => ({ default: module.Community })))
const MyPlaces = lazy(() => import('./features/community/MyPlaces').then((module) => ({ default: module.MyPlaces })))
const Nearby = lazy(() => import('./features/nearby/Nearby').then((module) => ({ default: module.Nearby })))
const NotificationSettings = lazy(() => import('./features/notifications/Notifications').then((module) => ({ default: module.NotificationSettings })))
const BlockedPeople = lazy(() => import('./features/settings/BlockedPeople').then((module) => ({ default: module.BlockedPeople })))
const EditProfile = lazy(() => import('./features/settings/EditProfile').then((module) => ({ default: module.EditProfile })))
const SavedPlaces = lazy(() => import('./features/settings/SavedPlaces').then((module) => ({ default: module.SavedPlaces })))
const SeleraPage = lazy(() => import('./features/settings/SeleraPage').then((module) => ({ default: module.SeleraPage })))
const Settings = lazy(() => import('./features/settings/Settings').then((module) => ({ default: module.Settings })))
const AddPlace = lazy(() => import('./features/community/AddPlace').then((module) => ({ default: module.AddPlace })))
const AddPlaceDone = lazy(() => import('./features/community/AddPlace').then((module) => ({ default: module.AddPlaceDone })))
const AllPosts = lazy(() => import('./features/community/Posts').then((module) => ({ default: module.AllPosts })))
const ThreadPage = lazy(() => import('./features/community/Posts').then((module) => ({ default: module.ThreadPage })))

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: false } },
})

function AppRoutes() {
  const { session, retry, signOutHere } = useSession()
  // Every page change below animates; the routes render this location, not the live one.
  const shown = useTransitionedLocation()

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

  if (session.status === 'suspended') {
    return (
      <Screen className="items-center justify-center text-center">
        <Mascot mood="sad" size={140} />
        <h1 className="mt-4 font-display text-2xl font-bold">This account is suspended</h1>
        <p className="mt-2 max-w-xs text-kicap-soft">Contact support@trymakanapa.com if you think this is a mistake.</p>
        <Button variant="secondary" className="mt-6 max-w-xs" onClick={signOutHere}>
          Sign out
        </Button>
      </Screen>
    )
  }

  if (session.status === 'signedOut') {
    return (
      <Routes location={shown}>
        <Route path="/welcome" element={<Onboarding />} />
        <Route path="/sign-in" element={<SignIn />} />
        <Route path="/forgot-password" element={<ForgotPassword />} />
        <Route path="/reset-password" element={<ResetPassword />} />
        {/* Where the link was going comes along, so signing in lands there. */}
        {/* `shown`, not the live URL: during a page transition the live URL is already /welcome, and
            `from: '/welcome'` sent onboarding back to its own start after the halal question. */}
        <Route path="*" element={<Navigate to={getStored('onboarded') ? '/sign-in' : '/welcome'} replace state={shown.pathname === '/' ? undefined : { from: shown.pathname + shown.search }} />} />
      </Routes>
    )
  }

  return (
    <DecideProvider>
    <NearbyProvider>
    <Routes location={shown}>
      <Route path="/welcome" element={<Onboarding />} />
      <Route path="/sign-in" element={<SignIn />} />
      <Route path="/forgot-password" element={<ForgotPassword />} />
      <Route path="/reset-password" element={<ResetPassword />} />
      <Route element={<AppShell />}>
        <Route path="settings" element={<Settings />} />
        <Route path="settings/profile" element={<EditProfile />} />
        <Route path="settings/selera" element={<SeleraPage />} />
        <Route path="settings/saved" element={<SavedPlaces />} />
        <Route path="settings/blocked" element={<BlockedPeople />} />
        <Route path="settings/notifications" element={<NotificationSettings />} />
        <Route index element={<DecideHome />} />
        <Route path="decide" element={<Preferences />} />
        <Route path="decide/result" element={<Result />} />
        <Route path="decide/saved" element={<SavedPick />} />
        <Route path="nearby" element={<Nearby />} />
        <Route path="nearby/result" element={<Result />} />
        <Route path="community" element={<Community />} />
        <Route path="community/posts" element={<AllPosts />} />
        <Route path="community/posts/:id" element={<ThreadPage />} />
        <Route path="community/add" element={<AddPlace />} />
        <Route path="community/add/done" element={<AddPlaceDone />} />
        <Route path="community/mine" element={<MyPlaces />} />
        <Route path="p/:key" element={<SharedPlace />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
    </NearbyProvider>
    </DecideProvider>
  )
}

/**
 * A shared place link, same path as the website's (`/p/624-kfc`): the place opens in Nearby.
 * Signed out, onboarding or sign-in comes first and lands back here.
 */
function SharedPlace() {
  const { key = '' } = useParams()
  const id = Number(/^\d+/.exec(key)?.[0])
  return <Navigate to={id ? `/nearby?place=${id}` : '/nearby'} replace />
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
