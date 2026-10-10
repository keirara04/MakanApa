import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { accountApi, type Selera, type SeleraStage, type SeleraTrait } from '../../api/account'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { Sheet } from '../../ui/Sheet'
import { SubPage } from './SubPage'

const STAGES: Record<SeleraStage, [string, string]> = {
  starting: ['Just getting started', 'Pick and accept a few places. Every choice teaches me a bit more.'],
  learning: ['Learning your selera', 'A few patterns so far. Correct anything that’s off.'],
  knowing: ['Getting to know you', 'Your picks are shaping recommendations now.'],
  strong: ['I know your selera', 'Built from a lot of your decisions. Still editable anytime.'],
}
const ORDER: SeleraStage[] = ['starting', 'learning', 'knowing', 'strong']
const STRENGTH = { strong: 'Strong signal', medium: 'Getting clearer', emerging: 'Early hint' } as const

/** What MakanApa has learned about your taste, and how to correct it. */
export function SeleraPage() {
  const queryClient = useQueryClient()
  const { data, isLoading, isError, refetch } = useQuery({ queryKey: ['selera'], queryFn: accountApi.selera })
  const [confirmReset, setConfirmReset] = useState(false)
  const update = (selera: Selera) => queryClient.setQueryData(['selera'], selera)

  if (isLoading) return <SubPage title="Your Selera"><p role="status">Loading…</p></SubPage>
  if (isError || !data) {
    return (
      <SubPage title="Your Selera">
        <p>No connection. Couldn’t load your Selera.</p>
        <Button variant="secondary" className="max-w-xs" onClick={() => void refetch()}>
          Try again
        </Button>
      </SubPage>
    )
  }

  const [title, detail] = STAGES[data.stage]
  const reached = ORDER.indexOf(data.stage)

  return (
    <SubPage title="Your Selera">
      <section className="sketch flex items-center gap-4 px-5 py-5" style={{ ['--sketch-radius' as string]: '20px' }}>
        <Mascot mood={data.stage === 'strong' ? 'excited' : data.stage === 'knowing' ? 'wave' : 'thinking'} size={72} />
        <div className="flex-1">
          <h2 className="font-display text-xl font-bold">{title}</h2>
          <p className="text-sm text-kicap-soft">{detail}</p>
          <div className="mt-3 flex gap-1.5" role="img" aria-label={`Stage ${reached + 1} of 4, ${data.signalCount} picks so far`}>
            {ORDER.map((stage, index) => (
              <span key={stage} className={`h-2 flex-1 rounded-full ${index <= reached ? 'bg-sambal' : 'bg-hairline'}`} />
            ))}
          </div>
          <p className="mt-1 text-xs text-kicap-soft" aria-hidden="true">
            {data.signalCount === 1 ? '1 pick so far' : `${data.signalCount} picks so far`}
          </p>
        </div>
      </section>

      <section>
        <h2 className="font-display text-xl font-bold">What I’ve picked up</h2>
        {data.traits.length === 0 ? (
          <p className="mt-2 text-kicap-soft">Still learning. Pick a few places and I’ll figure out your selera.</p>
        ) : (
          <>
            <p className="text-sm text-kicap-soft">Tap one to see why, or correct it.</p>
            <ul className="mt-3 flex flex-col gap-2">
              {data.traits.map((trait) => (
                <TraitCard key={trait.key} trait={trait} onUpdate={update} />
              ))}
            </ul>
          </>
        )}
      </section>

      {data.startingPicks.length > 0 && (
        <section className="rounded-row bg-surface px-4 py-4">
          <p className="font-semibold">Your starting picks: {data.startingPicks.join(', ')}</p>
          <p className="text-sm text-kicap-soft">From when you joined. They nudge your first recommendations until your real picks take over.</p>
        </section>
      )}

      <section>
        <h2 className="font-display text-xl font-bold">Always applied</h2>
        <p className="text-sm text-kicap-soft">Requirements, not taste. MakanApa never learns around these.</p>
        <ul className="mt-2 divide-y-2 divide-hairline border-y-2 border-hairline">
          {data.constraints.map((constraint) => (
            <li key={constraint.key} className="flex min-h-13 items-center justify-between py-2">
              <span className="font-semibold">{constraint.label}</span>
              {constraint.editIn === 'each_decision' ? (
                <span className="text-sm text-kicap-soft">Set each time</span>
              ) : (
                <span className={`rounded-full px-2.5 py-0.5 text-sm font-semibold ${constraint.value ? 'bg-pandan/15 text-pandan' : 'bg-hairline'}`}>{constraint.value ? 'On' : 'Off'}</span>
              )}
            </li>
          ))}
        </ul>
      </section>

      <section className="flex flex-col items-start gap-1">
        <Button variant="secondary" className="max-w-xs" onClick={() => setConfirmReset(true)}>
          Reset my Selera
        </Button>
        <p className="text-sm text-kicap-soft">Starts learning from scratch. Your past picks stay in your history.</p>
      </section>

      <Sheet open={confirmReset} onClose={() => setConfirmReset(false)} title="Reset your Selera?">
        <p>MakanApa will forget what it learned about your taste.</p>
        <Button
          onClick={() => {
            setConfirmReset(false)
            void accountApi.seleraReset().then(update).catch(() => {})
          }}
        >
          Reset
        </Button>
        <Button variant="quiet" onClick={() => setConfirmReset(false)}>
          Cancel
        </Button>
      </Sheet>
    </SubPage>
  )
}

