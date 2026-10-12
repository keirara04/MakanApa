import { beforeEach, describe, expect, it } from 'vitest'
import type { Recommendation } from '../api/decide'
import { directionsUrl } from './maps'
import { categoryLabel, detailsLine, distanceLabel, kindLabel, mealGreeting, priceLabel } from './presentation'
import { setStored } from './storage'

beforeEach(() => setStored('region', 'MY'))

const pick = (overrides: Partial<Recommendation> = {}) =>
  ({ foodCategory: 'ramen', cuisines: ['japanese'], priceLevel: 2, distanceKm: 0.4, openStatus: 'open', closesAt: '10:00 PM', ...overrides }) as Recommendation

describe('pick presentation (matches iOS)', () => {
  it('describes price, walk and hours like the app', () => {
    expect(detailsLine(pick())).toEqual(['Ramen', '≈ RM20/person', '5 min walk', 'Open until 10:00 PM'])
    expect(detailsLine(pick({ priceLevel: 4, distanceKm: 3.26, openStatus: 'unknown' }))).toEqual(['Ramen', '3.3 km'])
  })

  it('uses walking minutes only under 1.5 km, never zero', () => {
    expect(distanceLabel(0.01)).toBe('1 min walk')
    expect(distanceLabel(1.49)).toBe('18 min walk')
    expect(distanceLabel(1.5)).toBe('1.5 km')
  })

  it('names the kind of place from its category or first cuisine', () => {
    expect(kindLabel(pick({ foodCategory: 'fast_food' }))).toBe('Fast Food')
    expect(kindLabel(pick({ foodCategory: 'mamak', cuisines: ['malaysian_indian'] }))).toBe('Malaysian Indian')
    expect(categoryLabel('japanese_restaurant')).toBe('Japanese')
    expect(categoryLabel('restaurant')).toBeNull()
    expect(priceLabel(0)).toBeNull()
  })

  it('greets by mealtime', () => {
    expect([7, 12, 16, 19, 23, 2].map(mealGreeting)).toEqual(['Breakfast?', 'Lunch?', 'Tea time?', 'Dinner?', 'Supper?', 'Supper?'])
  })

  it('builds directions for each maps app', () => {
    const place = { name: 'Kedai Kopi', latitude: 2.9, longitude: 101.7, placeGoogleMapsUrl: null }
    expect(directionsUrl(place, 'google')).toBe('https://www.google.com/maps/search/?api=1&query=2.9,101.7')
    expect(directionsUrl({ ...place, placeGoogleMapsUrl: 'https://maps.google.com/?cid=1' }, 'google')).toBe('https://maps.google.com/?cid=1')
    expect(directionsUrl(place, 'waze')).toBe('https://waze.com/ul?ll=2.9,101.7&navigate=yes')
    expect(directionsUrl(place, 'naver')).toContain('query=Kedai%20Kopi')
  })
})
