import { useState, type FormEvent, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import type { Lens } from '../../api/decide'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { useLocation } from '../../lib/location'
import { budgets, distanceAmount, distances } from '../../lib/presentation'
import { useRegion, type Region } from '../../lib/region'
import { setStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { ContextStrip } from './ContextStrip'
import { buildSoloRequest, LocationNeeded, type Answers } from './request'
import { useDecide } from './state'

type Mood = readonly [tag: string, label: string, subtitle: string, image: string]

const HEALTHY: Mood = ['healthy', 'Healthy', 'Something lighter', 'mood-light.svg']
const QUICK: Mood = ['quick', 'Quick', 'Fast and convenient', 'mood-quick.svg']

const MY_CRAVINGS: Mood[] = [
  ['nasi_kandar', 'Nasi Kandar', 'Kandar power', 'mood-nasikandar.webp'],
  ['ayam_gepuk', 'Ayam Gepuk', 'Smashed, spicy, delicious', 'mood-ayamgepuk.webp'],
  ['nasi_padang', 'Nasi Padang', 'Rendang, gulai, the works', 'mood-nasipadang.webp'],
  ['mee_goreng', 'Mee Goreng', 'Wok hei, always hits', 'mood-meegoreng.webp'],
  ['nasi_lemak', 'Nasi Lemak', 'Anytime, anywhere', 'mood-nasilemak.webp'],
  ['char_kuey_teow', 'Char Kuey Teow', 'Smoky and satisfying', 'mood-charkueyteow.webp'],
  ['banana_leaf_rice', 'Banana Leaf Rice', 'Drowned in curry, no regrets', 'mood-bananaleafrice.webp'],
  ['dim_sum', 'Dim Sum', 'Small plates, big satisfaction', 'mood-dimsum.webp'],
  HEALTHY,
  QUICK,
]

const ABROAD: Mood[] = [
  ['indian', 'Indian', 'Curry with rice or naan', 'mood-bananaleafrice.webp'],
  ['malay', 'Taste of home', 'Malaysian and Indonesian', 'mood-nasipadang.webp'],
  ['chicken', 'Fried chicken', 'Crispy, best shared', 'mood-ayamgepuk.webp'],
]
const KEBAB: Mood = ['middle_eastern', 'Kebab', 'Wraps, rice plates, grills', 'mood-kebab.webp']
const BURGER: Mood = ['burger', 'Burger', 'Quick and filling', 'mood-burger.webp']
const PIZZA: Mood = ['pizza', 'Pizza', 'By the slice or the pie', 'mood-pizza.webp']

/** Mood chips by region; the API scores every key the same everywhere. */
const CRAVINGS: Record<Region, Mood[]> = {
  MY: MY_CRAVINGS,
  KR: [KEBAB, ...ABROAD, ['korean', 'Korean', 'Rice bowls, stews, noodles', 'mood-bibimbap.webp'], BURGER, HEALTHY, QUICK],
  US: [['middle_eastern', 'Halal cart', 'Chicken over rice, gyros', 'mood-kebab.webp'], ...ABROAD, BURGER, PIZZA, HEALTHY, QUICK],
  OTHER: [KEBAB, ...ABROAD, BURGER, PIZZA, HEALTHY, QUICK],
}

type Craving = Answers['craving'] | 'anything'
type Step = 0 | 1 | 2

/** "I know what I want": craving, budget, distance. The same three steps as iOS. */
export function Preferences() {
  const { user } = useSession()
  const { decide } = useDecide()
  const navigate = useNavigate()
  const location = useLocation()
  const region = useRegion()
  const [step, setStep] = useState<Step>(0)
  const [craving, setCraving] = useState<Craving | undefined>(undefined)
  const [typed, setTyped] = useState('')
  const [budget, setBudget] = useState<number | null>(2)
  const [lens, setLens] = useState<Lens | undefined>()
  const [distance, setDistance] = useState(2)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const cravingReady = craving !== undefined && !(craving && craving !== 'anything' && 'text' in craving && !craving.text.trim())
  const cravingLabel =
    craving === undefined ? null : craving === 'anything' || craving === null ? 'Anything' : 'tag' in craving ? craving.label : craving.text.trim()
  const community = user?.university ?? user?.area
  const lenses: [Lens, string][] = [
    ['cheap_today', 'Cheap today'],
    ['treat_myself', 'Treat myself'],
    ['quick_one', 'Quick one'],
    ['surprise_me', 'Surprise me'],
    ['community_favs', community ? `${community} favourites` : 'Local favourites'],
  ]

  async function findFood() {
    setBusy(true)
    setError(null)
    setStored('lastBudget', budget)
    setStored('lastDistanceKm', distance)
    try {
      const answers: Answers = { craving: craving === 'anything' || craving === undefined ? null : craving, budgetMax: budget, maxDistanceKm: distance, lens }
      const { request, asked } = await buildSoloRequest(answers, user)
      void decide(request, asked)
      navigate('/decide/result')
    } catch (caught) {
      if (caught instanceof LocationNeeded) {
        setError('MakanApa needs your location to pick somewhere nearby. Allow it, then try again.')
        void location.request()
      } else setError(errorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  function next(event?: FormEvent) {
    event?.preventDefault()
    if (step === 0 && !cravingReady) return
    if (step < 2) setStep((step + 1) as Step)
    else void findFood()
  }

  const answers = [cravingLabel, budget === null ? 'Anything' : budgets(region).find((option) => option.value === budget)?.amount, distanceAmount(distance, region)]

  return (
    <form onSubmit={next} className="flex flex-1 flex-col pt-2">
      <ol className="grid grid-cols-3 gap-2" aria-label="Steps">
        {['Mood', 'Budget', 'Distance'].map((label, index) => (
          <li key={label}>
            <button
              type="button"
              disabled={index > step}
              onClick={() => setStep(index as Step)}
              aria-current={index === step ? 'step' : undefined}
              className="flex w-full flex-col items-start border-t-4 pt-2 text-left disabled:cursor-default"
              style={{ borderColor: index <= step ? 'var(--color-sambal)' : 'var(--color-hairline)' }}
            >
              <span className="text-sm font-semibold text-kicap-soft">{label}</span>
              <span className="min-h-6 font-display font-semibold">{index < step ? answers[index] : ''}</span>
            </button>
          </li>
        ))}
      </ol>

      <div className="flex-1 py-8">
        {step === 0 && (
          <Section title="What mood today?" subtitle="What are you craving?">
            <label className="block">
              <span className="sr-only">Type a craving</span>
              <span className="relative block">
                <input
                  value={typed}
                  onChange={(event) => {
                    setTyped(event.target.value)
                    setCraving(event.target.value ? { text: event.target.value } : undefined)
                  }}
                  placeholder="Tell me what you’re craving…"
                  maxLength={100}
                  enterKeyHint="next"
                  className="min-h-14 w-full rounded-row border-2 border-hairline bg-surface px-4 pr-12 text-lg placeholder:text-kicap-soft focus:border-kicap focus:outline-none"
                />
                {typed && (
                  <button
                    type="button"
                    aria-label="Clear craving"
                    onClick={() => {
                      setTyped('')
                      setCraving(undefined)
                    }}
                    className="absolute right-1 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center text-kicap-soft"
                  >
                    ✕
                  </button>
                )}
              </span>
            </label>

            <Choice
              selected={craving === 'anything'}
              onSelect={() => {
                setTyped('')
                setCraving('anything')
              }}
              title="Anything"
              subtitle="Good food can surprise me."
              className="mt-4"
            />

            <p className="mt-6 font-hand text-2xl text-kicap-soft">Or pick one</p>
            <div className="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" role="radiogroup" aria-label="Cravings">
              {CRAVINGS[region].map(([tag, label, subtitle, image]) => {
                const selected = craving !== undefined && craving !== null && craving !== 'anything' && 'tag' in craving && craving.tag === tag
                return (
                  <button
                    key={tag}
                    type="button"
                    role="radio"
                    aria-checked={selected}
                    onClick={() => {
                      setTyped('')
                      setCraving({ tag, label })
                    }}
                    className={`press flex flex-col overflow-hidden rounded-row border-2 bg-surface text-left ${selected ? 'border-sambal shadow-lift' : 'border-hairline'}`}
                  >
                    <img src={`/choices/${image}`} alt="" width={320} height={240} loading="lazy" className="aspect-[4/3] w-full bg-cream object-cover" />
                    <span className="flex flex-col px-3 py-2.5">
                      <span className="font-display text-base font-semibold leading-tight">{label}</span>
                      <span className="text-xs text-kicap-soft">{subtitle}</span>
                    </span>
                  </button>
                )
              })}
            </div>
          </Section>
        )}

        {step === 1 && (
          <Section title="What’s the budget?" subtitle="Per person. Good food at every budget.">
            <div className="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Budget">
              {budgets(region).map((option) => (
                <Choice
                  key={option.value}
                  selected={budget === option.value}
                  onSelect={() => setBudget(option.value)}
                  title={option.amount}
                  subtitle={option.title}
                  image={option.image}
                />
              ))}
              <Choice selected={budget === null} onSelect={() => setBudget(null)} title="Anything" subtitle="No budget limit. Just make it good." />
            </div>

            <fieldset className="mt-8">
              <legend className="font-display text-lg font-semibold">Today I want…</legend>
              <p className="text-sm text-kicap-soft">Optional. Tells MakanApa how to decide, not just what to filter.</p>
              <div className="mt-3 flex flex-wrap gap-2">
                {lenses.map(([key, label]) => (
                  <button
                    key={key}
                    type="button"
                    aria-pressed={lens === key}
                    onClick={() => setLens(lens === key ? undefined : key)}
                    className={`press min-h-11 rounded-full border-2 px-4 font-display font-semibold ${lens === key ? 'border-kicap bg-kicap text-cream' : 'border-hairline text-kicap'}`}
                  >
                    {label}
                  </button>
                ))}
              </div>
            </fieldset>
          </Section>
        )}

        {step === 2 && (
          <Section title="How far will you go?" subtitle="Stay nearby or go a little further for good food.">
            <div className="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Distance">
              {distances(region).map((option) => (
                <Choice
                  key={option.km}
                  selected={distance === option.km}
                  onSelect={() => setDistance(option.km)}
                  title={option.title}
                  subtitle={option.subtitle}
                  image={option.image}
                />
              ))}
            </div>
            <p className="mt-4 text-sm text-kicap-soft">Distance from your current location.</p>
            <div className="mt-6">
              <ContextStrip coords={location.state.status === 'ready' ? location.state.coords : null} />
            </div>
          </Section>
        )}
      </div>

      <div className="sticky bottom-(--tab-bar) flex flex-col gap-2 bg-cream/95 pb-2 pt-3 backdrop-blur md:bottom-0">
        {error && (
          <p role="alert" className="text-sm text-sambal-deep">
            {error}
          </p>
        )}
        <p className="text-center text-sm text-kicap-soft">
          {step === 0 ? (cravingReady ? 'Next up, your budget' : 'Pick a mood, or leave it to us') : step === 1 ? 'One last thing, how far?' : 'We’ll take it from here.'}
        </p>
        <Button type="submit" disabled={step === 0 && !cravingReady} busy={busy} className="md:mx-auto md:max-w-sm">
          {step < 2 ? 'Continue' : 'Find my food'}
        </Button>
        {step === 0 && (
          <Button variant="quiet" onClick={() => navigate(-1)} className="md:mx-auto">
            Back
          </Button>
        )}
        {step > 0 && (
          <Button variant="quiet" onClick={() => setStep((step - 1) as Step)} className="md:mx-auto">
            Back
          </Button>
        )}
      </div>
    </form>
  )
}

function Section({ title, subtitle, children }: { title: string; subtitle: string; children: ReactNode }) {
  return (
    <section>
      <h1 className="font-display text-4xl font-bold tracking-tight md:text-5xl">{title}</h1>
      <p className="mt-2 text-lg text-kicap-soft">{subtitle}</p>
      <div className="mt-6">{children}</div>
    </section>
  )
}

function Choice({ selected, onSelect, title, subtitle, image, className = '' }: {
  selected: boolean
  onSelect: () => void
  title: string
  subtitle: string
  image?: string
  className?: string
}) {
  return (
    <button
      type="button"
      role="radio"
      aria-checked={selected}
      onClick={onSelect}
      className={`press flex min-h-20 w-full items-center gap-4 rounded-row border-2 bg-surface px-4 py-3 text-left ${selected ? 'border-sambal shadow-lift' : 'border-hairline'} ${className}`}
    >
      {image && <img src={image} alt="" width={56} height={56} className="h-14 w-14 shrink-0" />}
      <span className="flex-1">
        <span className="block font-display text-xl font-semibold">{title}</span>
        <span className="text-sm text-kicap-soft">{subtitle}</span>
      </span>
      <span aria-hidden="true" className={`h-6 w-6 shrink-0 rounded-full border-2 ${selected ? 'border-[7px] border-sambal' : 'border-hairline'}`} />
    </button>
  )
}
