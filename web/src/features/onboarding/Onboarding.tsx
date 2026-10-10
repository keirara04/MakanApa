import { useState } from 'react'
import { useNavigate } from 'react-router'
import { authApi } from '../../api/auth'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { useLocation } from '../../lib/location'
import { setStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { Screen } from '../../ui/Screen'

type Step = 'hello' | 'location' | 'halal'
const STEPS: Step[] = ['hello', 'location', 'halal']

/** The iOS onboarding: what MakanApa does, location, halal. Finishing makes a guest account. */
export function Onboarding() {
  const [step, setStep] = useState<Step>('hello')
  const navigate = useNavigate()

  return (
    <Screen>
      <ol className="flex justify-center gap-2 pt-2" aria-label={`Step ${STEPS.indexOf(step) + 1} of 3`}>
        {STEPS.map((item) => (
          <li key={item} className={`h-2 rounded-full transition-[width] ${item === step ? 'w-6 bg-kicap' : 'w-2 bg-hairline'}`} />
        ))}
      </ol>

      {step === 'hello' && <Hello onNext={() => setStep('location')} onSignIn={() => navigate('/sign-in')} />}
      {step === 'location' && <Location onNext={() => setStep('halal')} />}
      {step === 'halal' && <Halal onDone={() => navigate('/', { replace: true })} />}
    </Screen>
  )
}

function Hello({ onNext, onSignIn }: { onNext: () => void; onSignIn: () => void }) {
  return (
    <>
      <div className="flex flex-1 flex-col justify-center gap-8 py-8">
        <div className="relative mx-auto w-full max-w-[320px]">
          {/* An example pick, taped in like the website's polaroids. */}
          <div className="sketch -rotate-2 px-6 pb-6 pt-7" style={{ ['--sketch-radius' as string]: '22px' }} aria-hidden="true">
            <span className="tape -top-3 left-1/2 -translate-x-1/2 rotate-2" />
            <p className="text-sm font-semibold text-sambal-deep">Picked for you</p>
            <p className="mt-1 font-display text-3xl font-bold leading-tight">Nasi kandar</p>
            <p className="mt-2 pr-16 text-kicap-soft">Open now, 6 minutes away, about RM12</p>
          </div>
          <Mascot mood="wave" size={120} className="absolute -bottom-16 -right-3 rotate-6" />
        </div>

        <div className="mt-10">
          <h1 className="font-display text-[2.6rem] font-bold leading-[1.05] tracking-tight">Can’t decide what to eat?</h1>
          <p className="mt-4 text-lg leading-relaxed text-kicap-soft">
            Tap once and we’ll pick somewhere good nearby. No endless scrolling, no group-chat debates.
          </p>
        </div>
      </div>

      <div className="flex flex-col items-center gap-2">
        <Button onClick={onNext}>Get started</Button>
        <Button variant="quiet" onClick={onSignIn}>
          I already have an account
        </Button>
      </div>
    </>
  )
}

function Location({ onNext }: { onNext: () => void }) {
  const { state, request } = useLocation()

  return (
    <>
      <div className="flex flex-1 flex-col justify-center py-8">
        <img src="/illustrations/location-map.svg" alt="" aria-hidden="true" width={160} height={160} className="mx-auto" />
        <h1 className="mt-6 font-display text-4xl font-bold leading-tight tracking-tight">Find good food around you</h1>
        <p className="mt-4 text-lg leading-relaxed text-kicap-soft">
          We use your location to pick places near you and show what’s popular in your area.
        </p>
        <p className="mt-3 text-sm text-kicap-soft">Your location is never shown to other people.</p>

        <div aria-live="polite" className="mt-6 min-h-12">
          {state.status === 'ready' && <p className="font-display text-lg font-semibold text-pandan">Location is on</p>}
          {state.status === 'denied' && (
            <p className="text-kicap">
              Location is blocked for this site. To turn it on, tap the icon next to the address bar, open Permissions,
              and allow Location. You can carry on without it for now.
            </p>
          )}
          {state.status === 'unavailable' && <p className="text-kicap">Couldn’t get a location fix. Try again outside or by a window.</p>}
        </div>
      </div>

      {state.status === 'ready' || state.status === 'denied' ? (
        <Button onClick={onNext}>Continue</Button>
      ) : (
        <div className="flex flex-col items-center gap-2">
          <Button onClick={() => void request()} busy={state.status === 'locating'}>
            {state.status === 'locating' ? 'Finding you…' : 'Turn on location'}
          </Button>
          <Button variant="quiet" onClick={onNext}>
            Not now
          </Button>
        </div>
      )}
    </>
  )
}

function Halal({ onDone }: { onDone: () => void }) {
  const { session, continueAsGuest, setUser } = useSession()
  const [busy, setBusy] = useState<boolean | null>(null)
  const [error, setError] = useState<string | null>(null)

  async function choose(halalOnly: boolean) {
    setBusy(halalOnly)
    setError(null)
    try {
      if (session.status !== 'signedIn') await continueAsGuest()
      const { user } = await authApi.updateProfile({ halalPreference: halalOnly })
      setUser(user)
      setStored('halalOnly', halalOnly)
      setStored('onboarded', true)
      onDone()
    } catch (caught) {
      setError(errorMessage(caught))
      setBusy(null)
    }
  }

  return (
    <>
      <div className="flex flex-1 flex-col justify-center py-8">
        <svg viewBox="0 0 24 24" className="mx-auto h-24 w-24 text-pandan" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
          <path d="m9 12 2 2 4-4" />
        </svg>
        <h1 className="mt-6 font-display text-4xl font-bold leading-tight tracking-tight">Do you only eat halal?</h1>
        <p className="mt-4 text-lg leading-relaxed text-kicap-soft">
          We’ll hide places known to be non-halal. Places we haven’t verified yet still show, clearly marked. Change it anytime in settings.
        </p>
        <p className="mt-3 text-sm text-kicap-soft">Halal info comes from the community. Always double-check at the restaurant.</p>
        {error && (
          <p role="alert" className="mt-5 text-sambal-deep">
            {error}
          </p>
        )}
      </div>

      <div className="flex flex-col gap-3">
        <Button onClick={() => void choose(true)} busy={busy === true} disabled={busy !== null}>
          Yes, hide non-halal
        </Button>
        <Button variant="secondary" onClick={() => void choose(false)} busy={busy === false} disabled={busy !== null}>
          No, show everything
        </Button>
      </div>
    </>
  )
}
