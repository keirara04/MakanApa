import { useCallback, useEffect, useState } from 'react'
import { communityApi, type Post } from '../../api/community'

/** Top-level posts with paging, plus the edits every list needs (react, delete, report, block, reply). */
export function usePosts({ limit, enabled }: { limit: number; enabled: boolean }) {
  const [posts, setPosts] = useState<Post[]>([])
  const [nextCursor, setNextCursor] = useState<string | null>(null)
  const [canPost, setCanPost] = useState(false)
  const [cannotPostReason, setCannotPostReason] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const [loaded, setLoaded] = useState(false)
  const [error, setError] = useState<unknown>(null)

  const load = useCallback(
    async (cursor: string | null = null) => {
      setLoading(true)
      setError(null)
      try {
        const page = await communityApi.posts({ limit, cursor })
        setPosts((current) => {
          const merged = cursor ? [...current, ...page.posts] : page.posts
          return merged.filter((post, index) => merged.findIndex((other) => other.id === post.id) === index)
        })
        setNextCursor(page.nextCursor)
        setCanPost(page.canPost)
        setCannotPostReason(page.cannotPostReason)
        setLoaded(true)
      } catch (caught) {
        setError(caught)
      } finally {
        setLoading(false)
      }
    },
    [limit],
  )

  useEffect(() => {
    if (enabled) void load()
  }, [enabled, load])

  return {
    posts,
    canPost,
    cannotPostReason,
    loading,
    loaded,
    error,
    hasMore: nextCursor !== null,
    reload: () => load(),
    loadMore: () => nextCursor && !loading && load(nextCursor),
    change: (post: Post) => setPosts((current) => current.map((item) => (item.id === post.id ? post : item))),
    remove: (post: Post) => setPosts((current) => current.filter((item) => item.id !== post.id)),
    removeAuthor: (authorId: number) =>
      setPosts((current) =>
        current
          .filter((item) => item.author.id !== authorId)
          .map((item) => {
            const replies = item.replies?.filter((replyPost) => replyPost.author.id !== authorId)
            const removed = (item.replies?.length ?? 0) - (replies?.length ?? 0)
            return { ...item, replies, replyCount: Math.max(0, item.replyCount - removed) }
          }),
      ),
    added: (post: Post) =>
      setPosts((current) =>
        post.parentId
          ? current.map((item) =>
              item.id === post.parentId ? { ...item, replies: [...(item.replies ?? []), post].slice(-2), replyCount: item.replyCount + 1 } : item,
            )
          : [{ ...post, replies: post.replies ?? [] }, ...current],
      ),
  }
}
