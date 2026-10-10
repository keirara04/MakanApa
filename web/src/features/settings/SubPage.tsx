import type { ReactNode } from 'react'
import { Link } from 'react-router'

/** A screen under Settings: a way back, the title, then the content. */
export function SubPage({ title, children, action }: { title: string; children: ReactNode; action?: ReactNode }) {
  return (
    <div className="flex flex-col gap-6 pt-2 md:max-w-xl">
      <Link to="/settings" className="-ml-1 min-h-11 self-start font-display font-semibold text-sambal-deep">
        ‹ Settings
      </Link>
      <div className="-mt-4 flex items-center justify-between gap-3">
        <h1 className="font-display text-3xl font-bold tracking-tight md:text-4xl">{title}</h1>
        {action}
      </div>
      {children}
    </div>
  )
}
