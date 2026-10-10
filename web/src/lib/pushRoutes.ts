/**
 * Where tapping a push opens the app, by the payload's `type` (same payload as the iOS app).
 * Shared by the service worker and tested on its own.
 */
export interface PushData {
  type?: string
  nudgeId?: number
  restaurantId?: number
  postId?: number
}

export function pushTarget(data: PushData | null | undefined): string {
  switch (data?.type) {
    case 'meal_nudge': {
      const query = new URLSearchParams()
      if (data.nudgeId) query.set('nudge', String(data.nudgeId))
      if (data.restaurantId) {
        query.set('place', String(data.restaurantId))
        return `/nearby?${query}`
      }
      return `/?${query}`
    }
    case 'community_post_replied':
    case 'community_post_reacted':
      return data.postId ? `/community/posts/${data.postId}` : '/community'
    case 'submission_decided':
    case 'halal_report_decided':
    case 'halal_report_superseded':
    case 'owner_claim_decided':
    case 'ambassador_application_decided':
    case 'community_post_reported':
      return '/community'
    default:
      if (data?.type?.startsWith('halal_certificate')) return '/community'
      return '/'
  }
}
