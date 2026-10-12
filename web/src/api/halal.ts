import { api } from './client'
import type { CertificationAuthority, HalalInfo, MyHalalReport } from './decide'

/** A vouch or an ownership claim is a submission: open it, attach photos, then submit it. */

export type HalalClaim = Exclude<HalalInfo['status'], 'unknown'>

/** Same wording as iOS HalalStatus.pickerLabel. */
export const CLAIM_LABELS: Record<HalalInfo['status'], string> = {
  certified: 'Halal (has certificate)',
  muslim_friendly: 'No certificate (community notes)',
  non_halal: 'Not halal',
  unknown: 'Unknown',
}

export const AUTHORITY_LABELS: Record<CertificationAuthority, string> = {
  jakim: 'JAKIM',
  state_islamic_council: 'State Islamic council (JAIN/MAIN)',
  muis: 'MUIS',
  bpjph: 'BPJPH',
  other: 'Other',
}

export interface HistoryEntry {
  id: number
  status: HalalInfo['status']
  state: string
  method: string
  evidenceSource: string
  authority: CertificationAuthority | null
  summary: string | null
  effectiveFrom: string | null
  effectiveUntil: string | null
}

export const halalApi = {
  /** Reopens your open report for this place if there is one (200), or starts one (201). */
  report: (
    restaurantId: number,
    body: { claim: HalalClaim; comment: string | null; certificationAuthority: CertificationAuthority | null; certificateNumber: string | null; certificateExpiresAt: string | null },
  ) => api<{ submission: { id: number } }>(`restaurants/${restaurantId}/halal-reports`, { method: 'POST', body }),
  ownerClaim: (restaurantId: number, body: { contactPhone: string; notes: string | null }) =>
    api<{ submission: { id: number } }>(`restaurants/${restaurantId}/owner-claim`, { method: 'POST', body }),
  history: (restaurantId: number, page: number) => api<{ entries: HistoryEntry[]; nextPage: number | null }>(`restaurants/${restaurantId}/halal/history?page=${page}`),
}

/**
 * How many photos of each kind still fit (iOS HalalReportSheet): the server caps photos per
 * report, and photos from an earlier attempt already count. Certificate photos only for a
 * certified claim, at most 2; the rest at most 3.
 */
export function photoLimits(claim: HalalClaim, existing: MyHalalReport | null): { slots: number; cert: number; other: number } {
  const slots = Math.max(0, (existing?.maxPhotos ?? 5) - attachedPhotos(claim, existing))
  const cert = claim === 'certified' ? Math.min(2, slots) : 0
  return { slots, cert, other: Math.min(3, slots - cert) }
}

/** Photos already on the report that will still count: the server drops certificate photos when the claim stops being "certified". */
export function attachedPhotos(claim: HalalClaim, existing: MyHalalReport | null): number {
  if (!existing) return 0
  return claim === 'certified' ? existing.photoCount : existing.photoCount - existing.certPhotoCount
}

/** `2027-03-01` or an ISO timestamp, as "1 Mar 2027". */
export function halalDate(raw: string): string {
  const date = new Date(raw.length === 10 ? `${raw}T00:00:00` : raw)
  return Number.isNaN(date.getTime()) ? raw : date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}
