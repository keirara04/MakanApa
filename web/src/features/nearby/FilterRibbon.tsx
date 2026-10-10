import type { ReactNode } from 'react'
import type { DiscoveryMode, Vibe } from '../../api/nearby'
import { setStored, useStored } from '../../lib/storage'
import { useNearby } from './state'

const MODES: [DiscoveryMode, string][] = [
  ['normal', 'For you'],
  ['low_key', 'Low-key'],
  ['cafe', 'Cafe'],
]
const MORE_MODES: [DiscoveryMode, string][] = [
  ['popular', 'Popular'],
  ['cheap_eats', 'Cheap eats'],
  ['late_night', 'Late night'],
]
const VIBES: [Vibe, string][] = [
  ['chill', 'Chill'],
  ['study', 'Study'],
  ['dessert', 'Dessert'],
  ['brunch', 'Brunch'],
]

/** The iOS Nearby ribbon: filters, then a mode; a vibe row appears for the non-default modes. */
export function FilterRibbon({ trailing }: { trailing?: ReactNode }) {
  const { filters, setFilters, resetFilters } = useNearby()
  const changed = filters.openNow || filters.budgetMax !== null || filters.minRating !== null || filters.mode !== 'normal' || filters.vibe !== null
  const moreMode = MORE_MODES.find(([mode]) => mode === filters.mode)

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center gap-2">
        <div className="flex flex-1 gap-2 overflow-x-auto py-1 [mask-image:linear-gradient(to_right,transparent,black_12px,black_calc(100%-12px),transparent)] [scrollbar-width:none]" role="group" aria-label="Filters">
          <Chip on={filters.halal} onClick={() => setFilters({ halal: !filters.halal })}>
            Hide non-halal
          </Chip>
          <Chip on={filters.openNow} onClick={() => setFilters({ openNow: !filters.openNow })}>
            Open now
          </Chip>
          <Chip on={filters.budgetMax === 2} onClick={() => setFilters({ budgetMax: filters.budgetMax === 2 ? null : 2 })}>
            ≤ RM20
          </Chip>
          <Chip on={filters.minRating === 4.5} onClick={() => setFilters({ minRating: filters.minRating === 4.5 ? null : 4.5 })}>
            4.5+ ★
          </Chip>
          <span className="my-auto h-5 w-px shrink-0 bg-kicap/15" aria-hidden="true" />
          {MODES.map(([mode, label]) => (
            <Chip key={mode} on={filters.mode === mode} onClick={() => setFilters({ mode })}>
              {label}
            </Chip>
          ))}
          <label className="relative shrink-0">
            <span className="sr-only">More modes</span>
            <select
              value={moreMode ? moreMode[0] : ''}
              onChange={(event) => setFilters({ mode: (event.target.value || 'normal') as DiscoveryMode })}
              className={`press min-h-10 cursor-pointer appearance-none rounded-full border-2 py-0 pl-4 pr-8 font-display text-sm font-semibold shadow-sm ${moreMode ? 'border-sambal-deep bg-sambal-deep text-white' : 'border-hairline bg-surface text-kicap'}`}
            >
              <option value="">More</option>
              {MORE_MODES.map(([mode, label]) => (
                <option key={mode} value={mode}>
                  {label}
                </option>
              ))}
            </select>
            <span aria-hidden="true" className={`pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs ${moreMode ? 'text-white' : ''}`}>
              ▾
            </span>
          </label>
          {changed && (
            <button
              type="button"
              aria-label="Reset filters"
              onClick={resetFilters}
              className="press min-h-10 shrink-0 rounded-full border-2 border-sambal/40 bg-surface px-4 font-display text-sm font-semibold text-sambal-deep"
            >
              Reset
            </button>
          )}
        </div>
        {trailing}
      </div>

      {filters.mode !== 'normal' && (
        <div className="flex gap-2 overflow-x-auto [scrollbar-width:none]" role="group" aria-label="Vibe">
          {VIBES.map(([vibe, label]) => (
            <button
              key={vibe}
              type="button"
              aria-pressed={filters.vibe === vibe}
              onClick={() => setFilters({ vibe: filters.vibe === vibe ? null : vibe })}
              className={`press min-h-9 shrink-0 rounded-full px-3 text-sm font-semibold ${filters.vibe === vibe ? 'bg-kunyit text-kicap' : 'bg-surface/70 text-kicap/80'}`}
            >
              {label}
            </button>
          ))}
        </div>
      )}

      <HalalNotice show={filters.halal} />
    </div>
  )
}

function Chip({ on, onClick, children }: { on: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      aria-pressed={on}
      onClick={onClick}
      className={`press min-h-10 shrink-0 whitespace-nowrap rounded-full border-2 px-4 font-display text-sm font-semibold shadow-sm ${on ? 'border-sambal-deep bg-sambal-deep text-white' : 'border-hairline bg-surface text-kicap'}`}
    >
      {children}
    </button>
  )
}

function HalalNotice({ show }: { show: boolean }) {
  const dismissed = useStored('halalNoticeDismissed')
  if (!show || dismissed) return null
  return (
    <div role="note" className="flex gap-3 rounded-row border-2 border-kunyit/60 bg-surface px-4 py-3">
      <svg viewBox="0 0 24 24" className="mt-0.5 h-5 w-5 shrink-0 text-kunyit" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M10.3 3.9 2.4 17.6A2 2 0 0 0 4.1 20.6h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01" />
      </svg>
      <div className="flex-1">
        <p className="font-display font-semibold">Halal info is community-sourced</p>
        <p className="text-sm text-kicap-soft">
          Halal info may be incomplete or out of date. Always double-check at the restaurant (look for the halal certificate) before you eat.
        </p>
      </div>
      <button type="button" aria-label="Dismiss notice" onClick={() => setStored('halalNoticeDismissed', true)} className="h-11 w-11 shrink-0 text-kicap-soft">
        ✕
      </button>
    </div>
  )
}
