import { getStored } from '../lib/storage'

// Production builds without VITE_API_BASE_URL fail in vite.config.ts, so this fallback is dev only.
export const API_BASE_URL: string = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1'

/** The 403 codes the API uses to say why, same as the iOS client handles. */
export type ForbiddenCode = 'account_required' | 'terms_required' | 'account_suspended'

export class ApiError extends Error {
  readonly status: number
  readonly code?: string
  /** Seconds to wait, from Retry-After on a 429. */
  readonly retryAfter?: number
  /** Laravel validation errors on a 422, by field. */
  readonly fields?: Record<string, string[]>

  constructor(status: number, message: string, extra: { code?: string; retryAfter?: number; fields?: Record<string, string[]> } = {}) {
    super(message)
    this.status = status
    this.code = extra.code
    this.retryAfter = extra.retryAfter
    this.fields = extra.fields
  }

  /** No response at all: offline, DNS, CORS, timeout. */
  get isNetwork(): boolean {
    return this.status === 0
  }
}

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE'
  body?: unknown
  /** Sent as X-Decision-Token on decision calls. */
  decisionToken?: string
  /**
   * False for the sign-in endpoints, where a 401 means "wrong password" or "bad Google token",
   * not "your session expired". Everything else clears the session on 401.
   */
  session?: boolean
  signal?: AbortSignal
  /** Lets the request finish after the page is hidden or closed (session end). */
  keepalive?: boolean
}

let onUnauthorized: () => void = () => {}

/** The session store registers what a 401 does: drop the token and go back to sign-in. */
export function setUnauthorizedHandler(handler: () => void): void {
  onUnauthorized = handler
}

export async function api<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, decisionToken, session = true, signal, keepalive } = options
  const token = getStored('token')
  const isForm = body instanceof FormData

  const headers: Record<string, string> = { Accept: 'application/json' }
  if (token) headers.Authorization = `Bearer ${token}`
  if (decisionToken) headers['X-Decision-Token'] = decisionToken
  if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json'

  let response: Response
  try {
    response = await fetch(`${API_BASE_URL}/${path.replace(/^\//, '')}`, {
      method,
      headers,
      body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
      signal,
      keepalive,
    })
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    throw new ApiError(0, 'No connection. Check your internet and try again.')
  }

  const data = await response.json().catch(() => null)

  if (response.ok) return data as T

  const message: string = data?.message ?? 'Something went wrong. Try again.'

  if (response.status === 401 && session && token) onUnauthorized()

  const retryAfter = Number(response.headers.get('Retry-After'))
  throw new ApiError(response.status, message, {
    code: data?.code,
    retryAfter: Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : undefined,
    fields: data?.errors,
  })
}
