import { useCallback, useEffect, useState } from 'react'

/** A short message at the bottom of the screen, gone after 3 seconds. */
export function useToast(): { toast: (message: string) => void; element: React.ReactNode } {
  const [message, setMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!message) return
    const timer = setTimeout(() => setMessage(null), 3000)
    return () => clearTimeout(timer)
  }, [message])

  const toast = useCallback((text: string) => setMessage(text), [])
  const element = (
    <div role="status" aria-live="polite" className="pointer-events-none fixed inset-x-0 bottom-28 z-50 flex justify-center px-5 md:bottom-8">
      {message && <p className="max-w-md rounded-row bg-kicap/90 px-4 py-3 text-center text-cream shadow-lift">{message}</p>}
    </div>
  )
  return { toast, element }
}
