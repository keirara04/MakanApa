import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { setStored } from '../lib/storage'
import { ApiError, api, setUnauthorizedHandler } from './client'

function respond(status: number, body: unknown, headers: Record<string, string> = {}) {
  vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify(body), { status, headers })))
}

async function failure(promise: Promise<unknown>): Promise<ApiError> {
  try {
    await promise
  } catch (error) {
    return error as ApiError
  }
  throw new Error('expected the request to fail')
}

describe('api client', () => {
  const unauthorized = vi.fn()

  beforeEach(() => {
    localStorage.clear()
    setStored('token', 'tok')
    unauthorized.mockReset()
    setUnauthorizedHandler(unauthorized)
  })

  afterEach(() => vi.unstubAllGlobals())

  it('sends the bearer token and decision token', async () => {
    respond(200, { ok: true })
    await api('decisions/1/accept', { method: 'POST', body: {}, decisionToken: 'dec' })

    const [, init] = vi.mocked(fetch).mock.calls[0]
    expect(init?.headers).toMatchObject({ Authorization: 'Bearer tok', 'X-Decision-Token': 'dec' })
  })

  it('clears the session on a 401 from a normal call', async () => {
    respond(401, { message: 'Unauthenticated.' })
    expect((await failure(api('auth/me'))).status).toBe(401)
    expect(unauthorized).toHaveBeenCalledOnce()
  })

  it('keeps the session on a 401 from a sign-in call (wrong password)', async () => {
    respond(401, { message: 'Invalid credentials.' })
    const error = await failure(api('auth/login', { method: 'POST', body: {}, session: false }))
    expect(error.message).toBe('Invalid credentials.')
    expect(unauthorized).not.toHaveBeenCalled()
  })

  it('surfaces the 403 code', async () => {
    respond(403, { message: 'Sign in first.', code: 'account_required' })
    expect((await failure(api('community/posts', { method: 'POST', body: {} }))).code).toBe('account_required')
  })

  it('reads Retry-After on a 429', async () => {
    respond(429, { message: 'Too Many Attempts.' }, { 'Retry-After': '42' })
    expect((await failure(api('places/nearby'))).retryAfter).toBe(42)
  })

  it('turns a network failure into a readable error', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => { throw new TypeError('Failed to fetch') }))
    const error = await failure(api('auth/me'))
    expect(error.isNetwork).toBe(true)
  })
})
