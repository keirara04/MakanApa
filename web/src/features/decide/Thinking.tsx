import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import type { AskedFor } from './state'

/** The plate and spoon from the website hero. Only the spoon turns; the mascot stays still. */
export function Plate({ size = 200 }: { size?: number }) {
  return (
    <svg viewBox="0 0 100 100" width={size} height={size} aria-hidden="true" className="overflow-visible">
      <circle cx="50" cy="50" r="47" fill="var(--color-surface)" stroke="var(--color-kicap)" strokeWidth="1.2" filter="url(#sketch-a)" />
      <circle cx="50" cy="50" r="29" fill="none" stroke="var(--color-kicap)" strokeWidth="0.5" strokeDasharray="1.4 1.6" opacity="0.4" />
      <g className="spoon-spin">
        <ellipse cx="50" cy="24" rx="8" ry="11" fill="var(--color-kunyit)" stroke="var(--color-kicap)" strokeWidth="1.4" />
        <path d="M50 35 C 48.6 45, 48.3 58, 48 70 a 2 2 0 0 0 4 0 C 51.7 58, 51.4 45, 50 35 Z" fill="var(--color-cream)" stroke="var(--color-kicap)" strokeWidth="1.4" strokeLinejoin="round" />
        <circle cx="50" cy="50" r="2" fill="var(--color-sambal)" />
      </g>
    </svg>
  )
}

export function Thinking({ asked, onCancel }: { asked: AskedFor | null; onCancel: () => void }) {
  return (
    <section className="flex flex-1 flex-col items-center justify-center gap-6 py-8 text-center" aria-busy="true">
      <div className="relative">
        <Plate />
        <Mascot mood="thinking" size={96} className="absolute -bottom-6 -left-10" />
      </div>
      <div>
        <h1 className="font-display text-4xl font-bold tracking-tight">Finding your next meal.</h1>
        <p className="mt-2 text-kicap-soft">Hang tight. We’re looking for a spot that fits your craving.</p>
      </div>
      {asked && (
        <dl className="sketch w-full max-w-sm px-5 py-4 text-left" style={{ ['--sketch-radius' as string]: '18px' }}>
          <dt className="font-hand text-2xl text-kicap-soft">Your kind of food</dt>
          <dd className="mt-1 font-display text-lg font-semibold">{asked.mood}</dd>
          <dd className="text-kicap-soft">{asked.budget}</dd>
          <dd className="text-kicap-soft">{asked.distance}</dd>
        </dl>
      )}
      <p role="status" className="text-sm text-kicap-soft">
        Looking for nearby spots…
      </p>
      <Button variant="quiet" onClick={onCancel}>
        Cancel
      </Button>
    </section>
  )
}

export function Rerolling({ onCancel }: { onCancel: () => void }) {
  return (
    <section className="flex flex-1 flex-col items-center justify-center gap-6 py-8 text-center" aria-busy="true">
      <Plate size={160} />
      <div>
        <h1 className="font-display text-4xl font-bold tracking-tight">Finding another one!</h1>
        <p className="mt-2 text-kicap-soft">Same preferences. Different spot.</p>
      </div>
      <p role="status" className="text-sm text-kicap-soft">
        Still good, don’t worry
      </p>
      <Button variant="quiet" onClick={onCancel}>
        Cancel
      </Button>
    </section>
  )
}
