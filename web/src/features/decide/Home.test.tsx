import { cleanup, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { afterEach, describe, expect, it } from 'vitest'
import type { SavedPlace } from '../../lib/storage'
import { SavedShuffleCard } from './Home'

const place = (id: number): SavedPlace => ({ id, name: `Place ${id}`, latitude: 3.1, longitude: 101.7, savedAt: 0 })

function card(saved: SavedPlace[]) {
  render(
    <MemoryRouter>
      <SavedShuffleCard saved={saved} />
    </MemoryRouter>,
  )
  return screen.getByRole('link')
}

afterEach(cleanup)

describe('saved places shuffle card', () => {
  it('is always on Home, sending people to Nearby until two places are saved', () => {
    expect(card([]).getAttribute('href')).toBe('/nearby')
    cleanup()
    const one = card([place(1)])
    expect(one.getAttribute('href')).toBe('/nearby')
    expect(one.textContent).toContain('Save one more place')
  })

  it('opens the shuffle once two or more are saved', () => {
    const ready = card([place(1), place(2), place(3)])
    expect(ready.getAttribute('href')).toBe('/decide/saved')
    expect(ready.textContent).toContain('Shuffle your 3 saved places')
  })
})
