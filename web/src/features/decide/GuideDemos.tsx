import { useEffect, useRef, useState, type ReactNode } from 'react'
import { BUDGETS, DISTANCES } from '../../lib/presentation'
import { Button } from '../../ui/Button'

/**
 * Small try-it versions of Decide for the "How Decide works" sheet, one per guide page. Made-up
 * places, no network: tapping only changes what's drawn here. Motion only answers a tap.
 */
export function GuideDemo({ page }: { page: number }) {
  const Demo = DEMOS[page] ?? RecentDemo
  return <Demo />
}

function Stage({ hint, children }: { hint: string; children: ReactNode }) {
  return (
    <div className="flex min-h-[min(19rem,34dvh)] flex-col items-center justify-center gap-2.5 rounded-card bg-cream px-4 py-4">
      {children}
      <p className="font-hand text-xl leading-none text-kicap-soft">{hint}</p>
    </div>
  )
}

/** Runs `then` once, `ms` after `start()`; cleared if the page changes first. */
function useLater(ms: number, then: () => void) {
  const timer = useRef<number | undefined>(undefined)
  useEffect(() => () => window.clearTimeout(timer.current), [])
  return () => {
    window.clearTimeout(timer.current)
    timer.current = window.setTimeout(then, ms)
  }
}

function MiniPick({ name, lines }: { name: string; lines: string[] }) {
  return (
    <div className="tape-in w-full max-w-64 rounded-row border border-hairline bg-surface p-4 text-left shadow-sm">
      <p className="font-display text-lg font-bold">{name}</p>
      {lines.map((line) => (
        <p key={line} className="text-sm text-kicap-soft">
          {line}
        </p>
      ))}
    </div>
  )
}

function QuickDemo() {
  const [step, setStep] = useState<'idle' | 'busy' | 'done'>('idle')
  const finish = useLater(900, () => setStep('done'))

  return (
    <Stage hint={step === 'done' ? 'That’s the whole thing.' : 'Try it: tap Pick for me'}>
      <div aria-live="polite" className="flex w-full flex-col items-center gap-2">
        {step === 'done' ? (
          <>
            <MiniPick name="Warung Mak Teh" lines={['Malay, ≈ RM10/person', '6 min walk', 'Open until 11:00 PM']} />
            <Button variant="quiet" onClick={() => setStep('idle')}>
              Try again
            </Button>
          </>
        ) : (
          <button
            type="button"
            disabled={step === 'busy'}
            onClick={() => {
              setStep('busy')
              finish()
            }}
            className="press-raised relative flex w-full max-w-64 flex-col gap-3 overflow-hidden rounded-card bg-sambal p-4 text-left text-white"
          >
            <img src="/choices/mood-nasilemak.webp" alt="" width={64} height={64} className="absolute top-3 -right-4 h-16 w-16 rounded-full border-4 border-white/90 object-cover" />
            <span className="font-display text-xl font-bold">Can’t decide?</span>
            <span className="relative flex min-h-11 items-center justify-center rounded-full bg-white font-display text-lg font-bold text-sambal-deep">
              {step === 'busy' ? 'Finding you…' : 'Pick for me'}
            </span>
          </button>
        )}
      </div>
    </Stage>
  )
}

const DEMO_DISHES = [
  ['Nasi Lemak', 'mood-nasilemak'],
  ['Dim Sum', 'mood-dimsum'],
  ['Char Kuey Teow', 'mood-charkueyteow'],
] as const

function Choice({ on, onClick, children }: { on: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      aria-pressed={on}
      onClick={onClick}
      className={`press min-h-10 rounded-full border-2 px-3 font-display text-sm font-semibold ${on ? 'border-kicap bg-kicap text-cream' : 'border-hairline bg-surface'}`}
    >
      {children}
    </button>
  )
}

