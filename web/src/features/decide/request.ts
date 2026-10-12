import type { Lens, SoloRequest } from '../../api/decide'
import type { User } from '../../api/types'
import { getPosition } from '../../lib/location'
import { budgetAmount, withinLabel } from '../../lib/presentation'
import { getStored } from '../../lib/storage'
import type { AskedFor } from './state'

export class LocationNeeded extends Error {}

export interface Answers {
  /** A craving tag, typed text, or null for "Anything". */
  craving: { tag: string; label: string } | { text: string } | null
  budgetMax: number | null
  maxDistanceKm: number
  lens?: Lens
}

/** Turns the person's answers into the solo request iOS sends, plus what the thinking screen echoes. */
export async function buildSoloRequest(answers: Answers, user: User | null): Promise<{ request: SoloRequest; asked: AskedFor }> {
  let coords
  try {
    coords = await getPosition()
  } catch {
    throw new LocationNeeded()
  }

  const { craving, budgetMax, maxDistanceKm, lens } = answers
  const request: SoloRequest = {
    ...coords,
    maxDistanceKm,
    moods: craving && 'tag' in craving ? [craving.tag] : [],
    halal: user?.halalPreference ?? getStored('halalOnly') ?? false,
    ignoreContext: getStored('ignoredContext') ?? [],
  }
  if (budgetMax !== null) request.budgetMax = budgetMax
  if (craving && 'text' in craving) request.craving = craving.text.replace(/\s+/g, ' ').trim()
  if (lens) request.lens = lens

  const amount = budgetAmount(budgetMax)
  return {
    request,
    asked: {
      mood: craving ? ('tag' in craving ? craving.label : request.craving!) : 'Anything',
      budget: amount ? `${amount} per person` : 'No budget limit',
      distance: withinLabel(maxDistanceKm),
    },
  }
}

/** Quick pick: "Anything", with the budget and distance from the last full flow (iOS defaults). */
export function quickPickAnswers(): Answers {
  const lastBudget = getStored('lastBudget')
  return { craving: null, budgetMax: lastBudget === undefined ? 2 : lastBudget, maxDistanceKm: getStored('lastDistanceKm') ?? 2 }
}
