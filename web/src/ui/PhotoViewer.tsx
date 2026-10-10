import { useEffect, useRef, useState } from 'react'
import type { Photo } from '../api/decide'

export interface ViewerPhoto {
  url: string
  /** Google requires its photos' authors to be shown with them. */
  credit: { name: string | null; profileUrl: string | null } | null
  community: boolean
}

/** Google photos (with their authors) and community photos (no author shown), in display order. */
export function viewerPhotos(google: Photo[], community: string[] = []): ViewerPhoto[] {
  return [
    ...google.map((photo) => ({ url: photo.url, credit: photo.authorAttributions[0] ?? null, community: false })),
    ...community.map((url) => ({ url, credit: null, community: true })),
  ]
}

/**
 * A place's photos full screen (iOS's photo gallery): swipe or use the arrows, Escape or Close
 * to leave. A native modal dialog, so focus stays inside and the page behind is inert.
 */
export function PhotoViewer({ photos, start, title, onClose }: { photos: ViewerPhoto[]; start: number; title: string; onClose: () => void }) {
  const dialog = useRef<HTMLDialogElement>(null)
  const strip = useRef<HTMLDivElement>(null)
  const [index, setIndex] = useState(start)

  useEffect(() => {
    const element = dialog.current
    if (element && !element.open) element.showModal()
    strip.current?.children[start]?.scrollIntoView({ inline: 'center', block: 'nearest' })
    return () => element?.close()
  }, [start])

  function go(to: number) {
    const next = Math.max(0, Math.min(photos.length - 1, to))
    strip.current?.children[next]?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' })
  }

  const credit = photos[index]?.credit

  return (
    <dialog
      ref={dialog}
      aria-label={`Photos of ${title}`}
      onCancel={(event) => {
        if (event.target !== event.currentTarget) return
        event.preventDefault()
        onClose()
      }}
      onKeyDown={(event) => {
        if (event.key === 'ArrowRight') go(index + 1)
        if (event.key === 'ArrowLeft') go(index - 1)
      }}
      className="fixed inset-0 m-0 h-dvh max-h-none w-full max-w-none bg-kicap p-0 text-white backdrop:bg-kicap"
    >
      <div className="flex h-full flex-col">
        <div className="flex items-center justify-between px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-2">
          <p aria-live="polite" className="font-semibold">
            {index + 1} of {photos.length}
          </p>
          <button type="button" onClick={onClose} className="min-h-11 px-3 font-display text-lg font-semibold">
            Close
          </button>
        </div>
        <div
          ref={strip}
          onScroll={(event) => {
            const element = event.currentTarget
            setIndex(Math.round(element.scrollLeft / element.clientWidth))
          }}
          className="flex flex-1 snap-x snap-mandatory overflow-x-auto [scrollbar-width:none]"
        >
          {photos.map((photo, at) => (
            <div key={photo.url} className="flex h-full w-full shrink-0 snap-center items-center justify-center px-2">
              <img src={photo.url} alt={at === 0 ? title : ''} loading={Math.abs(at - start) <= 1 ? 'eager' : 'lazy'} className="max-h-full max-w-full object-contain" />
            </div>
          ))}
        </div>
        <div className="flex min-h-16 items-center justify-between gap-3 px-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
          <button type="button" aria-label="Previous photo" disabled={index === 0} onClick={() => go(index - 1)} className="hidden h-11 w-11 place-items-center rounded-full bg-white/15 disabled:opacity-30 md:grid">
            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" aria-hidden="true">
              <path d="m15 6-6 6 6 6" />
            </svg>
          </button>
          <p className="flex-1 text-center text-sm text-white/80">
            {credit?.name ? (
              <>
                Photo:{' '}
                {credit.profileUrl ? (
                  <a href={credit.profileUrl} target="_blank" rel="noreferrer" className="underline">
                    {credit.name}
                  </a>
                ) : (
                  credit.name
                )}
                , Google Maps
              </>
            ) : (
              photos[index]?.community && 'Shared by the MakanApa community'
            )}
          </p>
          <button type="button" aria-label="Next photo" disabled={index === photos.length - 1} onClick={() => go(index + 1)} className="hidden h-11 w-11 place-items-center rounded-full bg-white/15 disabled:opacity-30 md:grid">
            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" aria-hidden="true">
              <path d="m9 6 6 6-6 6" />
            </svg>
          </button>
        </div>
      </div>
    </dialog>
  )
}
