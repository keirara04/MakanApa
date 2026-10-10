// Browser walkthrough of the Decide flow with the pick endpoints mocked, so it never spends
// Google Places budget (a real solo pick queries Places with the API's key).
//
//   npm i --no-save playwright && npx playwright install chromium   (once)
//   npm run dev                                                     (web app on :5174, API on :8000)
//   SCENARIO=brain node scripts/decide-walkthrough.mjs ./shots 412
//
// SCENARIO: brain (Brain on, the dev default), v1 (production: no Brain fields), none (no
// result), limited (429), saved (saved-places shuffle). Onboarding uses the real local API and
// leaves a guest account behind in the local database.
import { chromium } from 'playwright'

const [, , out = '.', width = '412'] = process.argv
const scenario = process.env.SCENARIO ?? 'brain'
const W = Number(width)
const APP = 'http://localhost:5174'

const browser = await chromium.launch()
const ctx = await browser.newContext({
  viewport: { width: W, height: W < 700 ? 915 : 860 },
  deviceScaleFactor: W < 700 ? 2 : 1,
  geolocation: { latitude: 2.929, longitude: 101.7775 },
  permissions: ['geolocation'],
})
const page = await ctx.newPage()
const problems = []
page.on('pageerror', (error) => problems.push(error.message))
const requests = []

const BRAIN_KEYS = ['reasons', 'decidingFactor', 'fit', 'thinkingTrace', 'context', 'hasWhatIf', 'canTune', 'fatigue']
const pick = (id, name, extra = {}) => {
  const full = {
    id, name, shareUrl: `https://trymakanapa.com/p/${id}-x?ref=share`, headline: 'NASI KANDAR.', foodCategory: 'mamak',
    latitude: 2.93, longitude: 101.78, distanceKm: 0.4, rating: 4.6, priceLevel: 1, cuisines: ['malaysian_indian'],
    openStatus: 'open', closesAt: '11:00 PM',
    photos: [{ url: `${APP}/choices/mood-nasikandar.webp`, authorAttributions: [{ name: 'A', profileUrl: null }] }],
    reviews: [{ text: 'Kuah campur is the best around here.', rating: 5, authorName: 'Aina', relativePublishTime: 'a month ago', googleMapsUrl: null }],
    placeGoogleMapsUrl: 'https://maps.google.com/?cid=123', menuItems: [],
    halal: { status: 'unknown', reviewState: 'clear', display: { shortLabel: 'Not verified · Help verify', longLabel: 'Halal status not verified yet. Help us verify', tone: 'neutral', action: 'help_verify', verificationLabel: null }, verification: null, historyCount: 0, recentPickers: 0 },
    reasons: [{ family: 'match', key: 'craving', text: 'Matches your craving' }], decidingFactor: 'Deciding factor: closest strong match.',
    fit: 'strong', hasWhatIf: true, canTune: true, thinkingTrace: ['Checked 24 nearby spots', 'Picked this one'],
    ...extra,
  }
  if (scenario === 'v1' || scenario === 'saved') BRAIN_KEYS.forEach((key) => delete full[key])
  return full
}
const decision = (recommendation) => ({ decisionId: 77, clientToken: 't'.repeat(40), algorithmVersion: scenario === 'v1' ? 'v1' : 'v2', recommendation })

await page.route('**/api/v1/recommendations/solo', (route) => {
  requests.push(['solo', route.request().postDataJSON()])
  if (scenario === 'limited') return route.fulfill({ status: 429, headers: { 'Retry-After': '42' }, json: { message: 'Too Many Attempts.' } })
  return route.fulfill({ json: decision(scenario === 'none' ? null : pick(1, 'Restoran Nasi Kandar Pelita')) })
})
await page.route('**/api/v1/recommendations/saved', (route) => {
  requests.push(['saved', route.request().postDataJSON()])
  return route.fulfill({ json: decision(pick(3, 'Saved Spot One')) })
})
await page.route('**/api/v1/decisions/77/reroll', (route) => {
  requests.push(['reroll', route.request().headers()['x-decision-token']?.length])
  return route.fulfill({ json: { recommendation: pick(2, 'Kedai Mee Goreng Ali', { photos: [] }) } })
})
await page.route('**/api/v1/decisions/77/{accept,interactions,why-not}', (route) => route.fulfill({ json: { recorded: true, accepted: true } }))
await page.route('**/api/v1/context*', (route) => route.fulfill({ json: { mealSlot: 'dinner', signals: [] } }))

const shot = async (name) => {
  await page.waitForTimeout(500)
  await page.screenshot({ path: `${out}/${scenario}-${W}-${name}.png`, fullPage: true })
}

// Onboard as a guest against the real local API.
await page.goto(`${APP}/welcome`, { waitUntil: 'networkidle' })
await page.getByRole('button', { name: 'Get started' }).click()
await page.waitForTimeout(800)
if (await page.getByRole('button', { name: 'Turn on location' }).count()) await page.getByRole('button', { name: 'Turn on location' }).click()
await page.getByRole('button', { name: 'Continue' }).click()
await page.getByRole('button', { name: 'No, show everything' }).click()
await page.waitForURL(`${APP}/`)

if (scenario === 'saved') {
  await page.evaluate(() => {
    const stored = JSON.parse(localStorage.getItem('makanapa:v1') ?? '{}')
    stored.saved = [
      { id: 3, name: 'Saved Spot One', latitude: 2.93, longitude: 101.78, savedAt: Date.now() },
      { id: 4, name: 'Saved Spot Two', latitude: 2.94, longitude: 101.77, savedAt: Date.now() },
    ]
    localStorage.setItem('makanapa:v1', JSON.stringify(stored))
  })
  await page.reload({ waitUntil: 'networkidle' })
  await page.getByRole('link', { name: /Pick from my saved/ }).click()
  await shot('1-shuffling')
  await page.waitForURL(`${APP}/decide/result`, { timeout: 10_000 })
  await shot('2-result')
  await page.getByRole('button', { name: 'Another one' }).click()
  await page.waitForTimeout(1300)
  await shot('3-rerolled')
} else {
  await page.getByRole('button', { name: /Pick for me/ }).click()
  await page.waitForTimeout(2600)
  await shot('1-result')
}

console.log(scenario, W, 'requests', JSON.stringify(requests))
console.log(scenario, W, 'page errors', JSON.stringify(problems))
await browser.close()
