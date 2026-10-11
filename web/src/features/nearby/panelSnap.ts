/** The three heights of Nearby's area panel on phones, as on iOS (NearbyPanelState). */
export type Snap = 'collapsed' | 'medium' | 'large'

const ORDER: Snap[] = ['collapsed', 'medium', 'large']
const COLLAPSED = 104
const MEDIUM = 320
/** A release faster than this (px per ms) moves one step in its direction, wherever it is. */
const FLICK = 0.45

/** Visible heights from the space the panel has. Always ordered, even in a very short window. */
export function snapHeights(available: number): Record<Snap, number> {
  // Leaves the filters and the recenter button above it uncovered.
  const large = Math.max(Math.round(Math.min(available * 0.8, available - 128)), COLLAPSED)
  return { collapsed: COLLAPSED, medium: Math.min(MEDIUM, large), large }
}

/** Where a drag lands: the next snap along when flicked, otherwise the nearest. `velocity` > 0 is upward. */
export function settle(heights: Record<Snap, number>, height: number, velocity: number): Snap {
  if (velocity > FLICK) return ORDER.find((snap) => heights[snap] > height + 1) ?? 'large'
  if (velocity < -FLICK) return [...ORDER].reverse().find((snap) => heights[snap] < height - 1) ?? 'collapsed'
  return ORDER.reduce((best, snap) => (Math.abs(heights[snap] - height) < Math.abs(heights[best] - height) ? snap : best))
}

/** Tapping the handle: up a step, and from the top back down to collapsed. */
export function nextSnap(snap: Snap): Snap {
  return snap === 'collapsed' ? 'medium' : snap === 'medium' ? 'large' : 'collapsed'
}
