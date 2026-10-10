import { api } from './client'
import type { User } from './types'

export type SeleraStage = 'starting' | 'learning' | 'knowing' | 'strong'

export interface SeleraTrait {
  key: string
  icon: string
  label: string
  strength: 'strong' | 'medium' | 'emerging'
  evidence: string
}

export interface Selera {
  stage: SeleraStage
  signalCount: number
  traits: SeleraTrait[]
  startingPicks: string[]
  constraints: { key: 'halal' | 'budget' | 'distance'; label: string; icon: string; value: boolean | null; editIn: 'settings' | 'each_decision' }[]
}

export interface Contributions {
  trustedContributor: boolean
  placesAdded: number
  halalVerified: number
  photosAdded: number
  posts: number
}

export interface Community {
  shortName: string
  name: string
}

export const APP_VERSION = 'web 1.0'

export const accountApi = {
  selera: () => api<Selera>('me/selera'),
  seleraFeedback: (trait: string, kind: 'more' | 'less' | 'not_really') =>
    api<Selera>(`me/selera/traits/${encodeURIComponent(trait)}/feedback`, { method: 'POST', body: { kind } }),
  seleraHide: (trait: string) => api<Selera>(`me/selera/traits/${encodeURIComponent(trait)}`, { method: 'DELETE' }),
  seleraReset: () => api<Selera>('me/selera/reset', { method: 'POST' }),

  contributions: () => api<Contributions>('me/contributions'),

  blocks: () => api<{ users: { id: number; name: string; avatarKey: string | null }[] }>('me/blocks'),
  block: (userId: number) => api<{ blocked: true }>(`users/${userId}/block`, { method: 'POST' }),
  unblock: (userId: number) => api<{ blocked: false }>(`users/${userId}/block`, { method: 'DELETE' }),

  /** A wrong password answers 401, which here means "wrong password", not "signed out". */
  deleteAccount: (password: string | null) =>
    api<{ deleted: true }>('auth/me', { method: 'DELETE', body: password ? { password } : {}, session: false }),

  acceptTerms: (legal: User['legal']) =>
    api<{ user: User }>('me/terms-acceptance', {
      method: 'POST',
      body: { termsVersion: legal.termsVersion, guidelinesVersion: legal.guidelinesVersion, privacyVersion: legal.privacyVersion, appVersion: APP_VERSION },
    }),

  universities: () => api<{ universities: Community[] }>('universities'),
  areas: () => api<{ areas: Community[] }>('areas'),
  /** Public when neither is given. */
  setCommunity: (choice: { university?: string; area?: string }) =>
    api<{ user: User }>('me/community', { method: 'PATCH', body: choice }),
  requestCommunity: (type: 'university' | 'area', name: string) =>
    api<{ requested: true }>('community/requests', { method: 'POST', body: { type, name } }),
}

export const AVATARS = [
  ['default', 'Classic'],
  ['nasi', 'Nasi'],
  ['roti', 'Roti'],
  ['milo', 'Milo'],
  ['laksa', 'Laksa'],
  ['teh_tarik', 'Teh Tarik'],
  ['mascot', 'MakanApa'],
] as const

export function avatarSrc(key: string | null | undefined): string {
  if (key === 'mascot') return '/mascot/wave-320.webp'
  return `/avatars/${AVATARS.some(([value]) => value === key) ? key : 'default'}.svg`
}
