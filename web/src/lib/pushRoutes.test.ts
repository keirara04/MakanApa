import { describe, expect, it } from 'vitest'
import { pushTarget } from './pushRoutes'

describe('push tap routing (same types as iOS)', () => {
  it('opens the nudged place, or Decide when the nudge has none', () => {
    expect(pushTarget({ type: 'meal_nudge', nudgeId: 7, restaurantId: 42 })).toBe('/nearby?nudge=7&place=42')
    expect(pushTarget({ type: 'meal_nudge', nudgeId: 7 })).toBe('/?nudge=7')
  })

  it('opens the thread for replies and reactions', () => {
    expect(pushTarget({ type: 'community_post_replied', postId: 5 })).toBe('/community/posts/5')
    expect(pushTarget({ type: 'community_post_reacted', postId: 5 })).toBe('/community/posts/5')
  })

  it('sends contribution outcomes to Community and everything else home', () => {
    expect(pushTarget({ type: 'halal_report_decided' })).toBe('/community')
    expect(pushTarget({ type: 'halal_certificate_lapsing' })).toBe('/community')
    expect(pushTarget({ type: 'release' })).toBe('/')
    expect(pushTarget(null)).toBe('/')
  })
})
