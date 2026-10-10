// Browser walkthrough of Nearby (list mode, no Maps key) with every Google-backed endpoint mocked,
// so it spends nothing on Places. Setup as in decide-walkthrough.mjs:
//   node scripts/nearby-walkthrough.mjs ./shots 412
// Onboarding uses the real local API and leaves a guest account behind in the local database.
import { chromium } from 'playwright'

const [, , out = '.', width = '412'] = process.argv
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
const calls = []
page.on('pageerror', (error) => problems.push(error.message))

const halal = (status, shortLabel, tone) => ({ status, display: { shortLabel, longLabel: shortLabel, tone, action: null } })
const marker = (id, name, rating, extra = {}) => ({ id, name, rating, priceLevel: 1, latitude: 2.929 + id / 2000, longitude: 101.7775, openStatus: 'open', halal: halal('unknown', 'Not verified · Help verify', 'neutral'), ...extra })
const places = [
  marker(1, 'Restoran Nasi Kandar Pelita', 4.6, { halal: halal('certified', 'Halal (JAKIM)', 'certified') }),
  marker(2, 'Kopi Senja', 4.8, { priceLevel: 3 }),
  marker(3, 'Mee Goreng Mamak Ali', 4.1, { openStatus: 'closed' }),
  marker(4, 'Warung Pak Din', null),
]
const summary = {
  placeCount: 4, openNowCount: 3, budgetFriendlyCount: 3, topCategories: [{ label: 'mamak', count: 2 }],
  topRated: [places[1], places[0], places[2]], communityFinds: [places[3]],
  personalityTags: [{ key: 'budget_friendly', label: 'Budget-friendly' }],
}

await page.route('**/api/v1/places/nearby?*', (route) => {
  calls.push(['nearby', Object.fromEntries(new URL(route.request().url()).searchParams)])
  return route.fulfill({ json: { places, areaSummary: summary } })
})
await page.route('**/api/v1/places/search?*', (route) => {
  const query = new URL(route.request().url()).searchParams
  calls.push(['search', Object.fromEntries(query)])
  if (query.get('query') === 'zzz') {
    return route.fulfill({ json: { results: [], meta: { radiusKm: 6, source: 'local', googleAvailable: true, widerRadiusKm: 12, total: 0 }, suggestions: ['nasi lemak'] } })
  }
  return route.fulfill({
    json: {
      results: [
        { provenance: 'canonical', id: 1, googlePlaceId: null, name: 'Restoran Nasi Kandar Pelita', address: 'Jalan 2, Bangi', category: 'Mamak', cuisine: null, latitude: 2.9295, longitude: 101.7775, priceLevel: 1, rating: 4.6, distanceKm: 0.3, openStatus: 'open', closesAt: '11:00 PM', halal: halal('certified', 'Halal (JAKIM)', 'certified'), isCommunityFind: false, shareUrl: 'https://trymakanapa.com/p/1-x', groupKey: null, groupSize: 1 },
        { provenance: 'google_fallback', id: null, googlePlaceId: 'g-77', name: 'Nasi Kandar Beratur', address: 'Penang Road', category: null, cuisine: null, latitude: 2.94, longitude: 101.78, priceLevel: null, rating: 4.3, distanceKm: 1.4, openStatus: 'unknown', closesAt: null, halal: halal('unknown', 'Not verified · Help verify', 'neutral'), isCommunityFind: false, shareUrl: null, groupKey: null, groupSize: 1 },
      ],
      meta: { radiusKm: 6, source: 'mixed', googleAvailable: false, widerRadiusKm: 12, total: 2 },
      suggestions: [],
    },
  })
})
await page.route('**/api/v1/places/resolve', (route) => {
  calls.push(['resolve', route.request().postDataJSON()])
  return route.fulfill({ json: { restaurant: { provenance: 'canonical', id: 77, googlePlaceId: 'g-77', name: 'Nasi Kandar Beratur', address: 'Penang Road', category: 'Mamak', cuisine: null, latitude: 2.94, longitude: 101.78, priceLevel: 2, rating: 4.3, distanceKm: 1.4, openStatus: 'open', closesAt: null, halal: halal('unknown', 'Not verified · Help verify', 'neutral'), isCommunityFind: false, shareUrl: 'https://trymakanapa.com/p/77-x', groupKey: null, groupSize: 1 } } })
})
await page.route('**/api/v1/restaurants/*/details', (route) =>
  route.fulfill({
    json: {
      id: 1, name: 'Restoran Nasi Kandar Pelita', latitude: 2.9295, longitude: 101.7775, shareUrl: 'https://trymakanapa.com/p/1-x', foodCategory: 'mamak', rating: 4.6, priceLevel: 1, cuisines: [], openStatus: 'open',
      photos: [{ url: `${APP}/choices/mood-nasikandar.webp`, authorAttributions: [] }], reviews: [{ text: 'Queue moves fast.', rating: 5, authorName: 'Aina', relativePublishTime: null, googleMapsUrl: null }],
      placeGoogleMapsUrl: 'https://maps.google.com/?cid=1', closesAt: '11:00 PM', phone: '+60 3-1234 5678', instagramHandle: '@pelita', tiktokHandle: null, websiteUrl: null,
      menuItems: [{ name: 'Nasi kandar ayam', description: null, price: 9.5, category: null }], communityPhotos: [],
      halal: { status: 'certified', reviewState: 'clear', display: { shortLabel: 'Halal (JAKIM)', longLabel: 'JAKIM halal certified', tone: 'certified', action: null, verificationLabel: null }, verification: null, historyCount: 0, recentPickers: 0 },
    },
  }),
)
await page.route('**/api/v1/restaurants/*/choose', (route) => {
  calls.push(['choose', route.request().postDataJSON()])
  return route.fulfill({ status: 201, json: { decisionId: 9, clientToken: 'c'.repeat(40), created: true } })
})
await page.route('**/api/v1/places/nearby/pick', (route) => {
  calls.push(['pick', route.request().postDataJSON()])
  return route.fulfill({ json: { decisionId: 5, clientToken: 'p'.repeat(40), algorithmVersion: 'v1', recommendation: { id: 2, name: 'Kopi Senja', shareUrl: 'https://trymakanapa.com/p/2-x', headline: 'CAFE.', foodCategory: 'cafe', latitude: 2.93, longitude: 101.7775, distanceKm: 0.1, rating: 4.8, priceLevel: 3, cuisines: [], openStatus: 'open', closesAt: null, photos: [], reviews: [], placeGoogleMapsUrl: null, menuItems: [], halal: { status: 'unknown', reviewState: 'clear', display: { shortLabel: 'Not verified · Help verify', longLabel: 'Halal status not verified yet. Help us verify', tone: 'neutral', action: 'help_verify', verificationLabel: null }, verification: null, historyCount: 0, recentPickers: 0 } } } })
})

