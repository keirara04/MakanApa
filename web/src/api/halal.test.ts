import { describe, expect, it } from 'vitest'
import type { MyHalalReport } from './decide'
import { halalDate, photoLimits } from './halal'

describe('photoLimits', () => {
  it('splits the free slots like iOS', () => {
    expect(photoLimits('certified', null)).toEqual({ slots: 5, cert: 2, other: 3 })
    expect(photoLimits('muslim_friendly', null)).toEqual({ slots: 5, cert: 0, other: 3 })
  })

  it('counts photos already attached to a report being finished', () => {
    const existing = { photoCount: 4, certPhotoCount: 1, maxPhotos: 5 } as MyHalalReport
    expect(photoLimits('certified', existing)).toEqual({ slots: 1, cert: 1, other: 0 })
    expect(photoLimits('non_halal', { ...existing, photoCount: 5 })).toEqual({ slots: 0, cert: 0, other: 0 })
  })
})

describe('halalDate', () => {
  it('reads plain dates as local days', () => {
    expect(halalDate('2027-03-01')).toBe('1 Mar 2027')
    expect(halalDate('nonsense')).toBe('nonsense')
  })
})
