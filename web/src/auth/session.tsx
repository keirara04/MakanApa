import { createContext, use, useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { accountApi } from '../api/account'
import { authApi } from '../api/auth'
import { ApiError, setUnauthorizedHandler } from '../api/client'
import { isLinkOffer, type LinkOffer, type SessionResponse, type User } from '../api/types'
import { disablePush, syncPush } from '../lib/push'
import { getStored, setStored } from '../lib/storage'

export type Session =
  | { status: 'loading' }
  | { status: 'signedOut' }
  | { status: 'signedIn'; user: User }
  /** Couldn't reach the API on start, with a token we still trust: show a retry, don't sign out. */
  | { status: 'offline' }

interface SessionContextValue {
  session: Session
  user: User | null
  /** Silent guest account, like iOS onboarding's POST auth/guest. */
  continueAsGuest: () => Promise<void>
  login: (email: string, password: string) => Promise<void>
  register: (name: string, email: string, password: string) => Promise<void>
  /** Resolves with a link offer when the Google email already has a password account. */
  signInWithGoogle: (idToken: string) => Promise<LinkOffer | null>
  linkAccount: (linkToken: string, password: string) => Promise<void>
  logout: () => Promise<void>
  /** Deletes the account for good. Password only for accounts that have one. */
  deleteAccount: (password: string | null) => Promise<void>
  setUser: (user: User) => void
  retry: () => void
}

const SessionContext = createContext<SessionContextValue | null>(null)

export function SessionProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session>(() => (getStored('token') ? { status: 'loading' } : { status: 'signedOut' }))
  const [attempt, setAttempt] = useState(0)

  // Account-bound things go with the session, as on iOS. Saved places stay on the device.
  const signOutLocally = useCallback(() => {
    setStored('token', undefined)
    setStored('nearbyCache', undefined)
    setSession({ status: 'signedOut' })
  }, [])

  useEffect(() => setUnauthorizedHandler(signOutLocally), [signOutLocally])

  // Signed in: make sure this browser's push subscription (if any) belongs to this account.
  useEffect(() => {
    if (session.status === 'signedIn') void syncPush()
  }, [session.status])

  // Restore the session on start: the stored token is checked against GET auth/me.
  useEffect(() => {
    if (!getStored('token')) return
    let cancelled = false
    authApi
      .me()
      .then(({ user }) => !cancelled && setSession({ status: 'signedIn', user }))
      .catch((error: unknown) => {
        if (cancelled) return
        // 401 already signed out through the handler; anything else is the network or server.
        if (!(error instanceof ApiError && error.status === 401)) setSession({ status: 'offline' })
      })
    return () => {
      cancelled = true
    }
  }, [attempt])

  const adopt = useCallback(({ token, user }: SessionResponse) => {
    setStored('token', token)
    setSession({ status: 'signedIn', user })
  }, [])

  const value = useMemo<SessionContextValue>(() => ({
    session,
    user: session.status === 'signedIn' ? session.user : null,
    continueAsGuest: async () => adopt(await authApi.guest()),
    login: async (email, password) => adopt(await authApi.login(email, password)),
    register: async (name, email, password) => adopt(await authApi.register(name, email, password)),
    signInWithGoogle: async (idToken) => {
      const response = await authApi.google(idToken)
      if (isLinkOffer(response)) return response
      adopt(response)
      return null
    },
    linkAccount: async (linkToken, password) => adopt(await authApi.link(linkToken, password)),
    logout: async () => {
      // Pushes for this account stop on this browser; the token is dropped whatever the server says.
      await disablePush({ unsubscribe: false })
      await authApi.logout().catch(() => {})
      signOutLocally()
    },
    deleteAccount: async (password) => {
      await accountApi.deleteAccount(password)
      signOutLocally()
    },
    setUser: (user) => setSession({ status: 'signedIn', user }),
    retry: () => {
      setSession({ status: 'loading' })
      setAttempt((count) => count + 1)
    },
  }), [session, adopt, signOutLocally])

  return <SessionContext value={value}>{children}</SessionContext>
}

export function useSession(): SessionContextValue {
  const value = use(SessionContext)
  if (!value) throw new Error('useSession must be used inside SessionProvider')
  return value
}
