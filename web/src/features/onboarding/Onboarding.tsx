import { useState } from 'react'
import { useLocation as useRouterLocation, useNavigate } from 'react-router'
import { authApi } from '../../api/auth'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { useLocation } from '../../lib/location'
import { useRegion, type Region } from '../../lib/region'
import { getStored, setStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { Screen, StepLayout } from '../../ui/Screen'

type Step = 'hello' | 'location' | 'halal'
const STEPS: Step[] = ['hello', 'location', 'halal']

/** The iOS onboarding: what MakanApa does, location, halal. Finishing makes a guest account. */
export function Onboarding() {
  const [step, setStep] = useState<Step>('hello')
  const navigate = useNavigate()
  // A link opened before onboarding (a shared place, a post) is where onboarding ends.
  const from = (useRouterLocation().state as { from?: string } | null)?.from

  return (
    <Screen wide>
      <ol className="flex justify-center gap-2 pt-2" aria-label={`Step ${STEPS.indexOf(step) + 1} of 3`}>
        {STEPS.map((item) => (
          <li key={item} className={`h-2 rounded-full transition-[width] ${item === step ? 'w-6 bg-kicap' : 'w-2 bg-hairline'}`} />
        ))}
      </ol>

      {step === 'hello' && <Hello onNext={() => setStep('location')} onSignIn={() => navigate('/sign-in', { state: { from } })} />}
      {step === 'location' && <Location onNext={() => setStep('halal')} />}
      {step === 'halal' && <Halal onDone={() => navigate(from ?? '/', { replace: true })} />}
    </Screen>
  )
}

/** The taped example pick: a dish that's easy to find where the person is. */
const EXAMPLE: Record<Region, [dish: string, line: string]> = {
  MY: ['Nasi kandar', 'Open now, 6 minutes away, about RM12'],
  KR: ['Kebab', 'Open now, 4 minutes away, about ₩10,000'],
  US: ['Chicken over rice', 'Open now, 4 minutes away, about $12'],
  OTHER: ['Kebab', 'Open now, 4 minutes away'],
}

const title = 'font-display text-4xl font-bold leading-[1.08] tracking-tight md:text-5xl'
const lead = 'mt-4 text-lg leading-relaxed text-kicap-soft'

function Hello({ onNext, onSignIn }: { onNext: () => void; onSignIn: () => void }) {
  const [dish, line] = EXAMPLE[useRegion()]
  return (
    <StepLayout
      art={
        <div className="relative mx-auto w-full max-w-[320px] md:max-w-[380px]">
          {/* An example pick, taped in like the website's polaroids. */}
          <div className="sketch -rotate-2 px-6 pb-6 pt-7" style={{ ['--sketch-radius' as string]: '22px' }} aria-hidden="true">
            <span className="tape -top-3 left-1/2 -translate-x-1/2 rotate-2" />
            <p className="text-sm font-semibold text-sambal-deep">Picked for you</p>
            <p className="mt-1 font-display text-3xl font-bold leading-tight md:text-4xl">{dish}</p>
            <p className="mt-2 pr-16 text-kicap-soft">{line}</p>
          </div>
          <Mascot mood="wave" size={120} className="absolute -bottom-16 -right-3 rotate-6 md:h-36 md:w-36" />
        </div>
      }
      actions={
        <div className="flex flex-col items-center gap-2 md:items-start">
          <Button onClick={onNext} className="md:max-w-xs">
            Get started
          </Button>
          <Button variant="quiet" onClick={onSignIn} className="md:-ml-3">
            I already have an account
          </Button>
        </div>
      }
    >
      <h1 className="mt-10 font-display text-[2.6rem] font-bold leading-[1.05] tracking-tight md:mt-0 md:text-6xl">Can’t decide what to eat?</h1>
      <p className={lead}>Tap once and we’ll pick somewhere good nearby. No endless scrolling, no group-chat debates.</p>
    </StepLayout>
  )
}

function Location({ onNext }: { onNext: () => void }) {
  const { state, request } = useLocation()

  return (
    <StepLayout
      art={<img src="/illustrations/location-map.svg" alt="" aria-hidden="true" width={160} height={160} className="mx-auto md:h-64 md:w-64" />}
      actions={
        state.status === 'ready' || state.status === 'denied' ? (
          <Button onClick={onNext} className="md:max-w-xs">
            Continue
          </Button>
        ) : (
          <div className="flex flex-col items-center gap-2 md:items-start">
            <Button onClick={() => void request()} busy={state.status === 'locating'} className="md:max-w-xs">
              {state.status === 'locating' ? 'Finding you…' : 'Turn on location'}
            </Button>
            <Button variant="quiet" onClick={onNext} className="md:-ml-3">
              Not now
            </Button>
          </div>
        )
      }
    >
      <h1 className={title}>Find good food around you</h1>
      <p className={lead}>We use your location to pick places near you and show what’s popular in your area.</p>
      <p className="mt-3 text-sm text-kicap-soft">Your location is never shown to other people.</p>
      <div aria-live="polite" className="mt-6 min-h-12">
        {state.status === 'ready' && <p className="font-display text-lg font-semibold text-pandan">Location is on</p>}
        {state.status === 'denied' && (
          <p>
            Location is blocked for this site. To turn it on, tap the icon next to the address bar, open Permissions, and allow
            Location. You can carry on without it for now.
          </p>
        )}
        {state.status === 'unavailable' && <p>Couldn’t get a location fix. Try again outside or by a window.</p>}
      </div>
    </StepLayout>
  )
}

function Halal({ onDone }: { onDone: () => void }) {
  const { setUser } = useSession()
  const [busy, setBusy] = useState<boolean | null>(null)
  const [error, setError] = useState<string | null>(null)
  const abroad = useRegion() !== 'MY'

  async function choose(halalOnly: boolean) {
    setBusy(halalOnly)
    setError(null)
    try {
      // The guest token is stored before the profile is saved, but the session only becomes
      // signed in at the end: that swaps the route tree, which would restart onboarding midway.
      // A retry after a failure reuses the guest already made.
      if (!getStored('token')) setStored('token', (await authApi.guest()).token)
      const { user } = await authApi.updateProfile({ halalPreference: halalOnly })
      setStored('halalOnly', halalOnly)
      setStored('onboarded', true)
      // Same render (batched): the signed-in routes and the next page arrive together.
      setUser(user)
      onDone()
    } catch (caught) {
      setError(errorMessage(caught))
      setBusy(null)
    }
  }

  return (
    <StepLayout
      art={
        <svg viewBox="0 0 24 24" className="mx-auto h-24 w-24 text-pandan md:h-48 md:w-48" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
          <path d="m9 12 2 2 4-4" />
        </svg>
      }
      actions={
        <div className="flex flex-col gap-3 md:max-w-sm">
          <Button onClick={() => void choose(true)} busy={busy === true} disabled={busy !== null}>
            Yes, hide non-halal
          </Button>
          <Button variant="secondary" onClick={() => void choose(false)} busy={busy === false} disabled={busy !== null}>
            No, show everything
          </Button>
        </div>
      }
    >
      <h1 className={title}>Do you only eat halal?</h1>
      <p className={lead}>
        {abroad
          ? 'Halal food is harder to find here. We hide places known to serve pork or alcohol. Places we haven’t verified yet still show, clearly marked. Change it anytime in settings.'
          : 'We’ll hide places known to be non-halal. Places we haven’t verified yet still show, clearly marked. Change it anytime in settings.'}
      </p>
      <p className="mt-3 text-sm text-kicap-soft">Halal info comes from the community. Always double-check at the restaurant.</p>
      {error && (
        <p role="alert" className="mt-5 text-sambal-deep">
          {error}
        </p>
      )}
    </StepLayout>
  )
}
