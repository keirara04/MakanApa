import { Mascot, type MascotMood } from '../../ui/Mascot'

/** Stand-in for the tabs that land in later phases (ANDROID_PWA_PLAN.md, section 7). */
export function ComingSoon({ title, detail, mood }: { title: string; detail: string; mood: MascotMood }) {
  return (
    <section className="flex flex-1 flex-col items-center justify-center text-center">
      <Mascot mood={mood} size={160} />
      <h1 className="mt-6 font-display text-3xl font-bold tracking-tight">{title}</h1>
      <p className="mt-3 max-w-xs text-kicap-soft">{detail}</p>
    </section>
  )
}