function TraitCard({ trait, onUpdate }: { trait: SeleraTrait; onUpdate: (selera: Selera) => void }) {
  const [open, setOpen] = useState(false)
  const [sending, setSending] = useState<string | null>(null)
  const [saved, setSaved] = useState<'more' | 'less' | null>(null)
  const [error, setError] = useState(false)

  function send(kind: 'more' | 'less' | 'not_really' | 'hide') {
    setSending(kind)
    setError(false)
    const request = kind === 'hide' ? accountApi.seleraHide(trait.key) : accountApi.seleraFeedback(trait.key, kind)
    request
      .then((selera) => {
        if (kind === 'more' || kind === 'less') setSaved(kind)
        onUpdate(selera)
      })
      .catch(() => setError(true))
      .finally(() => setSending(null))
  }

  return (
    <li className="rounded-row border-2 border-hairline bg-surface">
      <button type="button" aria-expanded={open} onClick={() => setOpen(!open)} className="flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left">
        <span className="flex-1 font-semibold">{trait.label}</span>
        <span className="rounded-full bg-cream px-2 py-0.5 text-xs font-semibold">{STRENGTH[trait.strength]}</span>
        <span aria-hidden="true" className={`transition-transform ${open ? 'rotate-90' : ''}`}>
          ›
        </span>
      </button>
      {open && (
        <div className="flex flex-col gap-3 px-4 pb-4">
          <p className="rounded-row bg-cream px-3 py-2 text-sm">{trait.evidence}</p>
          <div className="grid grid-cols-2 gap-2">
            {(
              [
                ['more', 'More like this'],
                ['less', 'Less'],
                ['not_really', 'Not really'],
                ['hide', 'Hide'],
              ] as const
            ).map(([kind, label]) => (
              <button
                key={kind}
                type="button"
                disabled={sending !== null || saved === kind}
                aria-busy={sending === kind || undefined}
                onClick={() => send(kind)}
                className={`press min-h-11 rounded-full border-2 px-3 text-sm font-semibold disabled:opacity-60 ${kind === 'not_really' ? 'border-sambal/40 text-sambal-deep' : 'border-hairline'} ${saved === kind ? 'bg-kicap text-cream' : ''}`}
              >
                {sending === kind ? 'Saving…' : label}
              </button>
            ))}
          </div>
          {saved && <p role="status" className="text-sm text-pandan">{saved === 'more' ? 'Got it. I’ll lean into this.' : 'Got it. I’ll ease off this.'}</p>}
          {error && <p role="alert" className="text-sm text-sambal-deep">Couldn’t save that. Try again.</p>}
        </div>
      )}
    </li>
  )
}
