import { api } from '../api/client'

export type NotificationPreferences = Record<
  'mealtime_nudges' | 'community_submissions' | 'community_replies' | 'community_reactions' | 'account_admin' | 'release_announcements',
  boolean
>

export const preferencesApi = {
  get: () => api<{ preferences: NotificationPreferences }>('me/notification-preferences'),
  update: (changes: Partial<NotificationPreferences>) => api<{ preferences: NotificationPreferences }>('me/notification-preferences', { method: 'PATCH', body: changes }),
}

export type PushState = 'unsupported' | 'blocked' | 'off' | 'on'

export function pushSupported(): boolean {
  return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
}

export async function pushState(): Promise<PushState> {
  if (!pushSupported()) return 'unsupported'
  if (Notification.permission === 'denied') return 'blocked'
  if (Notification.permission !== 'granted') return 'off'
  const registration = await navigator.serviceWorker.getRegistration()
  return (await registration?.pushManager.getSubscription()) ? 'on' : 'off'
}

/** The browser's subscription, sent to the account. Same shape the API's Web Push channel uses. */
async function register(subscription: PushSubscription): Promise<void> {
  const json = subscription.toJSON()
  const encodings = (PushManager as unknown as { supportedContentEncodings?: string[] }).supportedContentEncodings ?? ['aes128gcm']
  await api('me/web-push', {
    method: 'POST',
    body: { endpoint: json.endpoint, keys: json.keys, contentEncoding: encodings.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm' },
  })
}

function keyBytes(base64Url: string): Uint8Array<ArrayBuffer> {
  const base64 = (base64Url + '='.repeat((4 - (base64Url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(base64)
  const bytes = new Uint8Array(new ArrayBuffer(raw.length))
  for (let index = 0; index < raw.length; index++) bytes[index] = raw.charCodeAt(index)
  return bytes
}

/** Asks the browser (only ever after a tap), subscribes, and tells the API. */
export async function enablePush(): Promise<PushState> {
  if (!pushSupported()) return 'unsupported'
  const permission = await Notification.requestPermission()
  if (permission === 'denied') return 'blocked'
  if (permission !== 'granted') return 'off'

  const registration = await navigator.serviceWorker.ready
  let subscription = await registration.pushManager.getSubscription()
  if (!subscription) {
    const { publicKey } = await api<{ publicKey: string | null }>('web-push/key')
    if (!publicKey) return 'unsupported'
    subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(publicKey) })
  }
  try {
    await register(subscription)
  } catch (error) {
    // Otherwise this browser would say "on" next time while the account has no subscription.
    await subscription.unsubscribe().catch(() => {})
    throw error
  }
  return 'on'
}

/** On start: an existing subscription is re-sent, so it follows whoever is signed in now. */
export async function syncPush(): Promise<void> {
  if (!pushSupported() || Notification.permission !== 'granted') return
  const registration = await navigator.serviceWorker.getRegistration()
  const subscription = await registration?.pushManager.getSubscription()
  if (subscription) await register(subscription).catch(() => {})
}

/** Stop pushes to this browser for this account (log out, or switched off in settings). */
export async function disablePush({ unsubscribe }: { unsubscribe: boolean }): Promise<void> {
  if (!pushSupported()) return
  const registration = await navigator.serviceWorker.getRegistration()
  const subscription = await registration?.pushManager.getSubscription()
  if (!subscription) return
  await api('me/web-push', { method: 'DELETE', body: { endpoint: subscription.endpoint } }).catch(() => {})
  if (unsubscribe) await subscription.unsubscribe().catch(() => {})
}
