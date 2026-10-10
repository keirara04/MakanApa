// Browser walkthrough of the account screens (phase 4) against a local API, for real: sign-up,
// edit profile, community, Your Selera, saved places (search mocked: it can reach Google),
// blocked people, forgot password, then deleting the account. Needs the webapp branch's API
// (password reset), e.g. `php artisan serve --port=8001` and
// `VITE_API_BASE_URL=http://localhost:8001/api/v1 npm run dev`. Then:
//   node scripts/account-walkthrough.mjs ./shots 412
// It creates and then deletes one account in the local database.
import { chromium } from 'playwright'

const [, , out = '.', width = '412'] = process.argv
const W = Number(width)
const APP = 'http://localhost:5174'
const email = `account-${Date.now()}@example.test`

const browser = await chromium.launch()
const ctx = await browser.newContext({
  viewport: { width: W, height: W < 700 ? 915 : 860 },
  deviceScaleFactor: W < 700 ? 2 : 1,
  geolocation: { latitude: 2.929, longitude: 101.7775 },
  permissions: ['geolocation'],
})
const page = await ctx.newPage()
const problems = []
const statuses = []
page.on('pageerror', (error) => problems.push(error.message))
page.on('response', (response) => {
  const url = new URL(response.url())
  if (url.pathname.startsWith('/api/v1/') && !url.pathname.includes('app-sessions')) statuses.push(`${response.request().method()} ${url.pathname} ${response.status()}`)
})
await page.route('**/api/v1/places/search?*', (route) =>
  route.fulfill({
    json: {
      results: [{ provenance: 'canonical', id: 1, googlePlaceId: null, name: 'Restoran Nasi Kandar Pelita', address: 'Jalan 2', category: 'Mamak', cuisine: null, latitude: 2.93, longitude: 101.78, priceLevel: 1, rating: 4.6, distanceKm: 0.3, openStatus: 'open', closesAt: null, halal: { status: 'unknown', display: { shortLabel: 'Not verified', longLabel: 'Not verified', tone: 'neutral', action: null } }, isCommunityFind: false, shareUrl: null, groupKey: null, groupSize: 1 }],
      meta: { radiusKm: 25, source: 'local', googleAvailable: false, widerRadiusKm: null, total: 1 },
      suggestions: [],
    },
  }),
)
await page.route('**/api/v1/restaurants/*/save', (route) => route.fulfill({ json: { saved: true } }))

const shot = async (name) => {
  await page.waitForTimeout(500)
  await page.screenshot({ path: `${out}/account-${W}-${name}.png`, fullPage: true })
}

// A link opened before onboarding is where onboarding ends: guest first (made at the last
// onboarding step), then create an account from Settings (upgrades the guest in place).
await page.goto(`${APP}/community/mine`, { waitUntil: 'networkidle' })
await page.waitForURL(`${APP}/welcome`)
await page.getByRole('button', { name: 'Get started' }).click()
await page.waitForTimeout(800)
if (await page.getByRole('button', { name: 'Turn on location' }).count()) await page.getByRole('button', { name: 'Turn on location' }).click()
await page.getByRole('button', { name: 'Continue' }).click()
await page.getByRole('button', { name: 'No, show everything' }).click()
await page.waitForURL(`${APP}/community/mine`)
await page.getByRole('heading', { name: /Sign in to see places/ }).waitFor()
await page.goto(`${APP}/settings`)
await shot('1-guest-settings')
await page.getByRole('link', { name: /Using MakanApa as a guest/ }).click()
await page.getByLabel('Name').fill('Aina')
await page.getByLabel('Email').fill(email)
await page.getByLabel('Password').fill('correct-horse-9')
await page.getByRole('button', { name: 'Create account' }).click()
await page.waitForURL(`${APP}/`)

await page.goto(`${APP}/settings`)
await page.getByRole('link', { name: 'Edit your profile' }).waitFor()
await shot('2-settings')

await page.getByRole('link', { name: 'Edit your profile' }).click()
await page.getByText('Laksa', { exact: true }).click()
await page.getByLabel('Name', { exact: true }).fill('Aina R')
await page.getByRole('button', { name: /^Community/ }).click()
await page.waitForTimeout(800)
await shot('3-community')
const firstUniversity = page.getByRole('dialog').getByRole('radio').nth(1)
if (await firstUniversity.count()) await firstUniversity.check()
await page.getByRole('dialog').getByRole('button', { name: 'Save' }).click()
await page.waitForTimeout(600)
await page.getByRole('button', { name: 'Save' }).click()
await page.waitForURL(`${APP}/settings`)
await shot('4-settings-updated')

await page.getByRole('link', { name: /Your Selera/ }).click()
await page.getByRole('heading', { name: 'What I’ve picked up' }).waitFor()
await shot('5-selera')

await page.goto(`${APP}/settings/saved`)
await page.getByRole('searchbox').fill('nasi kandar')
await page.waitForTimeout(900)
await page.getByRole('button', { name: 'Save Restoran Nasi Kandar Pelita' }).click()
await page.getByRole('searchbox').fill('')
await page.waitForTimeout(300)
await shot('6-saved')

await page.goto(`${APP}/settings/blocked`)
await page.getByText('You haven’t blocked anyone.').waitFor()

// Forgot password while signed in (always the same answer).
await page.goto(`${APP}/forgot-password`)
await page.getByLabel('Email').fill(email)
await page.getByRole('button', { name: 'Send reset link' }).click()
await page.getByRole('heading', { name: 'Check your email' }).waitFor()
await shot('7-forgot')

// Delete: a wrong password must not sign out, the right one deletes.
await page.goto(`${APP}/settings`)
await page.getByRole('button', { name: 'Delete account' }).click()
await page.getByRole('dialog').getByLabel('Password').fill('wrong-password')
await page.getByRole('button', { name: 'Delete my account' }).click()
await page.getByText('That password doesn’t match. Try again.').waitFor()
await shot('8-delete-wrong')
await page.getByRole('dialog').getByLabel('Password').fill('correct-horse-9')
await page.getByRole('button', { name: 'Delete my account' }).click()
await page.waitForURL(`${APP}/welcome`, { timeout: 10_000 })

console.log(W, 'api', JSON.stringify(statuses))
console.log(W, 'page errors', JSON.stringify(problems))
await browser.close()
