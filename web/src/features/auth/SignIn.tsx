import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router'
import { ApiError } from '../../api/client'
import type { LinkOffer } from '../../api/types'
import { GoogleButton } from '../../auth/GoogleButton'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { getStored, setStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Field } from '../../ui/Field'
import { Mascot } from '../../ui/Mascot'
import { Screen } from '../../ui/Screen'

type Mode = 'signIn' | 'create'

/**
 * Google or email. A guest who signs up here keeps everything: register and Google sign-in
 * upgrade the guest account in place. Signing in to an existing account switches to it.
 */
export function SignIn() {
  const { user, login, register, signInWithGoogle, linkAccount, continueAsGuest } = useSession()
  const navigate = useNavigate()
  const isGuest = user?.isGuest ?? false
  const [mode, setMode] = useState<Mode>(isGuest ? 'create' : 'signIn')
  const [linkOffer, setLinkOffer] = useState<LinkOffer | null>(null)
  const [busy, setBusy] = useState<'form' | 'google' | 'guest' | null>(null)
  const [error, setError] = useState<string | null>(null)

  function done() {
    setStored('onboarded', true)
    navigate('/', { replace: true })
  }

  async function attempt(kind: 'form' | 'google' | 'guest', work: () => Promise<void>) {
    setBusy(kind)
    setError(null)
    try {
      await work()
    } catch (caught) {
      // A 401 here is never an expired session: it's the email and password not matching.
      setError(caught instanceof ApiError && caught.status === 401 ? 'Email or password doesn’t match.' : errorMessage(caught))
    } finally {
      setBusy(null)
    }
  }

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const email = String(form.get('email') ?? '').trim()
    const password = String(form.get('password') ?? '')
    void attempt('form', async () => {
      if (linkOffer) await linkAccount(linkOffer.linkToken, password)
      else if (mode === 'create') await register(String(form.get('name') ?? '').trim(), email, password)
      else await login(email, password)
      done()
    })
  }

  function google(idToken: string) {
    void attempt('google', async () => {
      const offer = await signInWithGoogle(idToken)
      if (offer) setLinkOffer(offer)
      else done()
    })
  }

  const heading = linkOffer ? 'Link your Google account' : mode === 'create' ? 'Create your account' : 'Welcome back'

  return (
    <Screen>
      <header className="flex items-center justify-between">
        {(isGuest || getStored('onboarded') === undefined) && (
          <Button variant="quiet" className="-ml-3" onClick={() => navigate(-1)}>
            Back
          </Button>
        )}
      </header>

      <div className="flex items-end gap-3 pt-4">
        <h1 className="flex-1 font-display text-4xl font-bold leading-tight tracking-tight">{heading}</h1>
        <Mascot mood="thinking" size={88} className="-mb-1 shrink-0" />
      </div>
      <p className="mt-3 text-kicap-soft">
        {linkOffer
          ? `There’s already a MakanApa account for ${linkOffer.email}. Enter its password once to sign in with Google from now on.`
          : isGuest
            ? 'Your picks, saved places and taste profile all come with you. Nothing lost.'
            : 'Eat first. Decide later.'}
      </p>

      {!linkOffer && (
        <>
          <div className="mt-8">
            <GoogleButton onCredential={google} />
          </div>
          <div className="my-6 flex items-center gap-3 text-sm text-kicap-soft" aria-hidden="true">
            <span className="h-px flex-1 bg-hairline" />
            or with email
            <span className="h-px flex-1 bg-hairline" />
          </div>
        </>
      )}

      <form onSubmit={submit} className={`flex flex-col gap-4 ${linkOffer ? 'mt-8' : ''}`} noValidate={false}>
        {mode === 'create' && !linkOffer && <Field label="Name" name="name" autoComplete="name" required maxLength={100} />}
        {!linkOffer && <Field label="Email" name="email" type="email" autoComplete="email" inputMode="email" required />}
        <Field
          label="Password"
          name="password"
          type="password"
          autoComplete={mode === 'create' && !linkOffer ? 'new-password' : 'current-password'}
          minLength={mode === 'create' && !linkOffer ? 8 : undefined}
          required
        />
        {mode === 'create' && !linkOffer && <p className="-mt-2 text-sm text-kicap-soft">At least 8 characters.</p>}

        {error && (
          <p role="alert" className="text-sambal-deep">
            {error}
          </p>
        )}

        <Button type="submit" busy={busy === 'form'} disabled={busy !== null} className="mt-2">
          {linkOffer ? 'Link and sign in' : mode === 'create' ? 'Create account' : 'Sign in'}
        </Button>
      </form>

      <div className="mt-4 flex flex-col items-center gap-1">
        {linkOffer ? (
          <Button variant="quiet" onClick={() => setLinkOffer(null)}>
            Use a different account
          </Button>
        ) : (
          <Button variant="quiet" onClick={() => setMode(mode === 'create' ? 'signIn' : 'create')}>
            {mode === 'create' ? 'I already have an account' : 'Create an account'}
          </Button>
        )}

        {!user && (
          <Button variant="quiet" busy={busy === 'guest'} disabled={busy !== null} onClick={() => void attempt('guest', async () => {
            await continueAsGuest()
            done()
          })}>
            Continue without an account
          </Button>
        )}
      </div>

      <p className="mt-auto pt-8 text-center text-sm text-kicap-soft">
        By continuing you agree to the{' '}
        <a className="underline" href="https://trymakanapa.com/terms" target="_blank" rel="noreferrer">
          Terms of Use
        </a>{' '}
        and{' '}
        <a className="underline" href="https://trymakanapa.com/privacy" target="_blank" rel="noreferrer">
          Privacy Policy
        </a>
        .
      </p>
    </Screen>
  )
}
