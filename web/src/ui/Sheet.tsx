import { useEffect, useId, useRef, type ReactNode } from 'react'

/**
 * Modal sheet on the native <dialog>: focus trap, Escape and the backdrop come with it.
 * Slides up from the bottom on phones; a centred card from tablet width.
 */
export function Sheet({ open, onClose, title, children }: { open: boolean; onClose: () => void; title: string; children: ReactNode }) {
  const dialog = useRef<HTMLDialogElement>(null)
  const titleId = useId()

  useEffect(() => {
    const element = dialog.current
    if (!element) return
    if (open && !element.open) element.showModal()
    if (!open && element.open) element.close()
  }, [open])

  return (
    <dialog
      ref={dialog}
      aria-labelledby={titleId}
      onClose={onClose}
      onClick={(event) => event.target === dialog.current && onClose()}
      className="sheet m-0 mt-auto max-h-[90dvh] w-full max-w-none rounded-t-[28px] bg-surface p-0 text-kicap backdrop:bg-kicap/40 md:m-auto md:max-w-lg md:rounded-[28px]"
    >
      <div className="flex flex-col gap-4 px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-6">
        <div className="mx-auto -mt-2 h-1.5 w-10 rounded-full bg-hairline md:hidden" aria-hidden="true" />
        <h2 id={titleId} className="font-display text-2xl font-bold tracking-tight">
          {title}
        </h2>
        {children}
      </div>
    </dialog>
  )
}
