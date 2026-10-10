import type { ButtonHTMLAttributes, ReactNode } from 'react'

type Variant = 'primary' | 'secondary' | 'quiet'

const styles: Record<Variant, string> = {
  // 20px type: white on sambal is 3.8:1, which passes as large text only.
  primary: 'min-h-14 w-full rounded-full bg-sambal px-6 font-display text-xl font-semibold text-white shadow-lift disabled:opacity-60',
  secondary: 'min-h-13 w-full rounded-full border-2 border-kicap bg-surface px-6 font-display text-lg font-semibold text-kicap disabled:opacity-60',
  quiet: 'min-h-11 px-3 font-display text-base font-semibold text-sambal-deep underline decoration-2 underline-offset-4 disabled:opacity-60',
}

export function Button({ variant = 'primary', busy = false, children, className = '', ...props }: ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: Variant
  /** Disables the button and announces the work to screen readers. */
  busy?: boolean
  children: ReactNode
}) {
  return (
    <button
      type="button"
      {...props}
      disabled={props.disabled || busy}
      aria-busy={busy || undefined}
      className={`press inline-flex items-center justify-center gap-2 ${styles[variant]} ${className}`}
    >
      {children}
    </button>
  )
}
