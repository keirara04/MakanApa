import { describe, expect, it } from 'vitest'
import { changedFields, priceLevelFromSpend, spendFromPriceLevel, type DetailsForm } from './submissions'

describe('spend and price level', () => {
  it('buckets ringgit into tiers like iOS', () => {
    expect(priceLevelFromSpend('')).toBeNull()
    expect(priceLevelFromSpend('0')).toBeNull()
    expect(priceLevelFromSpend('abc')).toBeNull()
    expect(priceLevelFromSpend('14.99')).toBe(1)
    expect(priceLevelFromSpend('15')).toBe(2)
    expect(priceLevelFromSpend('29')).toBe(2)
    expect(priceLevelFromSpend('30')).toBe(3)
  })

  it('round-trips the prefilled spend', () => {
    for (const level of [1, 2, 3]) expect(priceLevelFromSpend(spendFromPriceLevel(level))).toBe(level)
    expect(spendFromPriceLevel(null)).toBe('')
  })
})

describe('changedFields', () => {
  const base: DetailsForm = { name: 'Pelita', address: '', foodCategory: 'Mamak', spend: '10', phone: '', instagramHandle: '', tiktokHandle: '', websiteUrl: '', menuItems: [] }

  it('names only what changed, in the server’s field names', () => {
    expect(changedFields(base, base)).toEqual([])
    expect(changedFields(base, { ...base, foodCategory: 'Cafe', spend: '20' })).toEqual(['food_category', 'price_level'])
    expect(changedFields(base, { ...base, menuItems: [{ name: 'Roti canai', price: 1.5 }] })).toEqual(['menu_items'])
  })
})
