import { installationId } from '../lib/storage'
import { api } from './client'

/** `HalalPresenter` output. Render `display` verbatim: wording is decided on the server. */
export interface HalalInfo {
  status: 'certified' | 'muslim_friendly' | 'non_halal' | 'unknown'
  reviewState: 'clear' | 'under_review'
  display: {
    shortLabel: string
    longLabel: string
    tone: 'certified' | 'friendly' | 'neutral' | 'warning' | 'non_halal'
    action: 'help_verify' | 'help_reverify' | null
    verificationLabel: string | null
  }
  verification: {
    method: string
    evidenceSource: string
    authority: CertificationAuthority | null
    verifiedAt: string | null
    expiresAt: string | null
  } | null
  /** Approved vouches, newest first (up to 3). Optional: picks saved before this shipped lack it. */
  reports?: HalalReport[]
  historyCount: number
  /** The signed-in person's own latest vouch here (open, or decided in the last 30 days). */
  myReport?: MyHalalReport | null
  recentPickers: number
}

export type CertificationAuthority = 'jakim' | 'state_islamic_council' | 'muis' | 'bpjph' | 'other'

export interface HalalReport {
  id: number
  claim: HalalInfo['status'] | null
  resolvedStatus: HalalInfo['status'] | null
  isCurrent: boolean
  comment: string | null
  userName: string
  userTrusted: boolean
  approvedAt: string | null
  photos: { id: number; url: string; photoType: string }[]
}

export interface MyHalalReport {
  id: number
  status: 'draft' | 'pending' | 'changes_requested' | 'approved' | 'rejected'
  claim: HalalInfo['status'] | null
  reviewNote: string | null
  comment: string | null
  certificationAuthority: CertificationAuthority | null
  certificateNumber: string | null
  certificateExpiresAt: string | null
  photoCount: number
  certPhotoCount: number
  maxPhotos: number
}

export interface Photo {
  url: string
  authorAttributions: { name: string | null; profileUrl: string | null }[]
}

export interface Review {
  text: string | null
  rating: number | null
  authorName: string
  relativePublishTime: string | null
  googleMapsUrl: string | null
}

export interface Reason {
  family: 'match' | 'edge' | 'moment'
  key: string
  text: string
}

export interface ContextSignal {
  key: string
  label: string
  icon: string
  active: boolean
  ignored: boolean
  stale: boolean
}

/** A picked place. Everything after `halal` only comes from the Brain (v2), so it's all optional. */
export interface Recommendation {
  id: number
  name: string
  shareUrl: string
  headline: string
  foodCategory: string | null
  latitude: number
  longitude: number
  distanceKm: number
  rating: number | null
  priceLevel: number | null
  cuisines: string[]
  openStatus: 'open' | 'closed' | 'unknown'
  photos: Photo[]
  reviews: Review[]
  placeGoogleMapsUrl: string | null
  closesAt: string | null
  menuItems: { name: string; description: string | null; price: number | null; category: string | null }[]
  halal: HalalInfo
  reasons?: Reason[]
  decidingFactor?: string | null
  fit?: 'strong' | 'good' | 'wildcard'
  thinkingTrace?: string[]
  context?: ContextSignal[]
  hasWhatIf?: boolean
  canTune?: boolean
  fatigue?: boolean
}

/**
 * A live pick from Kakao Map (Korea, when MakanApa knows too few places). Kakao's terms allow
 * keeping only the id and link, so it's shown, never stored: no photos, tuning or saving.
 */
export interface KakaoPick {
  kakaoPlaceId: string
  kakaoUrl: string
  name: string
  cuisine: string | null
  address: string | null
  latitude: number
  longitude: number
  distanceKm: number
}

export interface Decision {
  decisionId: number
  clientToken: string
  algorithmVersion: string
  recommendation: Recommendation | null
  /** Set instead of `recommendation` for a Kakao pick. */
  kakaoPick?: KakaoPick | null
  /** Abroad cuisine moods only: whether anything nearby matched. Missing on older backends. */
  moodMatch?: { mood: string; matched: boolean } | null
}

const MOOD_CUISINES: Record<string, string> = {
  middle_eastern: 'Middle Eastern',
  indian: 'Indian',
  malay: 'Malaysian or Indonesian',
  chicken: 'fried chicken',
  korean: 'Korean',
  burger: 'burger',
  pizza: 'pizza',
}

/** "Nothing Indian nearby fits right now, …" when an abroad cuisine mood found nothing open that fits. */
export function moodMissNote(match: Decision['moodMatch']): string | null {
  const cuisine = match && !match.matched ? MOOD_CUISINES[match.mood] : undefined
  return cuisine ? `Nothing ${cuisine} nearby fits right now, so here’s something else.` : null
}

