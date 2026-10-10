/// <reference lib="webworker" />
import { cleanupOutdatedCaches, createHandlerBoundToURL, precacheAndRoute } from 'workbox-precaching'
import { NavigationRoute, registerRoute } from 'workbox-routing'
import { pushTarget, type PushData } from './lib/pushRoutes'

declare const self: ServiceWorkerGlobalScope

// App shell only. API responses are never cached here: picks and places must be live, and the
// session token must not end up in a cache.
precacheAndRoute(self.__WB_MANIFEST)
cleanupOutdatedCaches()
registerRoute(new NavigationRoute(createHandlerBoundToURL('/index.html')))

// The update banner's Reload asks the waiting worker to take over.
self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') void self.skipWaiting()
})

interface PushPayload {
  title?: string
  body?: string
  icon?: string
  badge?: string
  tag?: string
  data?: PushData
}

self.addEventListener('push', (event) => {
  let payload: PushPayload = {}
  try {
    payload = event.data?.json() ?? {}
  } catch {
    payload = { body: event.data?.text() }
  }
  event.waitUntil(
    self.registration.showNotification(payload.title ?? 'MakanApa', {
      body: payload.body,
      icon: payload.icon ?? '/icon-192.png',
      badge: payload.badge ?? '/icon-192.png',
      tag: payload.tag,
      data: payload.data ?? {},
    }),
  )
})

// Tapping a notification focuses an open MakanApa tab (moving it to the right screen) or opens one.
self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const url = new URL(pushTarget(event.notification.data as PushData), self.location.origin).href
  event.waitUntil(
    (async () => {
      const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      const open = windows.find((client) => new URL(client.url).origin === self.location.origin)
      if (open) {
        await open.focus()
        await open.navigate(url).catch(() => open.postMessage({ type: 'NAVIGATE', url }))
        return
      }
      await self.clients.openWindow(url)
    })(),
  )
})
