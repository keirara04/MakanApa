/**
 * How a pick is described, ported from the iOS app so both say the same thing
 * (ResultView details line, PricePresentation, Copy.homeGreeting).
 */
import type { Recommendation } from '../api/decide'
import { currentRegion, CURRENCY, regionOf, type Region } from './region'

export const BUDGETS = [
  { value: 1, title: 'Save a bit', image: '/choices/budget-save.svg' },
  { value: 2, title: 'Normal', image: '/choices/budget-normal.svg' },
  { value: 3, title: 'Treat myself', image: '/choices/budget-treat.svg' },
] as const

export const DISTANCES = [
  { km: 1, title: 'Within 1 km', subtitle: 'Close by', short: '5 min', image: '/choices/distance-near.svg' },
  { km: 2, title: 'Within 2 km', subtitle: 'Short trip', short: '10 min', image: '/choices/distance-walk.svg' },
  { km: 5, title: 'Within 5 km', subtitle: 'Worth the trip', short: 'Don’t mind', image: '/choices/distance-car.svg' },
] as const

/** Budget tile amounts for price levels 1–3. */
const BUDGET_AMOUNTS: Record<Region, readonly string[]> = {
  MY: ['~RM10', '~RM20', '~RM35+'],
  KR: ['~₩10,000', '~₩18,000', '~₩30,000+'],
  US: ['~$12', '~$25', '~$40+'],
  OTHER: ['$', '$$', '$$$'],
}

/** Approximate spend for price levels 1–3. */
const SPEND: Record<Region, readonly string[]> = {
  MY: ['≈ RM10/person', '≈ RM20/person', '≈ RM35+/person'],
  KR: ['≈ ₩10,000/person', '≈ ₩18,000/person', '≈ ₩30,000+/person'],
  US: ['≈ $12/person', '≈ $25/person', '≈ $40+/person'],
  OTHER: ['Budget', 'Mid-range', 'Pricey'],
}

const MILES_PER_KM = 0.621371
/** The wizard's 1/2/5 km in rounded miles. */
const MILES: Record<number, string> = { 1: '0.6', 2: '1.2', 5: '3' }

export function budgets(region = currentRegion()) {
  return BUDGETS.map((option, index) => ({ ...option, amount: BUDGET_AMOUNTS[region][index] }))
}

export function distances(region = currentRegion()) {
  return DISTANCES.map((option) => ({ ...option, title: withinLabel(option.km, region) }))
}

export function budgetAmount(budget: number | null | undefined, region = currentRegion()): string | null {
  return budgets(region).find((option) => option.value === budget)?.amount ?? null
}

export function distanceShort(km: number): string {
  return DISTANCES.find((option) => option.km === km)?.short ?? distanceAmount(km)
}

/** A radius as asked: "2 km", or "1.2 mi" in the US. */
export function distanceAmount(km: number, region = currentRegion()): string {
  return region === 'US' ? `${MILES[km] ?? Number((km * MILES_PER_KM).toFixed(1))} mi` : `${km} km`
}

export function withinLabel(km: number, region = currentRegion()): string {
  return `Within ${distanceAmount(km, region)}`
}

/** A measured distance: "3.3 km", or "2.0 mi" in the US. */
export function kmLabel(km: number, region = currentRegion()): string {
  return region === 'US' ? `${(km * MILES_PER_KM).toFixed(1)} mi` : `${km.toFixed(1)} km`
}

/** `≈ RM20/person` for price levels 1–3, nothing otherwise. Priced in the place's own region when it has coordinates. */
export function priceLabel(priceLevel: number | null, place?: { latitude?: number | null; longitude?: number | null }): string | null {
  return priceLevel === 1 || priceLevel === 2 || priceLevel === 3 ? SPEND[regionOf(place)][priceLevel - 1] : null
}

/** A menu price in the place's currency: "RM12.50", "₩12,000", "$9.00", or a bare "9.00". */
export function menuPrice(price: number, place?: { latitude?: number | null; longitude?: number | null }): string {
  const region = regionOf(place)
  return region === 'KR' ? `₩${Math.round(price).toLocaleString('en-US')}` : `${CURRENCY[region]}${price.toFixed(2)}`
}

/** Walking minutes under 1.5 km (about 12 minutes a km), kilometres (miles in the US) beyond. */
export function distanceLabel(km: number, region = currentRegion()): string {
  return km < 1.5 ? `${Math.max(1, Math.round(km * 12))} min walk` : kmLabel(km, region)
}

export function hoursLabel(pick: Pick<Recommendation, 'openStatus' | 'closesAt'>): string | null {
  if (pick.openStatus === 'open') return pick.closesAt ? `Open until ${pick.closesAt}` : 'Open'
  if (pick.openStatus === 'closed') return 'Closed'
  return null
}

const KINDS: Record<string, string> = {
  burger: 'Burger', chicken: 'Chicken', pizza: 'Pizza', sandwich: 'Sandwich', ramen: 'Ramen', sushi: 'Sushi',
  seafood: 'Seafood', steak: 'Steak', bbq: 'BBQ', bakery: 'Bakery', dessert: 'Dessert', cafe: 'Cafe',
  drinks: 'Drinks', breakfast: 'Breakfast', fast_food: 'Fast Food',
}

function titleCase(slug: string): string {
  return slug.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

/** "Ramen", "Fast Food", or the first cuisine. */
export function kindLabel(pick: Pick<Recommendation, 'foodCategory' | 'cuisines'>): string | null {
  if (pick.foodCategory && KINDS[pick.foodCategory]) return KINDS[pick.foodCategory]
  return pick.cuisines[0] ? titleCase(pick.cuisines[0]) : null
}

/** For Recent rows: "Mamak", "Japanese"; nothing for plain "restaurant". */
export function categoryLabel(foodCategory: string | null): string | null {
  if (!foodCategory) return null
  const label = foodCategory.replace(/_restaurant$/, '')
  return label === 'restaurant' ? null : titleCase(label)
}

/** The line under the name: kind, price, distance, hours, whichever are known. */
export function detailsLine(pick: Recommendation): string[] {
  return [kindLabel(pick), priceLabel(pick.priceLevel, pick), distanceLabel(pick.distanceKm), hoursLabel(pick)].filter(
    (part): part is string => Boolean(part),
  )
}

export function mealGreeting(hour: number): string {
  if (hour >= 5 && hour <= 10) return 'Breakfast?'
  if (hour >= 11 && hour <= 14) return 'Lunch?'
  if (hour >= 15 && hour <= 17) return 'Tea time?'
  if (hour >= 18 && hour <= 21) return 'Dinner?'
  return 'Supper?'
}

/** "Today", "Yesterday", a weekday within the week, else "3 Oct". */
export function relativeDay(timestamp: number, now = Date.now()): string {
  const day = (time: number) => Math.floor((time - new Date(time).getTimezoneOffset() * 60_000) / 86_400_000)
  const diff = day(now) - day(timestamp)
  if (diff <= 0) return 'Today'
  if (diff === 1) return 'Yesterday'
  const date = new Date(timestamp)
  if (diff < 7) return date.toLocaleDateString(undefined, { weekday: 'long' })
  return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
}
