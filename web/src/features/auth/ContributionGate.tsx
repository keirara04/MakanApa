import { useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import { accountApi } from '../../api/account'
import { authApi } from '../../api/auth'
import { ApiError } from '../../api/client'
import { useSession } from '../../auth/session'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { Sheet } from '../../ui/Sheet'

const RULES = [
  'Keep it about food and places, and be kind.',
  'No hate, harassment, sexual content, spam or links.',
  'Only vouch for halal info you’ve seen yourself.',
  'Zero tolerance: we review reports within 24 hours and remove content or accounts that break the rules.',
]

/**
 * Wraps anything other people will see (photos, posts, places, halal vouches, picks):
 * guests are asked to sign in, and accounts that haven't agreed to the current rules agree first.
 * `run(action)` performs the action straight away when neither applies.
 */
export function useContributionGate(feature: string, { terms = true }: { terms?: boolean } = {}): { gate: ReactNode; run: (action: () => void) => void } {
  const { user, setUser } = useSession()
  const navigate = useNavigate()
  const [pending, setPending] = useState<(() => void) | null>(null)
  const [step, setStep] = useState<'account' | 'agree' | null>(null)
  const [agreed, setAgreed] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  function close() {
    setStep(null)
    setPending(null)
    setAgreed(false)
    setError(null)
  }

  function run(action: () => void) {
    if (!user || user.isGuest) {
      setPending(() => action)
      setStep('account')
    } else if (terms && user.legal.needsAcceptance) {
      setPending(() => action)
      setStep('agree')
    } else action()
  }

  async function agree() {
    if (!user) return
    setBusy(true)
    setError(null)
    try {
      const { user: updated } = await accountApi.acceptTerms(user.legal)
      setUser(updated)
      const action = pending
      close()
      action?.()
    } catch (caught) {
      // The documents may have changed since the page loaded: refresh, ask again.
      await authApi.me().then(({ user: fresh }) => setUser(fresh)).catch(() => {})
      setAgreed(false)
      setError(caught instanceof ApiError && !caught.isNetwork ? caught.message : 'Couldn’t save that. Check your connection and try again.')
    } finally {
      setBusy(false)
    }
  }

  const gate = (
    <>
      <Sheet open={step === 'account'} onClose={close} title={`Sign in to ${feature}`}>
        <Mascot mood="wave" size={88} />
        <p className="text-kicap-soft">
          Picks, Nearby and saves work without an account. Adding places, posting and halal reports need one, so the community knows who’s contributing.
        </p>
        <p className="font-semibold text-pandan">Your picks, saved places and taste profile all come with you. Nothing lost.</p>
        <Button onClick={() => navigate('/sign-in')}>Sign in or create an account</Button>
        <Button variant="quiet" onClick={close}>
          Not now
        </Button>
      </Sheet>

      <Sheet open={step === 'agree'} onClose={close} title="Before you contribute">
        <p className="text-kicap-soft">Posts, places, photos and halal vouches are seen by other people, so everyone agrees to the same rules first.</p>
        <ul className="space-y-2 rounded-row bg-cream px-4 py-4">
          {RULES.map((rule) => (
            <li key={rule} className="flex gap-2">
              <span aria-hidden="true" className="font-bold text-pandan">
                ✓
              </span>
              {rule}
            </li>
          ))}
        </ul>
        <p className="text-sm">
          Read the full{' '}
          <a href="https://trymakanapa.com/terms" target="_blank" rel="noreferrer" className="underline">
            Terms of Use
          </a>
          ,{' '}
          <a href="https://trymakanapa.com/community-guidelines" target="_blank" rel="noreferrer" className="underline">
            Community Guidelines
          </a>{' '}
          and{' '}
          <a href="https://trymakanapa.com/privacy" target="_blank" rel="noreferrer" className="underline">
            Privacy Policy
          </a>
          .
        </p>
        <label className="flex cursor-pointer gap-3">
          <input type="checkbox" checked={agreed} onChange={(event) => setAgreed(event.target.checked)} className="mt-1 h-5 w-5 shrink-0 accent-[var(--color-sambal)]" />
          <span className="text-sm">
            I’m 13 or older (if I’m under 18, my parent or guardian agrees), and I agree to the Terms of Use and Community Guidelines.
          </span>
        </label>
        {error && <p role="alert" className="text-sambal-deep">{error}</p>}
        <Button onClick={() => void agree()} disabled={!agreed} busy={busy}>
          {busy ? 'Saving…' : 'I agree'}
        </Button>
        <Button variant="quiet" onClick={close}>
          Not now
        </Button>
      </Sheet>
    </>
  )

  return { gate, run }
}
