/**
 * How a pick is described, ported from the iOS app so both say the same thing
 * (ResultView details line, PricePresentation, Copy.homeGreeting).
 */
import type { Recommendation } from '../api/decide'

export const BUDGETS = [
  { value: 1, amount: '~RM10', title: 'Save a bit', image: '/choices/budget-save.svg' },
  { value: 2, amount: '~RM20', title: 'Normal', image: '/choices/budget-normal.svg' },
  { value: 3, amount: '~RM35+', title: 'Treat myself', image: '/choices/budget-treat.svg' },
] as const

export const DISTANCES = [
  { km: 1, title: 'Within 1 km', subtitle: 'Close by', short: '5 min', image: '/choices/distance-near.svg' },
  { km: 2, title: 'Within 2 km', subtitle: 'Short trip', short: '10 min', image: '/choices/distance-walk.svg' },
  { km: 5, title: 'Within 5 km', subtitle: 'Worth the trip', short: 'Don’t mind', image: '/choices/distance-car.svg' },
] as const

export function budgetAmount(budget: number | null | undefined): string | null {
  return BUDGETS.find((option) => option.value === budget)?.amount ?? null
}

export function distanceShort(km: number): string {
  return DISTANCES.find((option) => option.km === km)?.short ?? `${km} km`
}

/** `≈ RM20/person` for price levels 1–3, nothing otherwise. */
export function priceLabel(priceLevel: number | null): string | null {
  return priceLevel === 1 ? '≈ RM10/person' : priceLevel === 2 ? '≈ RM20/person' : priceLevel === 3 ? '≈ RM35+/person' : null
}

/** Walking minutes under 1.5 km (about 12 minutes a km), kilometres beyond. */
export function distanceLabel(km: number): string {
  return km < 1.5 ? `${Math.max(1, Math.round(km * 12))} min walk` : `${km.toFixed(1)} km`
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
  return [kindLabel(pick), priceLabel(pick.priceLevel), distanceLabel(pick.distanceKm), hoursLabel(pick)].filter(
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
  if (diff < 7) return date.toLocaleDateString('en-MY', { weekday: 'long' })
  return date.toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })
}
