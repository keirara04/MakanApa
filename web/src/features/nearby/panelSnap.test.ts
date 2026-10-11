import { describe, expect, it } from 'vitest'
import { nextSnap, settle, snapHeights } from './panelSnap'

describe('Nearby area panel snapping', () => {
  const heights = snapHeights(700)

  it('sizes the three heights from the space available, always in order', () => {
    expect(heights).toEqual({ collapsed: 104, medium: 320, large: 560 })
    const tiny = snapHeights(200)
    expect(tiny.collapsed).toBeLessThanOrEqual(tiny.medium)
    expect(tiny.medium).toBeLessThanOrEqual(tiny.large)
  })

  it('lands on the nearest height after a slow drag', () => {
    expect(settle(heights, 150, 0)).toBe('collapsed')
    expect(settle(heights, 400, 0)).toBe('medium')
    expect(settle(heights, 520, 0)).toBe('large')
  })

  it('moves one step in the direction of a flick', () => {
    expect(settle(heights, 130, 1)).toBe('medium')
    expect(settle(heights, 330, 1)).toBe('large')
    expect(settle(heights, 550, -1)).toBe('medium')
    expect(settle(heights, 300, -1)).toBe('collapsed')
  })

  it('steps up on a tap of the handle, then back down', () => {
    expect([nextSnap('collapsed'), nextSnap('medium'), nextSnap('large')]).toEqual(['medium', 'large', 'collapsed'])
  })
})
