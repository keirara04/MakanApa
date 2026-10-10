/** `presentUser()` in api/app/Http/Controllers/Api/AuthController.php, field for field. */
export interface User {
  id: number
  email: string | null
  name: string | null
  avatarKey: string | null
  halalPreference: boolean
  role: 'user' | 'superadmin' | string
  status: 'active' | string
  isGuest: boolean
  affiliationType: 'university' | 'area' | null
  university: string | null
  area: string | null
  affiliationVerificationStatus: string | null
  ambassadorOf: { type: 'university' | 'area'; name: string } | null
  legal: {
    termsVersion: string
    guidelinesVersion: string
    privacyVersion: string
    needsAcceptance: boolean
  }
}

export interface SessionResponse {
  token: string
  user: User
}

/** Google sign-in for an email that already has a password account: confirm the password to link. */
export interface LinkOffer {
  needsLinking: true
  linkToken: string
  email: string
}

export type SocialResponse = SessionResponse | LinkOffer

export function isLinkOffer(response: SocialResponse): response is LinkOffer {
  return 'needsLinking' in response && response.needsLinking === true
}