function CravingDemo() {
  const [dish, setDish] = useState(1)
  const [budget, setBudget] = useState(1)
  const [distance, setDistance] = useState(1)

  return (
    <Stage hint="Try it: change any of them">
      <div className="flex gap-3" role="group" aria-label="Craving">
        {DEMO_DISHES.map(([name, image], index) => (
          <button
            key={name}
            type="button"
            aria-pressed={dish === index}
            aria-label={name}
            onClick={() => setDish(index)}
            className={`press rounded-full p-0.5 ${dish === index ? 'ring-4 ring-sambal' : 'opacity-70'}`}
          >
            <img src={`/choices/${image}.webp`} alt="" width={48} height={48} className="h-12 w-12 rounded-full object-cover" />
          </button>
        ))}
      </div>
      <div className="flex flex-wrap justify-center gap-1.5" role="group" aria-label="Budget">
        {BUDGETS.map((option, index) => (
          <Choice key={option.value} on={budget === index} onClick={() => setBudget(index)}>
            {option.amount}
          </Choice>
        ))}
      </div>
      <div className="flex flex-wrap justify-center gap-1.5" role="group" aria-label="Distance">
        {DISTANCES.slice(0, 3).map((option, index) => (
          <Choice key={option.km} on={distance === index} onClick={() => setDistance(index)}>
            {option.short}
          </Choice>
        ))}
      </div>
      <p aria-live="polite" className="text-center text-sm">
        MakanApa picks <strong>{DEMO_DISHES[dish][0]}</strong>, around {BUDGETS[budget].amount.replace('~', '')}, {distance === 2 ? 'at any distance' : `within ${DISTANCES[distance].short}`}.
      </p>
    </Stage>
  )
}

const REASONS = ['Open now, until 11:00 PM', '4.5 stars from 820 reviews', '6 min walk, inside your budget']

function WhyDemo() {
  const [shown, setShown] = useState(false)
  const [going, setGoing] = useState(false)

  return (
    <Stage hint={shown ? 'Then tap Let’s eat' : 'Try it: tap See why'}>
      <div className="w-full max-w-64 rounded-row border border-hairline bg-surface p-4 shadow-sm">
        <p className="font-display text-lg font-bold">Kedai Kopi Seri</p>
        <p className="text-sm text-kicap-soft">Café, ≈ RM10/person</p>
        <div aria-live="polite">
          {shown ? (
            <ul className="mt-3 flex flex-col gap-1.5">
              {REASONS.map((reason, index) => (
                <li key={reason} className="trace-line flex items-center gap-2 text-sm" style={{ ['--i' as string]: index }}>
                  <span aria-hidden="true" className="h-2 w-2 shrink-0 rounded-full bg-pandan" />
                  {reason}
                </li>
              ))}
            </ul>
          ) : (
            <button type="button" onClick={() => setShown(true)} className="press mt-3 min-h-10 rounded-full border-2 border-kicap px-4 font-display text-sm font-semibold">
              See why
            </button>
          )}
        </div>
        {shown && (
          <button
            type="button"
            onClick={() => setGoing(true)}
            className="press mt-3 min-h-11 w-full rounded-full bg-sambal font-display font-bold text-white"
          >
            {going ? 'Opening your maps app…' : 'Let’s eat'}
          </button>
        )}
      </div>
    </Stage>
  )
}

const TUNES = {
  start: { name: 'Restoran Seri Melur', lines: ['Malay, ≈ RM20/person', '14 min walk', '4.1 stars'], changed: -1 },
  closer: { name: 'Warung Mak Teh', lines: ['Malay, ≈ RM20/person', '4 min walk', '4.2 stars'], changed: 1 },
  cheaper: { name: 'Gerai Pak Mat', lines: ['Malay, ≈ RM10/person', '12 min walk', '4.0 stars'], changed: 0 },
  safer: { name: 'Dapur Tok Wan', lines: ['Malay, ≈ RM20/person', '15 min walk', '4.7 stars from 2,300 reviews'], changed: 2 },
} as const

function TuneDemo() {
  const [tune, setTune] = useState<keyof typeof TUNES>('start')
  const pick = TUNES[tune]

  return (
    <Stage hint={tune === 'start' ? 'Try it: tap Closer, Cheaper or Safer bet' : 'Same list, one thing better'}>
      <div key={tune} aria-live="polite" className="tape-in w-full max-w-64 rounded-row border border-hairline bg-surface p-4 shadow-sm">
        <p className="font-display text-lg font-bold">{pick.name}</p>
        {pick.lines.map((line, index) => (
          <p key={line} className={`text-sm ${index === pick.changed ? 'font-semibold text-pandan' : 'text-kicap-soft'}`}>
            {line}
          </p>
        ))}
      </div>
      <div className="flex flex-wrap justify-center gap-1.5">
        {(
          [
            ['closer', 'Closer'],
            ['cheaper', 'Cheaper'],
            ['safer', 'Safer bet'],
          ] as const
        ).map(([key, label]) => (
          <Choice key={key} on={tune === key} onClick={() => setTune(key)}>
            {label}
          </Choice>
        ))}
      </div>
    </Stage>
  )
}

