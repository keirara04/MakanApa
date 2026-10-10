import { api } from './client'

export type OpenStatus = 'open' | 'closed' | 'unknown'
export type CommunityTag = 'chill' | 'study' | 'student_budget' | 'hidden_gem' | 'date' | 'lepak' | 'family' | 'late_night'

interface FeedPlace {
  id: number
  name: string
  foodCategory: string | null
  rating: number | null
  priceLevel: number | null
  cuisines: string[]
  openStatus: OpenStatus
  distanceKm: number | null
}

export type TrendingItem = FeedPlace & { pickCount: number; pickerCount: number; trendingVibe: CommunityTag | null }
export type NewInAreaItem = FeedPlace & { approvedAt: string | null }
export type AmbassadorPickItem = FeedPlace & { photoUrl: string | null; note: string | null; ambassador: { name: string; avatarKey: string | null }; pickedAt: string }

export interface Feed {
  community: { type: 'university' | 'area' | 'public'; university: string | null; area: string | null; label: string }
  trending: TrendingItem[]
  newInArea: NewInAreaItem[]
  ambassadorPicks: AmbassadorPickItem[]
}

export type Reaction = 'up' | 'fire' | 'drool'

export interface Post {
  id: number
  parentId: number | null
  body: string
  createdAt: string
  author: { id: number | null; name: string; avatarKey: string | null; ambassadorOf: { type: 'university' | 'area'; name: string } | null }
  isMine: boolean
  restaurant: { id: number; name: string; foodCategory: string | null } | null
  reactionCount: number
  reactions: Partial<Record<Reaction, number>>
  myReaction: Reaction | null
  replyCount: number
  replies?: Post[]
}

export interface PostsPage {
  posts: Post[]
  nextCursor: string | null
  canPost: boolean
  cannotPostReason: string | null
}

export interface Thread {
  post: Post
  replies: Post[]
  nextCursor: string | null
  canReply: boolean
}

export type ReportReason = 'spam' | 'offensive' | 'harassment' | 'misleading' | 'other'

export interface AmbassadorApplication {
  id: number
  status: 'pending' | 'approved' | 'declined'
  communityType: 'university' | 'area'
  communityName: string | null
  reviewNote: string | null
  createdAt: string | null
}

export interface TaggablePlace {
  id: number
  name: string
  address: string | null
  foodCategory: string | null
}

export const communityApi = {
  feed: (coords: { latitude: number; longitude: number } | null) => {
    const query = coords ? `?${new URLSearchParams({ latitude: String(coords.latitude), longitude: String(coords.longitude) })}` : ''
    return api<Feed>(`community/feed${query}`)
  },

  posts: (params: { restaurantId?: number; cursor?: string | null; limit?: number } = {}) => {
    const query = new URLSearchParams()
    if (params.restaurantId) query.set('restaurantId', String(params.restaurantId))
    if (params.cursor) query.set('cursor', params.cursor)
    if (params.limit) query.set('limit', String(params.limit))
    return api<PostsPage>(`community/posts${query.size ? `?${query}` : ''}`)
  },

  thread: (id: number, cursor?: string | null) => api<Thread>(`community/posts/${id}${cursor ? `?cursor=${encodeURIComponent(cursor)}` : ''}`),

  create: (body: { body: string; restaurantId?: number | null; parentId?: number | null }) => api<{ post: Post }>('community/posts', { method: 'POST', body }),

  remove: (id: number) => api<{ deleted: true }>(`community/posts/${id}`, { method: 'DELETE' }),

  react: (id: number, type: Reaction) =>
    api<Pick<Post, 'myReaction' | 'reactionCount' | 'reactions'>>(`community/posts/${id}/react`, { method: 'POST', body: { type } }),

  report: (id: number, reason: ReportReason, note: string | null) => api<{ reported: true }>(`community/posts/${id}/report`, { method: 'POST', body: { reason, note } }),

  /** MakanApa places only (Google results are never offered for tagging). Shares the places limit. */
  searchPlaces: (query: string, coords: { latitude: number; longitude: number } | null, signal?: AbortSignal) => {
    const params = new URLSearchParams({ query })
    if (coords) {
      params.set('latitude', String(coords.latitude))
      params.set('longitude', String(coords.longitude))
    }
    return api<{ existing: TaggablePlace[] }>(`community/places/search?${params}`, { signal })
  },

  application: () => api<{ application: AmbassadorApplication | null }>('me/ambassador-application'),
  apply: (reason: string, instagramHandle: string | null) =>
    api<{ application: AmbassadorApplication }>('me/ambassador-application', { method: 'POST', body: { reason, instagramHandle } }),
}

/** Optimistic reaction, same rules as the server toggle (iOS applyingReaction). */
export function applyReaction(post: Post, type: Reaction): Post {
  const reactions = { ...post.reactions }
  let total = post.reactionCount
  if (post.myReaction) {
    reactions[post.myReaction] = Math.max(0, (reactions[post.myReaction] ?? 0) - 1)
    total -= 1
  }
  if (post.myReaction === type) return { ...post, reactions, reactionCount: total, myReaction: null }
  reactions[type] = (reactions[type] ?? 0) + 1
  return { ...post, reactions, reactionCount: total + 1, myReaction: type }
}
