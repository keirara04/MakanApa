import { useQuery, useQueryClient } from '@tanstack/react-query'
import { decideApi } from '../../api/decide'
import type { Coordinates } from '../../lib/location'
import { setStored, useStored } from '../../lib/storage'

/**
 * "Right now: Raining, Supper". What MakanApa is factoring in, from GET context. Tapping a
 * chip tells it to ignore that signal (sent as ignoreContext on every pick). Hidden when empty,
 * which is always the case while the Brain is switched off.
 */
export function ContextStrip({ coords }: { coords: Coordinates | null }) {
  const ignored = useStored('ignoredContext') ?? []
  const queryClient = useQueryClient()
  const { data } = useQuery({
    queryKey: ['context', coords?.latitude.toFixed(3), coords?.longitude.toFixed(3), ignored],
    queryFn: () => decideApi.context(coords!.latitude, coords!.longitude, ignored),
    enabled: coords !== null,
    staleTime: 5 * 60_000,
  })

  const signals = data?.signals ?? []
  if (signals.length === 0) return null

  function toggle(key: string) {
    const next = ignored.includes(key) ? ignored.filter((item) => item !== key) : [...ignored, key].sort()
    setStored('ignoredContext', next)
    void queryClient.invalidateQueries({ queryKey: ['context'] })
  }

  return (
    <div className="flex flex-wrap items-center gap-2" role="group" aria-label="What MakanApa is factoring in right now">
      <span className="font-hand text-2xl leading-none text-kicap-soft">Right now</span>
      {signals.map((signal) => {
        const off = ignored.includes(signal.key)
        return (
          <button
            key={signal.key}
            type="button"
            aria-pressed={!off}
            onClick={() => toggle(signal.key)}
            className={`press min-h-10 rounded-full border-2 px-3 font-display text-sm font-semibold ${off ? 'border-hairline text-kicap-soft line-through' : signal.stale ? 'border-hairline bg-surface' : 'border-kicap bg-surface'}`}
          >
            {signal.label}
          </button>
        )
      })}
    </div>
  )
}