const shot = async (name) => {
  await page.waitForTimeout(500)
  await page.screenshot({ path: `${out}/nearby-${W}-${name}.png`, fullPage: true })
}

// Onboard as a guest against the real local API.
await page.goto(`${APP}/welcome`, { waitUntil: 'networkidle' })
await page.getByRole('button', { name: 'Get started' }).click()
await page.waitForTimeout(800)
if (await page.getByRole('button', { name: 'Turn on location' }).count()) await page.getByRole('button', { name: 'Turn on location' }).click()
await page.getByRole('button', { name: 'Continue' }).click()
await page.getByRole('button', { name: 'No, show everything' }).click()
await page.waitForURL(`${APP}/`)

await page.getByRole('link', { name: 'Nearby' }).first().click()
await page.getByRole('heading', { name: 'Around here' }).waitFor()
await shot('1-list')
await page.getByRole('button', { name: '4.5+ ★' }).click()
await page.getByRole('button', { name: 'Cafe' }).click()
await shot('2-filters')
await page.getByRole('button', { name: 'Reset filters' }).click()

await page.getByRole('button', { name: /Restoran Nasi Kandar Pelita/ }).first().click()
await page.getByRole('dialog').getByText('Nasi kandar ayam').waitFor()
await shot('3-place')
await page.getByRole('button', { name: 'Eat here' }).click()
await page.getByRole('button', { name: 'Added to Recent' }).waitFor()
await page.keyboard.press('Escape')

await page.getByRole('button', { name: 'Search places, food, or cuisine' }).click()
await page.getByRole('searchbox').fill('zzz')
await page.waitForTimeout(700)
await shot('4-search-empty')
await page.getByRole('searchbox').fill('nasi kandar')
await page.waitForTimeout(700)
await shot('5-search')
await page.getByRole('button', { name: /Nasi Kandar Beratur/ }).click()
await page.getByRole('dialog').waitFor()
await shot('6-resolved-place')
await page.getByRole('button', { name: /Results for/ }).click()
await page.getByRole('button', { name: 'Close search' }).click()

await page.getByRole('button', { name: 'Pick for me' }).first().click()
await page.waitForURL(`${APP}/nearby/result`, { timeout: 10_000 })
await shot('7-pick')

console.log(W, 'calls', JSON.stringify(calls))
console.log(W, 'page errors', JSON.stringify(problems))
await browser.close()
