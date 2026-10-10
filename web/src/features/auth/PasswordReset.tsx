import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { authApi } from '../../api/auth'
import { errorMessage } from '../../lib/errors'
import { Button } from '../../ui/Button'
import { Field } from '../../ui/Field'
import { Mascot } from '../../ui/Mascot'
import { Screen } from '../../ui/Screen'

/** "Forgot password?": asks for the email and says the same thing whether or not it exists. */
export function ForgotPassword() {
  const [sent, setSent] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const email = String(new FormData(event.currentTarget).get('email') ?? '').trim()
    setBusy(true)
    setError(null)
    try {
      await authApi.forgotPassword(email)
      setSent(email)
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Screen>
      <Link to="/sign-in" className="-ml-1 min-h-11 self-start pt-2 font-display font-semibold text-sambal-deep underline">
        Back to sign in
      </Link>
      <div className="flex flex-1 flex-col justify-center gap-6 py-8">
        <Mascot mood={sent ? 'wave' : 'thinking'} size={112} />
        {sent ? (
          <div aria-live="polite">
            <h1 className="font-display text-4xl font-bold tracking-tight">Check your email</h1>
            <p className="mt-3 text-lg text-kicap-soft">
              If there’s a MakanApa account for {sent}, a link to set a new password is on its way. It works for 60 minutes. Check spam if it
              doesn’t show up.
            </p>
          </div>
        ) : (
          <>
            <div>
              <h1 className="font-display text-4xl font-bold tracking-tight">Forgot your password?</h1>
              <p className="mt-3 text-lg text-kicap-soft">Enter your account’s email and we’ll send a link to set a new one.</p>
            </div>
            <form onSubmit={submit} className="flex flex-col gap-4">
              <Field label="Email" name="email" type="email" autoComplete="email" inputMode="email" required />
              {error && <p role="alert" className="text-sambal-deep">{error}</p>}
              <Button type="submit" busy={busy}>
                Send reset link
              </Button>
            </form>
          </>
        )}
      </div>
    </Screen>
  )
}

/** The page the reset email links to: /reset-password?token=…&email=… */
export function ResetPassword() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''
  const [done, setDone] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const password = String(form.get('password') ?? '')
    if (password !== String(form.get('confirm') ?? '')) {
      setError('The two passwords don’t match.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      await authApi.resetPassword(email, token, password)
      setDone(true)
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Screen>
      <div className="flex flex-1 flex-col justify-center gap-6 py-8">
        <Mascot mood={done ? 'excited' : 'thinking'} size={112} />
        {!token || !email ? (
          <div>
            <h1 className="font-display text-4xl font-bold tracking-tight">This link isn’t complete</h1>
            <p className="mt-3 text-lg text-kicap-soft">Open the link straight from the email, or ask for a new one.</p>
            <Button className="mt-6" onClick={() => navigate('/forgot-password')}>
              Ask for a new link
            </Button>
          </div>
        ) : done ? (
          <div aria-live="polite">
            <h1 className="font-display text-4xl font-bold tracking-tight">Password changed</h1>
            <p className="mt-3 text-lg text-kicap-soft">You’ve been signed out everywhere. Sign in with your new password.</p>
            <Button className="mt-6" onClick={() => navigate('/sign-in', { replace: true })}>
              Sign in
            </Button>
          </div>
        ) : (
          <>
            <div>
              <h1 className="font-display text-4xl font-bold tracking-tight">Set a new password</h1>
              <p className="mt-3 text-lg text-kicap-soft">For {email}. You’ll be signed out on your other devices.</p>
            </div>
            <form onSubmit={submit} className="flex flex-col gap-4">
              <Field label="New password" name="password" type="password" autoComplete="new-password" minLength={8} required />
              <Field label="Type it again" name="confirm" type="password" autoComplete="new-password" minLength={8} required />
              <p className="-mt-2 text-sm text-kicap-soft">At least 8 characters.</p>
              {error && (
                <p role="alert" className="text-sambal-deep">
                  {error}
                  {error.includes('expired') && (
                    <>
                      {' '}
                      <Link to="/forgot-password" className="underline">
                        Ask for a new link
                      </Link>
                    </>
                  )}
                </p>
              )}
              <Button type="submit" busy={busy}>
                Save new password
              </Button>
            </form>
          </>
        )}
      </div>
    </Screen>
  )
}
