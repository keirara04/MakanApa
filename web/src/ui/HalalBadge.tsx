import type { HalalInfo } from '../api/decide'

const TONE: Record<HalalInfo['display']['tone'], string> = {
  certified: 'bg-pandan text-white',
  friendly: 'bg-pandan/15 text-pandan',
  neutral: 'bg-hairline text-kicap',
  warning: 'bg-kunyit/40 text-kicap',
  non_halal: 'bg-kicap text-cream',
}

/** The server's halal wording (HalalPresenter) as a pill; colour from the tone only. */
export function HalalBadge({ halal }: { halal: { display: Pick<HalalInfo['display'], 'shortLabel' | 'tone'> } }) {
  return <span className={`inline-flex min-h-8 items-center rounded-full px-3 text-sm font-semibold ${TONE[halal.display.tone]}`}>{halal.display.shortLabel}</span>
}
