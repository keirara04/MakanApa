import { describe, expect, it } from 'vitest'
import { missNote } from './decide'

describe('missNote', () => {
  it('names the mood that matched nothing', () => {
    expect(missNote({ moodMatch: { mood: 'indian', label: 'Indian', matched: false } })).toBe('Nothing Indian nearby fits right now, so here’s something else.')
  })

  it('falls back to the typed craving', () => {
    expect(missNote({ moodMatch: null, craving: { query: 'sushi', matched: false } })).toBe('Nothing sushi nearby fits right now, so here’s something else.')
  })

  it('says nothing when it matched', () => {
    expect(missNote({ moodMatch: { mood: 'indian', label: 'Indian', matched: true } })).toBeNull()
    expect(missNote(null)).toBeNull()
  })
})
