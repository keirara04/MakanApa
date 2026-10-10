import { useId, type InputHTMLAttributes } from 'react'

/** Labelled text input with its error underneath, linked for screen readers. */
export function Field({ label, error, ...props }: InputHTMLAttributes<HTMLInputElement> & { label: string; error?: string }) {
  const id = useId()
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="font-display text-base font-semibold">
        {label}
      </label>
      <input
        id={id}
        {...props}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        className="min-h-13 rounded-row border-2 border-hairline bg-surface px-4 text-base text-kicap placeholder:text-kicap-soft focus:border-kicap focus:outline-none aria-invalid:border-sambal-deep"
      />
      {error && (
        <p id={`${id}-error`} className="text-sm text-sambal-deep">
          {error}
        </p>
      )}
    </div>
  )
}
