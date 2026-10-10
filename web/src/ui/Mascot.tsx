/**
 * The mascot, from the refreshed art set (marketing/mascot-refresh/). It never moves and is
 * never named in the interface until Plus launches. Decorative: what it means is always said in
 * text nearby.
 */
export type MascotMood = 'wave' | 'thinking' | 'excited' | 'sad'

export function Mascot({ mood, size = 160, className = '' }: { mood: MascotMood; size?: number; className?: string }) {
  const src = mood === 'wave' && size > 160 ? '/mascot/wave-640.webp' : `/mascot/${mood}-320.webp`
  return <img src={src} alt="" aria-hidden="true" width={size} height={size} className={`select-none ${className}`} draggable={false} />
}
