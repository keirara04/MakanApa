import { useEffect, useRef, useState } from 'react'

/**
 * Google's own "Sign in with Google" button (Google Identity Services). Its ID token's `aud` is
 * this web client id, which is exactly what the API verifies against (GOOGLE_SERVER_CLIENT_ID).
 * The client's "Authorized JavaScript origins" in Google Cloud must list this site's origin.
 */
const CLIENT_ID: string | undefined = import.meta.env.VITE_GOOGLE_CLIENT_ID

interface GoogleIdentity {
  accounts: {
    id: {
      initialize(options: { client_id: string; callback: (response: { credential: string }) => void; ux_mode?: 'popup' }): void
      renderButton(element: HTMLElement, options: Record<string, unknown>): void
    }
  }
}

declare global {
  interface Window {
    google?: GoogleIdentity
  }
}

let loader: Promise<GoogleIdentity> | null = null

function loadGoogle(): Promise<GoogleIdentity> {
  loader ??= new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = 'https://accounts.google.com/gsi/client'
    script.async = true
    script.onload = () => (window.google ? resolve(window.google) : reject(new Error('Google sign-in did not load')))
    script.onerror = () => {
      loader = null
      reject(new Error('Google sign-in did not load'))
    }
    document.head.appendChild(script)
  })
  return loader
}

export function GoogleButton({ onCredential }: { onCredential: (idToken: string) => void }) {
  const slot = useRef<HTMLDivElement>(null)
  const callback = useRef(onCredential)
  const [failed, setFailed] = useState(!CLIENT_ID)

  useEffect(() => {
    callback.current = onCredential
  })

  useEffect(() => {
    if (!CLIENT_ID) return
    let cancelled = false
    loadGoogle()
      .then((google) => {
        if (cancelled || !slot.current) return
        google.accounts.id.initialize({ client_id: CLIENT_ID, callback: ({ credential }) => callback.current(credential), ux_mode: 'popup' })
        google.accounts.id.renderButton(slot.current, {
          type: 'standard',
          theme: 'outline',
          size: 'large',
          shape: 'pill',
          text: 'continue_with',
          width: Math.min(slot.current.clientWidth || 320, 400),
        })
      })
      .catch(() => !cancelled && setFailed(true))
    return () => {
      cancelled = true
    }
  }, [])

  if (failed) {
    return <p className="text-center text-sm text-kicap-soft">Google sign-in isn’t available right now. Use your email instead.</p>
  }

  return <div ref={slot} className="flex min-h-11 w-full justify-center" />
}
