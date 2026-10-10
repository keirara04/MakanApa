import { describe, expect, it } from 'vitest'
import { boxAround, centreOf, distanceKm, matchesFilters, type NearbyFilters, type NearbyPlace } from './nearby'

const filters: NearbyFilters = { openNow: false, budgetMax: null, minRating: null, mode: 'normal', vibe: null, halal: false }
const place = (overrides: Partial<NearbyPlace> = {}): NearbyPlace => ({ id: 1, name: 'A', rating: 4.6, priceLevel: 2, latitude: 0, longitude: 0, openStatus: 'open', ...overrides })

describe('nearby helpers', () => {
  it('builds a box whose centre-to-corner distance stays under 1 km (one Google tile)', () => {
    const box = boxAround(2.929, 101.7775)
    const centre = centreOf(box)
    expect(centre.latitude).toBeCloseTo(2.929, 6)
    expect(distanceKm(centre, { latitude: box.north, longitude: box.east })).toBeLessThan(1)
    expect(distanceKm(centre, { latitude: box.north, longitude: box.east })).toBeGreaterThan(0.95)
  })

  it('applies the chips like iOS filteredPlaces', () => {
    expect(matchesFilters(place({ openStatus: 'unknown' }), { ...filters, openNow: true })).toBe(false)
    // Unknown price passes the budget chip; a known higher price doesn't.
    expect(matchesFilters(place({ priceLevel: null }), { ...filters, budgetMax: 2 })).toBe(true)
    expect(matchesFilters(place({ priceLevel: 3 }), { ...filters, budgetMax: 2 })).toBe(false)
    // Unrated never passes the rating chip.
    expect(matchesFilters(place({ rating: null }), { ...filters, minRating: 4.5 })).toBe(false)
    expect(matchesFilters(place({ rating: 4.5 }), { ...filters, minRating: 4.5 })).toBe(true)
  })
})
