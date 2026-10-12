import { useState } from 'react'
import { useLocation as useRouterLocation, useNavigate } from 'react-router'
import { authApi } from '../../api/auth'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { useLocation } from '../../lib/location'
import { useRegion, type Region } from '../../lib/region'
import { getStored, setStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Screen, StepLayout } from '../../ui/Screen'
import { HalalKey, HowItWorks, RadiusMap } from './OnboardingArt'

type Step = 'hello' | 'location' | 'halal'
const STEPS: Step[] = ['hello', 'location', 'halal']

/** The iOS onboarding: what MakanApa does, location, halal. Finishing makes a guest account. */
export function Onboarding() {
  const [step, setStep] = useState<Step>('hello')
  const navigate = useNavigate()
  // A link opened before onboarding (a shared place, a post) is where onboarding ends.
  const from = (useRouterLocation().state as { from?: string } | null)?.from

  const index = STEPS.indexOf(step)

  return (
    <Screen wide>
      <div className="grid grid-cols-[2.75rem_1fr_2.75rem] items-center pt-1">
        {index > 0 ? (
          <button type="button" onClick={() => setStep(STEPS[index - 1])} aria-label="Back" className="press -ml-2 flex h-11 w-11 items-center justify-center rounded-full text-kicap">
            <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d="M15 5l-7 7 7 7" />
            </svg>
          </button>
        ) : (
          <span />
        )}
        <ol className="flex justify-center gap-2" aria-label={`Step ${index + 1} of 3`}>
          {STEPS.map((item) => (
            <li key={item} className={`h-2 rounded-full transition-[width] ${item === step ? 'w-6 bg-kicap' : 'w-2 bg-hairline'}`} />
          ))}
        </ol>
      </div>

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
  const region = useRegion()
  const [dish, line] = EXAMPLE[region]
  return (
    <StepLayout
      art={<HowItWorks region={region} dish={dish} line={line} />}
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
      <p className={lead}>Tell us what you feel like. We check what’s open near you and pick one place. No endless scrolling, no group-chat debates.</p>
    </StepLayout>
  )
}

function Location({ onNext }: { onNext: () => void }) {
  const { state, request } = useLocation()

  return (
    <StepLayout
      art={<RadiusMap region={useRegion()} />}
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
      <p className={lead}>Picks come from the places around you, so we need to know where you are. Choose how far you’ll go each time you decide.</p>
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
      art={<HalalKey />}
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
        {abroad ? 'Halal food is harder to find here. ' : ''}
        If you choose yes, places known to be non-halal are hidden. Everything else still shows, with its halal status on it. You can change this anytime in Settings.
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
