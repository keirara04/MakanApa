import type { CSSProperties } from 'react'
import { budgets, withinLabel } from '../../lib/presentation'
import type { Region } from '../../lib/region'
import { HalalBadge } from '../../ui/HalalBadge'
import { Mascot } from '../../ui/Mascot'

/*
 * Onboarding's three drawings, in the website's hand-drawn vocabulary: wobbly sketch strokes
 * (#sketch-a), masking tape, Caveat notes. Only the first one moves: its arrows draw in once,
 * then the pick is taped down. Reduced motion shows it finished.
 */

const hand = 'whitespace-nowrap font-hand text-[1.25rem] leading-none text-kicap-soft md:text-[1.35rem]'

/** A small map pin, drawn around (0,0) at its tip. */
function Pin({ x, y, fill = 'var(--color-kicap)' }: { x: number; y: number; fill?: string }) {
  return (
    <g transform={`translate(${x} ${y})`}>
      <path d="M0 0C-4-5-6-8-6-11a6 6 0 1 1 12 0c0 3-2 6-6 11Z" fill={fill} />
      <circle cx="0" cy="-11" r="2.2" fill="var(--color-cream)" />
    </g>
  )
}

/** A hand-drawn arrow along `d`, drawn in after `delay` ms. */
function Arrow({ d, head, delay, length }: { d: string; head: string; delay: number; length: number }) {
  const style = { '--len': length, '--delay': `${delay}ms` } as CSSProperties
  return (
    <g fill="none" stroke="var(--color-kicap)" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" filter="url(#sketch-a)">
      <path d={d} className="draw-in" style={style} />
      <path d={head} className="draw-in" style={{ ...style, '--delay': `${delay + 380}ms`, '--len': 40 } as CSSProperties} />
    </g>
  )
}

/**
 * How MakanApa works, as a sequence: every place near you, narrowed by what you choose, to one
 * pick. The example follows the person's region (dish, money, distance unit).
 */
export function HowItWorks({ region, dish, line }: { region: Region; dish: string; line: string }) {
  const choices = [dish, budgets(region)[1].amount, withinLabel(1, region)]

  return (
    <figure className="relative mx-auto w-full max-w-[340px] md:max-w-[420px]" aria-label={`How it works: from the places near you, your choices narrow it down to one pick, like ${dish}.`}>
      <div className="grid grid-cols-[1fr_auto_1.25fr] items-center gap-1" aria-hidden="true">
        <div className="flex flex-col items-center">
          <svg viewBox="0 0 120 84" className="w-full max-w-[120px]">
            <Pin x={18} y={30} />
            <Pin x={44} y={20} />
            <Pin x={72} y={34} />
            <Pin x={100} y={22} />
            <Pin x={30} y={62} />
            <Pin x={58} y={56} fill="var(--color-sambal)" />
            <Pin x={86} y={68} />
            <Pin x={108} y={54} />
          </svg>
          <p className={hand}>1. places near you</p>
        </div>

        <svg viewBox="0 0 44 30" className="w-10">
          <Arrow d="M3 17C14 11 26 11 38 15" head="M30 8l9 7-10 5" delay={150} length={44} />
        </svg>

        <div className="flex flex-col items-center">
          <div className="sketch w-full px-3 py-3" style={{ ['--sketch-radius' as string]: '16px' }}>
            <ul className="flex flex-col gap-1.5">
              {choices.map((choice) => (
                <li key={choice} className="truncate rounded-full bg-sambal/10 px-2.5 py-1 text-center text-sm font-semibold text-sambal-deep">
                  {choice}
                </li>
              ))}
            </ul>
          </div>
          <p className={`${hand} mt-2`}>2. what you choose</p>
        </div>
      </div>

      <svg viewBox="0 0 340 44" className="-mt-1 w-full" aria-hidden="true">
        {/* Ends beside the tape, not on it. */}
        <Arrow d="M266 4C266 20 254 32 230 38" head="M240 31l-11 7 11 5" delay={650} length={60} />
      </svg>

      <div className="relative" aria-hidden="true">
        <div className="sketch tape-in -rotate-2 px-6 pb-6 pt-7 [animation-delay:1100ms]" style={{ ['--sketch-radius' as string]: '22px' }}>
          <span className="tape -top-3 left-1/2 -translate-x-1/2 rotate-2" />
          <p className="text-sm font-semibold text-sambal-deep">3. Picked for you</p>
          <p className="mt-1 font-display text-3xl font-bold leading-tight">{dish}</p>
          <p className="mt-2 pr-20 text-kicap-soft">{line}</p>
        </div>
        <Mascot mood="wave" size={104} className="absolute -bottom-12 -right-2 rotate-6 md:h-32 md:w-32" />
      </div>
    </figure>
  )
}

/** What "near you" means: you in the middle, the two Decide distances as rings around you. */
export function RadiusMap({ region }: { region: Region }) {
  return (
    <figure className="mx-auto w-full max-w-[280px] md:max-w-[340px]" aria-label={`Picks come from places around you: ${withinLabel(1, region).toLowerCase()} for a quick walk, ${withinLabel(5, region).toLowerCase()} if you'll travel.`}>
      <svg viewBox="0 -8 280 238" className="w-full" aria-hidden="true">
        <g fill="none" stroke="var(--color-kicap)" strokeWidth="2" filter="url(#sketch-a)">
          <circle cx="140" cy="120" r="102" strokeDasharray="6 8" opacity="0.45" />
          <circle cx="140" cy="120" r="54" strokeDasharray="5 6" />
        </g>
        <circle cx="140" cy="120" r="54" fill="var(--color-kunyit)" opacity="0.18" />
        <Pin x={112} y={104} />
        <Pin x={168} y={98} />
        <Pin x={160} y={150} />
        <Pin x={120} y={152} />
        <Pin x={62} y={70} />
        <Pin x={226} y={92} />
        <Pin x={200} y={196} />
        <Pin x={70} y={178} />
        <circle cx="140" cy="120" r="13" fill="var(--color-sambal)" opacity="0.2" />
        <circle cx="140" cy="120" r="7" fill="var(--color-sambal)" stroke="var(--color-surface)" strokeWidth="2.5" />
        <g fontFamily="var(--font-hand)" fontSize="19" fill="var(--color-kicap-soft)" textAnchor="middle">
          <text x="140" y="143">you</text>
          <text x="140" y="60" fill="var(--color-kicap)">{withinLabel(1, region).toLowerCase()}</text>
          <text x="140" y="12" opacity="0.75">{withinLabel(5, region).toLowerCase()}</text>
        </g>
      </svg>
    </figure>
  )
}

/** The labels a place can carry, with what "hide non-halal" does to each. Server wording only. */
const LEGEND = [
  { label: 'Halal certified', tone: 'certified', note: 'Shown' },
  { label: 'Not certified · community notes', tone: 'friendly', note: 'Shown, marked' },
  { label: 'Not verified · Help verify', tone: 'neutral', note: 'Shown, marked' },
  { label: 'Non-halal', tone: 'non_halal', note: 'Hidden' },
] as const

export function HalalKey() {
  return (
    <figure className="sketch mx-auto w-full max-w-[340px] px-5 py-5 md:max-w-[380px]" style={{ ['--sketch-radius' as string]: '22px' }}>
      <figcaption className="font-display text-lg font-semibold">With “Hide non-halal” on</figcaption>
      <ul className="mt-3 flex flex-col gap-2.5">
        {LEGEND.map(({ label, tone, note }) => {
          const hidden = tone === 'non_halal'
          return (
            <li key={label} className="flex items-center justify-between gap-3">
              <span className={`relative ${hidden ? 'opacity-45' : ''}`}>
                <HalalBadge halal={{ display: { shortLabel: label, tone } }} />
                {hidden && (
                  <svg viewBox="0 0 100 20" preserveAspectRatio="none" className="pointer-events-none absolute inset-x-[-6px] top-1/2 h-4 w-[calc(100%+12px)] -translate-y-1/2" aria-hidden="true">
                    <path d="M2 12C30 6 62 15 98 7" fill="none" stroke="var(--color-sambal)" strokeWidth="3" strokeLinecap="round" filter="url(#sketch-a)" />
                  </svg>
                )}
              </span>
              <span className={`shrink-0 text-sm font-semibold ${hidden ? 'text-sambal-deep' : 'text-kicap-soft'}`}>{note}</span>
            </li>
          )
        })}
      </ul>
    </figure>
  )
}
