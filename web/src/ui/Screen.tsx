import type { ReactNode } from 'react'

/**
 * Phone-width column for every screen. On a wide screen it stays phone-sized and centred, so
 * the layout people see on Android is the one that's designed and tested.
 */
export function Screen({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div className={`mx-auto flex min-h-dvh w-full max-w-[480px] flex-col px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-[max(1rem,env(safe-area-inset-top))] ${className}`}>
      {children}
    </div>
  )
}