export type Lens = 'cheap_today' | 'treat_myself' | 'quick_one' | 'surprise_me' | 'community_favs'
export type TuneDirection = 'closer' | 'cheaper' | 'safer' | 'adventurous'
export type WhyNotReason = 'too_far' | 'too_pricey' | 'not_feeling_it' | 'ate_recently'
export type WhyNotDetail = 'too_heavy' | 'too_similar' | 'dont_like_cuisine' | 'just_not_today'
export type VibeTag = 'chill' | 'study' | 'student_budget' | 'hidden_gem' | 'late_night'
export type Interaction = 'directions_opened' | 'shared' | 'reasons_expanded'

export interface SoloRequest {
  latitude: number
  longitude: number
  /** 1–3; left out for "Anything". */
  budgetMax?: number
  maxDistanceKm: number
  /** One craving tag, or empty for "Anything" and typed cravings. */
  moods: string[]
  /** Typed craving; never sent together with moods. */
  craving?: string
  lens?: Lens
  halal: boolean
  ignoreContext: string[]
}

export interface TuneResponse {
  recommendation: Recommendation | null
  canSearchWider: boolean
  message?: string
  suggestedAdjustment?: { distanceKm: number; budgetMax: number | null }
}

export interface WhatIfRow {
  component: string
  label: string
  winner: { id: number; name: string | null }
}

const decisionCall = (decision: Pick<Decision, 'decisionId' | 'clientToken'>, path: string) => ({
  path: `decisions/${decision.decisionId}/${path}`,
  decisionToken: decision.clientToken,
})

export const decideApi = {
  solo: (request: SoloRequest) =>
    // kakao: this client can show a Kakao pick, so in Korea the server may answer with one.
    api<Decision>('recommendations/solo', { method: 'POST', body: { ...request, mode: 'normal', installationId: installationId(), kakao: true } }),

  saved: (request: { latitude: number; longitude: number; savedPlaceIds: number[]; halal: boolean; ignoreContext: string[] }) =>
    api<Decision>('recommendations/saved', { method: 'POST', body: { ...request, installationId: installationId() } }),

  reroll: (decision: Decision) => {
    const { path, decisionToken } = decisionCall(decision, 'reroll')
    return api<{ recommendation: Recommendation | null; kakaoPick?: KakaoPick | null }>(path, { method: 'POST', body: {}, decisionToken })
  },

  accept: (decision: Decision) => {
    const { path, decisionToken } = decisionCall(decision, 'accept')
    return api<{ accepted: boolean }>(path, { method: 'POST', body: {}, decisionToken })
  },

  tune: (decision: Decision, direction: TuneDirection) => {
    const { path, decisionToken } = decisionCall(decision, 'tune')
    return api<TuneResponse>(path, { method: 'POST', body: { direction }, decisionToken })
  },

  whatIf: (decision: Decision) => {
    const { path, decisionToken } = decisionCall(decision, 'what-if')
    return api<{ whatIf: WhatIfRow[] }>(path, { decisionToken })
  },

  choose: (decision: Decision, restaurantId: number) => {
    const { path, decisionToken } = decisionCall(decision, 'choose')
    return api<{ recommendation: Recommendation }>(path, { method: 'POST', body: { restaurantId }, decisionToken })
  },

  whyNot: (decision: Decision, reason: WhyNotReason, detail?: WhyNotDetail) => {
    const { path, decisionToken } = decisionCall(decision, 'why-not')
    return api<{ recorded: true }>(path, { method: 'POST', body: detail ? { reason, detail } : { reason }, decisionToken })
  },

  interaction: (decision: Decision, type: Interaction) => {
    const { path, decisionToken } = decisionCall(decision, 'interactions')
    return api<{ recorded: true }>(path, { method: 'POST', body: { type }, decisionToken })
  },

  vibeTag: (decision: Pick<Decision, 'decisionId' | 'clientToken'>, vibe: VibeTag) => {
    const { path, decisionToken } = decisionCall(decision, 'vibe-tag')
    return api<{ tagged: true }>(path, { method: 'POST', body: { vibe }, decisionToken })
  },

  context: (latitude: number, longitude: number, ignore: string[]) => {
    const query = new URLSearchParams({ latitude: String(latitude), longitude: String(longitude) })
    ignore.forEach((key) => query.append('ignore[]', key))
    return api<{ mealSlot: string; signals: ContextSignal[] }>(`context?${query}`)
  },

  seedTaste: (picks: string[]) => api('me/selera/seed', { method: 'POST', body: { picks } }),

  shareEvent: (restaurantId: number) => api(`restaurants/${restaurantId}/share-events`, { method: 'POST', body: {} }),

  save: (restaurantId: number) =>
    api<{ saved: boolean }>(`restaurants/${restaurantId}/save`, { method: 'POST', body: { installationId: installationId() } }),

  unsave: (restaurantId: number) =>
    api<{ saved: boolean }>(`restaurants/${restaurantId}/unsave`, { method: 'POST', body: { installationId: installationId() } }),
}
