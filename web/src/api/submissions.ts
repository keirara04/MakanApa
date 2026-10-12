import { currentRegion, type Region } from '../lib/region'
import { api } from './client'

/** Adding a place or suggesting an edit. Every change is a submission an admin reviews. */

export interface MenuItem {
  name: string
  price: number | null
}

export interface ExistingPlace {
  id: number
  name: string
  address: string | null
  foodCategory: string | null
  priceLevel: number | null
  latitude: number
  longitude: number
  distanceKm: number | null
}

export interface GooglePlace {
  googlePlaceId: string
  name: string
  address: string | null
  foodCategory: string | null
  priceLevel: number | null
  rating: number | null
  latitude: number
  longitude: number
  distanceKm: number
}

export type SubmissionType = 'new_place' | 'edit_place' | 'halal_report' | 'owner_claim' | 'closure' | 'reopen'
export type SubmissionStatus = 'draft' | 'pending' | 'approved' | 'changes_requested' | 'rejected' | 'cancelled'
export type LocationSource = 'google' | 'current_location' | 'map_pin'

export interface Submission {
  id: number
  restaurantId: number | null
  submissionType: SubmissionType
  name: string
  foodCategory: string | null
  halalClaim: string | null
  halalResolvedStatus: string | null
  status: SubmissionStatus
  reviewNote: string | null
  createdAt: string | null
}

/** The editable details, shared by create and update. */
export interface SubmissionDetails {
  name: string
  address: string | null
  foodCategory: string | null
  priceLevel: number | null
  phone: string | null
  instagramHandle: string | null
  tiktokHandle: string | null
  websiteUrl: string | null
  menuItems: MenuItem[] | null
  notes: string | null
  changedFields: string[]
}

export interface NewSubmission extends SubmissionDetails {
  submissionType: 'new_place' | 'edit_place'
  sourceType: 'google' | 'manual'
  googlePlaceId: string | null
  restaurantId: number | null
  latitude: number
  longitude: number
  locationSource: LocationSource
}

export const submissionsApi = {
  /** MakanApa places plus Google's (Google only when a location is sent). Shares the places limit. */
  search: (query: string, coords: { latitude: number; longitude: number } | null, signal?: AbortSignal) => {
    const params = new URLSearchParams({ query })
    if (coords) {
      params.set('latitude', String(coords.latitude))
      params.set('longitude', String(coords.longitude))
    }
    return api<{ existing: ExistingPlace[]; google: GooglePlace[] }>(`community/places/search?${params}`, { signal })
  },
  /** Starts as a draft, invisible to reviewers until submit(). */
  create: (body: NewSubmission) => api<{ submission: Submission }>('community/submissions', { method: 'POST', body }),
  /** On a changes_requested submission this also sends it back for review. */
  update: (id: number, body: SubmissionDetails) => api<{ submission: Submission }>(`community/submissions/${id}`, { method: 'PATCH', body }),
  cancel: (id: number, keepalive = false) => api<{ cancelled: true }>(`community/submissions/${id}`, { method: 'DELETE', keepalive }),
  submit: (id: number) => api<{ submission: Submission }>(`community/submissions/${id}/submit`, { method: 'POST' }),
  uploadPhoto: (id: number, photo: Blob, photoType = 'other') => {
    const form = new FormData()
    form.append('photo', photo, 'photo.jpg')
    form.append('photoType', photoType)
    return api<{ photo: { id: number; photoType: string } }>(`community/submissions/${id}/photos`, { method: 'POST', body: form })
  },
  mine: () => api<{ submissions: Submission[] }>('community/submissions/mine'),
}

/** People think in local money, the API in tiers 1–3 (iOS derivedPriceLevel). */
export function priceLevelFromSpend(spend: string, region = currentRegion()): number | null {
  const value = Number(spend)
  if (!spend.trim() || !Number.isFinite(value) || value <= 0) return null
  const [cheap, mid] = region === 'KR' ? [12000, 25000] : [15, 30]
  return value < cheap ? 1 : value < mid ? 2 : 3
}

const TYPICAL_SPEND: Record<Region, string[]> = { MY: ['10', '20', '35'], KR: ['10000', '18000', '30000'], US: ['12', '25', '40'], OTHER: ['12', '25', '40'] }

/** A tier back to a typical spend, to prefill the field. */
export function spendFromPriceLevel(level: number | null, region = currentRegion()): string {
  return level === 1 || level === 2 || level === 3 ? TYPICAL_SPEND[region][level - 1] : ''
}

export interface DetailsForm {
  name: string
  address: string
  foodCategory: string
  spend: string
  phone: string
  instagramHandle: string
  tiktokHandle: string
  websiteUrl: string
  menuItems: MenuItem[]
}

const FIELD_KEYS: [keyof DetailsForm, string][] = [
  ['name', 'name'],
  ['address', 'address'],
  ['foodCategory', 'food_category'],
  ['spend', 'price_level'],
  ['phone', 'phone'],
  ['instagramHandle', 'instagram_handle'],
  ['tiktokHandle', 'tiktok_handle'],
  ['websiteUrl', 'website_url'],
  ['menuItems', 'menu_items'],
]

/** A field as it's sent: trimmed text, spend as its tier. */
function sent(form: DetailsForm, key: keyof DetailsForm): unknown {
  if (key === 'spend') return priceLevelFromSpend(form.spend)
  const value = form[key]
  return typeof value === 'string' ? value.trim() : value
}

/**
 * The server's names for the fields that differ from what the place had (iOS
 * computeChangedFields), compared as sent: "20" to "25" is the same tier, a trailing space
 * is no change.
 */
export function changedFields(original: DetailsForm, current: DetailsForm): string[] {
  return FIELD_KEYS.filter(([key]) => JSON.stringify(sent(original, key)) !== JSON.stringify(sent(current, key))).map(([, field]) => field)
}
