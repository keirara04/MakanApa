import { describe, expect, it } from 'vitest'
import { relativeTime } from '../lib/time'
import { applyReaction, type Post } from './community'

const post = (overrides: Partial<Post> = {}): Post =>
  ({ id: 1, reactions: { up: 2 }, reactionCount: 2, myReaction: null, ...overrides }) as Post

describe('reactions toggle like the server', () => {
  it('adds a new reaction', () => {
    expect(applyReaction(post(), 'fire')).toMatchObject({ reactions: { up: 2, fire: 1 }, reactionCount: 3, myReaction: 'fire' })
  })

  it('removes the same reaction tapped again', () => {
    expect(applyReaction(post({ myReaction: 'up' }), 'up')).toMatchObject({ reactions: { up: 1 }, reactionCount: 1, myReaction: null })
  })

  it('switches to a different reaction in place', () => {
    expect(applyReaction(post({ myReaction: 'up' }), 'drool')).toMatchObject({ reactions: { up: 1, drool: 1 }, reactionCount: 2, myReaction: 'drool' })
  })
})

describe('relative time', () => {
  const now = Date.parse('2026-10-11T12:00:00Z')
  it('uses the largest whole unit', () => {
    expect(relativeTime('2026-10-11T11:59:40Z', now)).toBe('now')
    expect(relativeTime('2026-10-11T11:55:00Z', now)).toMatch(/^5 min/)
    expect(relativeTime('2026-10-11T09:00:00Z', now)).toMatch(/^3 hr/)
    expect(relativeTime('2026-10-10T12:00:00Z', now)).toBe('yesterday')
  })
})
