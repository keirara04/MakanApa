import { deviceLabel } from '../lib/device'
import { api } from './client'
import type { SessionResponse, SocialResponse, User } from './types'

/** Every account the web app creates is tagged, so Filament can compare web with iOS. */
const SIGNUP_SOURCE = 'web'

export const authApi = {
  guest: () =>
    api<SessionResponse>('auth/guest', { method: 'POST', body: { deviceLabel: deviceLabel(), signupSource: SIGNUP_SOURCE }, session: false }),

  login: (email: string, password: string) =>
    api<SessionResponse>('auth/login', { method: 'POST', body: { email, password, deviceLabel: deviceLabel() }, session: false }),

  /** With a guest token attached, this upgrades the same account in place (nothing lost). */
  register: (name: string, email: string, password: string) =>
    api<SessionResponse>('auth/register', {
      method: 'POST',
      body: { name, email, password, deviceLabel: deviceLabel(), signupSource: SIGNUP_SOURCE },
      session: false,
    }),

  google: (idToken: string) =>
    api<SocialResponse>('auth/google', {
      method: 'POST',
      body: { idToken, deviceLabel: deviceLabel(), signupSource: SIGNUP_SOURCE },
      session: false,
    }),

  link: (linkToken: string, password: string) =>
    api<SessionResponse>('auth/link', { method: 'POST', body: { linkToken, password, deviceLabel: deviceLabel() }, session: false }),

  me: () => api<{ user: User }>('auth/me'),

  logout: () => api<{ loggedOut: true }>('auth/logout', { method: 'POST' }),

  updateProfile: (changes: Partial<{ name: string; avatarKey: string | null; halalPreference: boolean }>) =>
    api<{ user: User }>('me/profile', { method: 'PATCH', body: changes }),
}
