import { describe, expect, it } from 'vitest'
import type { MyHalalReport } from './decide'
import { attachedPhotos, halalDate, photoLimits } from './halal'

describe('photoLimits', () => {
  it('splits the free slots like iOS', () => {
    expect(photoLimits('certified', null)).toEqual({ slots: 5, cert: 2, other: 3 })
    expect(photoLimits('muslim_friendly', null)).toEqual({ slots: 5, cert: 0, other: 3 })
  })

  it('counts photos already attached to a report being finished', () => {
    const existing = { photoCount: 4, certPhotoCount: 1, maxPhotos: 5 } as MyHalalReport
    expect(photoLimits('certified', existing)).toEqual({ slots: 1, cert: 1, other: 0 })
    expect(photoLimits('non_halal', { ...existing, photoCount: 5 })).toEqual({ slots: 1, cert: 0, other: 1 })
  })

  it('stops counting certificate photos once the claim is not certified', () => {
    const existing = { photoCount: 3, certPhotoCount: 2, maxPhotos: 5 } as MyHalalReport
    expect(attachedPhotos('certified', existing)).toBe(3)
    expect(attachedPhotos('muslim_friendly', existing)).toBe(1)
  })
})

describe('halalDate', () => {
  it('reads plain dates as local days', () => {
    expect(halalDate('2027-03-01')).toBe('1 Mar 2027')
    expect(halalDate('nonsense')).toBe('nonsense')
  })
})
