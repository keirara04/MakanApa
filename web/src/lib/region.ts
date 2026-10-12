import { getStored, setStored, useStored } from './storage'

/**
 * Where the person is, for presentation only (prices, distances, moods). Same boxes and order
 * as the API's App\Support\Region. The wire format never changes with it.
 */
export type Region = 'MY' | 'KR' | 'US' | 'OTHER'

/**
 * Peninsular Malaysia stops short of southern Thailand and Batam; Singapore, Toronto,
 * Ottawa–Montreal–Quebec and everything north of 49° (Vancouver) are cut out of their boxes.
 */
export function regionAt(latitude: number, longitude: number): Region {
  const within = (south: number, north: number, west: number, east: number) =>
    latitude >= south && latitude <= north && longitude >= west && longitude <= east
  if (within(1.15, 1.47, 103.6, 104.1)) return 'OTHER' // Singapore
  if (within(1.2, 6.8, 99.5, 104.6) || within(0.8, 7.5, 109.5, 119.5)) return 'MY'
  if (within(33, 38.7, 124.5, 131)) return 'KR'
  if (within(43.4, 44.6, -80.6, -78.4) || within(45, 46.9, -76.5, -71)) return 'OTHER' // Toronto, Ottawa–Montreal–Quebec
  if (within(24.4, 49, -125, -66.9)) return 'US'
  return 'OTHER'
}

const US_ZONES = ['New_York', 'Chicago', 'Denver', 'Phoenix', 'Los_Angeles', 'Anchorage', 'Detroit', 'Boise'].map((city) => `America/${city}`)

/** Before any location fix: a guess from the device time zone. */
export function regionFromTimeZone(zone = Intl.DateTimeFormat().resolvedOptions().timeZone): Region {
  if (zone === 'Asia/Seoul') return 'KR'
  if (zone === 'Asia/Kuala_Lumpur' || zone === 'Asia/Kuching') return 'MY'
  if (US_ZONES.includes(zone) || zone === 'Pacific/Honolulu' || /^America\/(Indiana|Kentucky|North_Dakota)\//.test(zone)) return 'US'
  return 'OTHER'
}

/** Region of the last location fix (kept across launches), else the time-zone guess. */
export function currentRegion(): Region {
  return getStored('region') ?? regionFromTimeZone()
}

/** currentRegion(), re-rendering when a new fix moves it. */
export function useRegion(): Region {
  return useStored('region') ?? regionFromTimeZone()
}

/** Called with every device location fix. */
export function noteLocation(latitude: number, longitude: number): void {
  const region = regionAt(latitude, longitude)
  if (getStored('region') !== region) setStored('region', region)
}

/** Region of a place when it has coordinates, else the person's. */
export function regionOf(place?: { latitude?: number | null; longitude?: number | null }): Region {
  const { latitude, longitude } = place ?? {}
  return Number.isFinite(latitude) && Number.isFinite(longitude) ? regionAt(latitude!, longitude!) : currentRegion()
}

export const CURRENCY: Record<Region, string> = { MY: 'RM', KR: '₩', US: '$', OTHER: '' }

/** The Nearby "cheap" chip (price level 2 or less) and the area summary count. */
export const BUDGET_CHIP: Record<Region, string> = { MY: '≤ RM20', KR: '≤ ₩18,000', US: '≤ $25', OTHER: '$$ or less' }
export const BUDGET_COUNT: Record<Region, string> = { MY: 'under RM20', KR: 'under ₩18,000', US: 'under $25', OTHER: 'budget-friendly' }

export const SEARCH_EXAMPLES: Record<Region, string[]> = {
  MY: ['nasi lemak', 'mamak', 'coffee', 'chicken rice'],
  KR: ['kebab', 'halal', 'indian', 'coffee'],
  US: ['halal cart', 'indian', 'coffee', 'kebab'],
  OTHER: ['kebab', 'halal', 'indian', 'coffee'],
}

/** Where the pin picker opens without a location: Kuala Lumpur, Seoul (Korea Univ), State College (Penn State). */
export const PIN_DEFAULT: Record<Region, { latitude: number; longitude: number }> = {
  MY: { latitude: 3.139, longitude: 101.6869 },
  KR: { latitude: 37.5894, longitude: 127.0276 },
  US: { latitude: 40.7982, longitude: -77.8599 },
  OTHER: { latitude: 3.139, longitude: 101.6869 },
}

/** Form examples and suggestions that would otherwise only make sense in Malaysia. */
export const FORM_EXAMPLES: Record<
  Region,
  { placeName: string; category: string; categories: string[]; phone: string; dish: string; spend: string; university: string; area: string }
> = {
  MY: {
    placeName: 'e.g. Restoran Nasi Kandar Pelita',
    category: 'e.g. Mamak',
    categories: ['Mamak', 'Nasi campur', 'Kopitiam', 'Cafe', 'Western', 'Chinese', 'Indian', 'Warung', 'Dessert', 'Street food'],
    phone: '012-345 6789',
    dish: 'Nasi lemak ayam',
    spend: '15',
    university: 'e.g. Universiti Malaya',
    area: 'e.g. Shah Alam, Cheras, your neighbourhood',
  },
  KR: {
    placeName: 'e.g. Anam Kebab House',
    category: 'e.g. Middle Eastern',
    categories: ['Halal', 'Middle Eastern', 'Indian', 'Korean', 'Fried chicken', 'Cafe', 'Dessert', 'Street food'],
    phone: '010-1234-5678',
    dish: 'Chicken kebab',
    spend: '12000',
    university: 'e.g. Korea University',
    area: 'e.g. Anam, Itaewon, your neighbourhood',
  },
  US: {
    placeName: 'e.g. Sultan Halal Grill',
    category: 'e.g. Middle Eastern',
    categories: ['Halal', 'Middle Eastern', 'Indian', 'Fried chicken', 'Burgers', 'Pizza', 'Cafe', 'Dessert'],
    phone: '(814) 555-0123',
    dish: 'Chicken over rice',
    spend: '15',
    university: 'e.g. Penn State',
    area: 'e.g. State College, your neighbourhood',
  },
  OTHER: {
    placeName: 'e.g. Istanbul Kebab House',
    category: 'e.g. Middle Eastern',
    categories: ['Halal', 'Middle Eastern', 'Indian', 'Fried chicken', 'Burgers', 'Pizza', 'Cafe', 'Dessert'],
    phone: 'Phone number',
    dish: 'Chicken kebab',
    spend: '15',
    university: 'e.g. your university',
    area: 'e.g. your neighbourhood',
  },
}
