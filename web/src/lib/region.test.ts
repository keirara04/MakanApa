import { describe, expect, it } from 'vitest'
import { priceLevelFromSpend, spendFromPriceLevel } from '../api/submissions'
import { budgetAmount, distanceLabel, menuPrice, priceLabel, withinLabel } from './presentation'
import { regionAt, regionFromTimeZone } from './region'

describe('region', () => {
  it('places coordinates in the same boxes as the API', () => {
    expect(regionAt(3.139, 101.6869)).toBe('MY')
    expect(regionAt(37.5894, 127.0276)).toBe('KR')
    expect(regionAt(40.7982, -77.8599)).toBe('US')
    expect(regionAt(-33.87, 151.21)).toBe('OTHER')
    expect(regionAt(1.2903, 103.852)).toBe('OTHER') // Singapore
    expect(regionAt(43.6532, -79.3832)).toBe('OTHER') // Toronto
    expect(regionAt(49.2827, -123.1207)).toBe('OTHER') // Vancouver
    expect(regionAt(42.8864, -78.8784)).toBe('US') // Buffalo
  })

  it('guesses from the time zone before any fix', () => {
    expect(regionFromTimeZone('Asia/Seoul')).toBe('KR')
    expect(regionFromTimeZone('Asia/Kuching')).toBe('MY')
    expect(regionFromTimeZone('America/Indiana/Indianapolis')).toBe('US')
    expect(regionFromTimeZone('Pacific/Honolulu')).toBe('US')
    expect(regionFromTimeZone('Europe/London')).toBe('OTHER')
  })

  it('labels prices and distances per region', () => {
    expect(['MY', 'KR', 'US', 'OTHER'].map((region) => budgetAmount(2, region as never))).toEqual(['~RM20', '~₩18,000', '~$25', '$$'])
    expect(priceLabel(3, { latitude: 37.5, longitude: 127 })).toBe('≈ ₩30,000+/person')
    expect(priceLabel(1, { latitude: 3.1, longitude: 101.7 })).toBe('≈ RM10/person')
    expect(menuPrice(12000, { latitude: 37.5, longitude: 127 })).toBe('₩12,000')
    expect(menuPrice(9, { latitude: 40.8, longitude: -77.9 })).toBe('$9.00')
    expect(withinLabel(5, 'US')).toBe('Within 3 mi')
    expect(distanceLabel(2, 'US')).toBe('1.2 mi')
    expect(distanceLabel(2, 'MY')).toBe('2.0 km')
  })

  it('buckets spend in won in Korea', () => {
    expect(priceLevelFromSpend('11999', 'KR')).toBe(1)
    expect(priceLevelFromSpend('25000', 'KR')).toBe(3)
    expect(spendFromPriceLevel(2, 'KR')).toBe('18000')
    expect(spendFromPriceLevel(3, 'US')).toBe('40')
  })
})
