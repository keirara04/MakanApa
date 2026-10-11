import { describe, expect, it } from 'vitest'
import { navDirection } from './pageTransition'

describe('page transition direction', () => {
  it('pushes into deeper pages and slides back out', () => {
    expect(navDirection('/', '/decide')).toBe('forward')
    expect(navDirection('/decide', '/decide/result')).toBe('forward')
    expect(navDirection('/decide/result', '/')).toBe('back')
    expect(navDirection('/settings', '/settings/saved')).toBe('forward')
    expect(navDirection('/settings/saved', '/settings')).toBe('back')
  })

  it('treats Settings as a page over any tab', () => {
    expect(navDirection('/nearby', '/settings')).toBe('forward')
    expect(navDirection('/settings', '/community')).toBe('back')
  })

  it('crossfades between tabs', () => {
    expect(navDirection('/', '/nearby')).toBe('tab')
    expect(navDirection('/nearby', '/community')).toBe('tab')
    expect(navDirection('/community/posts', '/decide/result')).toBe('tab')
  })
})
