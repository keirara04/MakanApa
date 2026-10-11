import { useEffect, useRef, useState, type PointerEvent, type ReactNode } from 'react'
import { nextSnap, settle, snapHeights, type Snap } from './panelSnap'

/** Movement before a press counts as a drag; anything less stays a tap on whatever was under it. */
const DRAG_SLOP = 6

/**
 * The phone's area panel over the full-screen map: collapsed, medium or large, like iOS. Drag it
 * by its top (or anywhere while it isn't fully open); tapping the handle steps through the
 * heights, so it never needs a drag. The list only scrolls once it's fully open.
 */
export function NearbyPanel({ header, children }: { header: ReactNode; children: ReactNode }) {
  const panel = useRef<HTMLElement>(null)
  const [available, setAvailable] = useState(0)
  const [snap, setSnap] = useState<Snap>('collapsed')
  const [dragHeight, setDragHeight] = useState<number | null>(null)
  const press = useRef<{ y: number; height: number; dragging: boolean; lastY: number; lastT: number; velocity: number } | null>(null)

  // Sized from the map area it sits in, which follows the browser's toolbar as it hides and shows.
  useEffect(() => {
    const parent = panel.current?.parentElement
    if (!parent) return
    const observer = new ResizeObserver(([entry]) => setAvailable(entry.contentRect.height))
    observer.observe(parent)
    return () => observer.disconnect()
  }, [])

  const heights = snapHeights(available)
  const open = snap === 'large'

  function down(event: PointerEvent<HTMLElement>, fromHeader: boolean) {
    if (open && !fromHeader) return
    press.current = { y: event.clientY, height: heights[snap], dragging: false, lastY: event.clientY, lastT: event.timeStamp, velocity: 0 }
  }

  function move(event: PointerEvent<HTMLElement>) {
    const current = press.current
    if (!current) return
    const dy = current.y - event.clientY
    if (!current.dragging) {
      if (Math.abs(dy) < DRAG_SLOP) return
      // Captured only once it's a drag, so a plain tap still reaches the row or button under it.
      current.dragging = true
      panel.current?.setPointerCapture(event.pointerId)
    }
    const elapsed = event.timeStamp - current.lastT
    if (elapsed > 0) current.velocity = (current.lastY - event.clientY) / elapsed
    current.lastY = event.clientY
    current.lastT = event.timeStamp
    setDragHeight(Math.min(Math.max(current.height + dy, heights.collapsed), heights.large))
  }

  function up() {
    const current = press.current
    press.current = null
    if (!current?.dragging || dragHeight === null) return
    setSnap(settle(heights, dragHeight, current.velocity))
    setDragHeight(null)
  }

  return (
    <section
      ref={panel}
      aria-label="Places around here"
      onPointerDown={(event) => down(event, false)}
      onPointerMove={move}
      onPointerUp={up}
      onPointerCancel={up}
      style={{ height: dragHeight ?? heights[snap], touchAction: open ? 'auto' : 'none' }}
      className={`absolute inset-x-0 bottom-0 z-20 flex flex-col overflow-hidden rounded-t-[28px] border-t border-hairline bg-surface shadow-[0_-10px_30px_-14px_rgb(41_32_29/0.4)] ${
        dragHeight === null ? 'transition-[height] duration-300 ease-[cubic-bezier(0.2,0.8,0.2,1)] motion-reduce:transition-none' : ''
      }`}
    >
      <div
        onPointerDown={(event) => {
          event.stopPropagation()
          down(event, true)
        }}
        className="shrink-0 touch-none"
      >
        <button
          type="button"
          onClick={() => setSnap(nextSnap(snap))}
          aria-label={open ? 'Show fewer places' : 'Show more places'}
          aria-expanded={snap !== 'collapsed'}
          className="mx-auto flex h-7 w-20 items-center justify-center"
        >
          <span aria-hidden="true" className="h-1.5 w-10 rounded-full bg-kicap/20" />
        </button>
        {header}
      </div>
      <div className={`min-h-0 flex-1 px-5 pb-6 ${open ? 'overflow-y-auto overscroll-contain' : 'overflow-hidden'}`}>{children}</div>
    </section>
  )
}
