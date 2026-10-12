import { act, renderHook } from '@testing-library/react'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { decideApi, type Decision, type KakaoPick } from '../../api/decide'
import { getStored, setStored } from '../../lib/storage'
import { DecideProvider, useDecide } from './state'

vi.mock('../../api/decide', () => ({
  decideApi: { solo: vi.fn(), reroll: vi.fn(), accept: vi.fn(), interaction: vi.fn() },
}))

const kakao = (id: string): KakaoPick => ({
  kakaoPlaceId: id, kakaoUrl: `http://place.map.kakao.com/${id}`, name: '식당', cuisine: 'Indian',
  address: null, latitude: 37.59, longitude: 127.03, distanceKm: 0.3,
})

const decision: Decision = { decisionId: 1, clientToken: 't', algorithmVersion: 'kakao_live', recommendation: null, kakaoPick: kakao('k1') }
const request = { latitude: 37.59, longitude: 127.03, maxDistanceKm: 2, moods: [], halal: false, ignoreContext: [] }
const asked = { mood: 'Anything', budget: 'No budget limit', distance: 'Within 2 km' }
const wrapper = ({ children }: { children: ReactNode }) => <DecideProvider>{children}</DecideProvider>

describe('a Kakao pick', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.mocked(decideApi.solo).mockResolvedValue(decision)
    vi.mocked(decideApi.accept).mockResolvedValue({ accepted: true })
    vi.mocked(decideApi.interaction).mockResolvedValue({ recorded: true } as never)
    vi.spyOn(window, 'open').mockReturnValue(null)
  })

  it('never puts a Kakao id in the excluded list, which every later pick sends', async () => {
    setStored('excluded', [7])
    vi.mocked(decideApi.reroll).mockResolvedValue({ recommendation: null, kakaoPick: kakao('k2') })
    const { result } = renderHook(useDecide, { wrapper })

    await act(() => result.current.decide(request, asked))
    expect(result.current.kakao?.kakaoPlaceId).toBe('k1')
    await act(() => result.current.reroll({ exclude: true }))

    expect(result.current.kakao?.kakaoPlaceId).toBe('k2')
    expect(getStored('excluded')).toEqual([7])
  })

  it('keeps only the link in Recent, never the name', async () => {
    const { result } = renderHook(useDecide, { wrapper })
    await act(() => result.current.decide(request, asked))
    act(() => result.current.accept())

    expect(window.open).toHaveBeenCalledWith('http://place.map.kakao.com/k1', '_blank', 'noopener')
    expect(getStored('recent')).toEqual([{ kind: 'kakao', id: 'kakao-k1', kakaoUrl: 'http://place.map.kakao.com/k1', at: expect.any(Number) }])
    expect(getStored('pendingVibe')).toBeUndefined()
  })
})
