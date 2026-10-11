import { startTransition, useLayoutEffect, useRef, useState } from 'react'
import { useLocation, type Location } from 'react-router'

export type NavDirection = 'forward' | 'back' | 'tab'

/** The tab a path belongs to; Decide covers its whole flow, and Settings sits over every tab. */
function section(path: string): string {
  const first = path.split('/').filter(Boolean)[0] ?? ''
  return first === 'decide' ? '' : first
}

/** How a navigation moves: into a deeper page, back out of one, or across to another tab. */
export function navDirection(from: string, to: string): NavDirection {
  const [a, b] = [section(from), section(to)]
  if (a !== b) return b === 'settings' ? 'forward' : a === 'settings' ? 'back' : 'tab'
  const depth = (path: string) => path.split('/').filter(Boolean).length
  return depth(to) > depth(from) ? 'forward' : depth(to) < depth(from) ? 'back' : 'tab'
}

/**
 * The location the routes show, a step behind the real one so each page change can run as a
 * view transition (the browser snapshots the old page, React swaps it, the two animate). Pass it
 * to <Routes location>. Without View Transitions support, or with reduced motion, it's instant.
 *
 * The swap is a React transition, not a flushSync: pages are lazy-loaded, and a forced update
 * would animate into the "Loading…" fallback. As a transition, the old page stays up until the
 * new one is ready, and the view transition waits for that commit.
 */
export function useTransitionedLocation(): Location {
  const location = useLocation()
  const [shown, setShown] = useState(location)
  const committed = useRef<(() => void) | null>(null)

  // The new page is on screen: let the browser take its snapshot and animate.
  useLayoutEffect(() => {
    committed.current?.()
    committed.current = null
  }, [shown])

  useLayoutEffect(() => {
    if (location.key === shown.key) return
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    // Only real page changes animate; a new query string on the same page (filters, ?place=) doesn't.
    if (!document.startViewTransition || reduce || location.pathname === shown.pathname) {
      setShown(location)
      return
    }
    const root = document.documentElement
    root.dataset.nav = navDirection(shown.pathname, location.pathname)
    const transition = document.startViewTransition(
      () =>
        new Promise<void>((resolve) => {
          committed.current = resolve
          startTransition(() => setShown(location))
        }),
    )
    // A quick second tap replaces this transition; `ready` then rejects, which is fine.
    transition.ready.catch(() => {})
    void transition.finished.finally(() => delete root.dataset.nav)
    // `shown` is what's on screen; only a new location starts a transition.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location])

  return shown
}
