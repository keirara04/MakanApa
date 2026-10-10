import type { ReactNode } from 'react'

/**
 * Full-page frame for screens outside the app shell (onboarding, sign-in, loading). A phone
 * column by default; `wide` lets two-column layouts spread out on tablets and desktops.
 */
export function Screen({ children, wide = false, className = '' }: { children: ReactNode; wide?: boolean; className?: string }) {
  return (
    <div
      className={`mx-auto flex min-h-dvh w-full flex-col px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-[max(1rem,env(safe-area-inset-top))] md:px-10 md:py-10 ${wide ? 'max-w-[480px] md:max-w-5xl' : 'max-w-[480px]'} ${className}`}
    >
      {children}
    </div>
  )
}

/**
 * One step of a flow: art, words, actions. On a phone they stack with the actions at the
 * bottom (thumb reach); from tablet width the art sits left and words plus actions right.
 */
export function StepLayout({ art, children, actions }: { art: ReactNode; children: ReactNode; actions: ReactNode }) {
  return (
    <div className="flex flex-1 flex-col md:grid md:grid-cols-2 md:items-center md:gap-16">
      <div className="flex flex-1 flex-col justify-center py-8 md:py-0">{art}</div>
      <div className="flex flex-col md:justify-center">
        <div className="pb-8">{children}</div>
        {actions}
      </div>
    </div>
  )
}
