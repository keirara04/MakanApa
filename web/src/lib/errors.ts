import { ApiError } from '../api/client'

/** What to tell the person when a request fails: what happened and what to do next. */
export function errorMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return 'Something went wrong. Try again.'
  if (error.isNetwork) return error.message
  // Never show a server error's own text: in debug builds it can be a raw SQL message.
  if (error.status >= 500) return 'MakanApa had a problem on its side. Try again in a moment.'
  if (error.status === 429) {
    return error.retryAfter ? `Too many tries in a row. Try again in ${error.retryAfter}s.` : 'Too many tries in a row. Try again in a minute.'
  }
  if (error.code === 'account_suspended') return 'This account is suspended. Contact support if you think this is a mistake.'
  const firstField = error.fields && Object.values(error.fields)[0]?.[0]
  return firstField ?? error.message
}