const DECK = ['Dim Sum Corner', 'Warung Mak Teh', 'Kedai Kopi Seri']
const DECK_TINTS = ['bg-kunyit text-kicap', 'bg-pandan text-white', 'bg-sambal text-white']

function SavedDemo() {
  const [step, setStep] = useState<'idle' | 'shuffling' | 'dealt'>('idle')
  const [dealt, setDealt] = useState(0)
  const deal = useLater(1200, () => setStep('dealt'))

  function shuffle() {
    setDealt((current) => (current + 1 + Math.floor(Math.random() * (DECK.length - 1))) % DECK.length)
    setStep('shuffling')
    deal()
  }

  return (
    <Stage hint={step === 'dealt' ? 'Shuffle as often as you like' : 'Try it: tap the deck'}>
      <div aria-live="polite" className="flex flex-col items-center gap-3">
        {step === 'dealt' ? (
          <>
            <MiniPick name={DECK[dealt]} lines={['From your saved places']} />
            <Button variant="quiet" onClick={shuffle}>
              Shuffle again
            </Button>
          </>
        ) : (
          <button
            type="button"
            onClick={shuffle}
            disabled={step === 'shuffling'}
            aria-label="Shuffle the saved places"
            className="press relative h-32 w-24"
          >
            {DECK.map((name, index) => (
              <span
                key={name}
                className={`absolute inset-0 flex items-end rounded-row p-2.5 text-left font-display text-sm leading-tight font-semibold shadow-md ${DECK_TINTS[index]} ${step === 'shuffling' ? 'deck-card' : ''}`}
                style={{ ['--i' as string]: index, ['--n' as string]: DECK.length, rotate: step === 'shuffling' ? undefined : `${(index - 1) * 9}deg` }}
              >
                {name}
              </span>
            ))}
          </button>
        )}
      </div>
    </Stage>
  )
}

const RECENT = [
  ['Warung Mak Teh', 'Malay, yesterday'],
  ['Dim Sum Corner', 'Chinese, Friday'],
  ['Kedai Kopi Seri', 'Café, Thursday'],
] as const

function RecentDemo() {
  const [rows, setRows] = useState<readonly (typeof RECENT)[number][]>(RECENT)
  const [opened, setOpened] = useState<string | null>(null)

  return (
    <Stage hint={rows.length < RECENT.length ? 'Gone from Recent, nothing else' : 'Try it: tap a place, or the ×'}>
      <div aria-live="polite" className="w-full max-w-64">
        {rows.length === 0 ? (
          <div className="flex flex-col items-center gap-1 py-4 text-center text-sm text-kicap-soft">
            Places you pick will show up here.
            <Button variant="quiet" onClick={() => setRows(RECENT)}>
              Bring them back
            </Button>
          </div>
        ) : (
          <ul className="divide-y divide-hairline overflow-hidden rounded-row border border-hairline bg-surface shadow-sm">
            {rows.map(([name, meta]) => (
              <li key={name} className="flex items-center pr-1">
                <button type="button" onClick={() => setOpened(name)} className="flex min-h-14 flex-1 flex-col justify-center px-3 py-2 text-left">
                  <span className="font-display font-semibold">{name}</span>
                  <span className="text-xs text-kicap-soft">{opened === name ? 'Opens in your maps app' : meta}</span>
                </button>
                <button
                  type="button"
                  aria-label={`Remove ${name}`}
                  onClick={() => setRows((current) => current.filter(([other]) => other !== name))}
                  className="press flex h-10 w-10 items-center justify-center rounded-full text-kicap-soft"
                >
                  <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2.25" strokeLinecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18" />
                  </svg>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Stage>
  )
}

/** In the same order as the guide's pages. */
const DEMOS = [QuickDemo, CravingDemo, WhyDemo, TuneDemo, SavedDemo, RecentDemo]
