// Browser walkthrough of Add a place and My places (phase 6, part 2) against a real local API:
// add a new place by hand (with a photo), suggest an edit to a listed place, leave a flow half way
// (its draft is withdrawn), then check My places. Place search is mocked so Google is never
// called. Setup as in account-walkthrough.mjs:
//   node scripts/addplace-walkthrough.mjs ./shots 412 ../api
import { execFileSync } from 'node:child_process'
import { chromium } from 'playwright'

const [, , out = '.', width = '412', API_DIR = '../api'] = process.argv
const W = Number(width)
const APP = 'http://localhost:5174'
const email = `addplace-${Date.now()}@example.test`
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code], { cwd: API_DIR }).toString().trim()

// A real listed place to suggest an edit to.
const listed = JSON.parse(tinker(`echo json_encode(App\\Models\\Restaurant::where('is_active', true)->first(['id', 'name', 'address', 'food_category', 'price_level', 'latitude', 'longitude']));`))
// The smallest valid PNG, as the picked photo.
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64')

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
  if (url.pathname.includes('/community/')) statuses.push(`${response.request().method()} ${url.pathname.replace('/api/v1/', '')} ${response.status()}`)
})
await page.route('**/api/v1/community/places/search?*', (route) => {
  const query = new URL(route.request().url()).searchParams.get('query').toLowerCase()
  const existing = listed.name.toLowerCase().includes(query)
    ? [{ id: listed.id, name: listed.name, address: listed.address, foodCategory: listed.food_category, priceLevel: listed.price_level, latitude: Number(listed.latitude), longitude: Number(listed.longitude), distanceKm: 0.4 }]
    : []
  return route.fulfill({ json: { existing, google: [] } })
})

const shot = async (name) => {
  await page.waitForTimeout(400)
  await page.screenshot({ path: `${out}/addplace-${W}-${name}.png`, fullPage: true })
}

const cleanUp = () =>
  tinker(`$u = App\\Models\\User::where('email', '${email}')->first(); if ($u) { $ids = App\\Models\\RestaurantSubmission::where('user_id', $u->id)->pluck('id'); App\\Models\\RestaurantPhoto::whereIn('restaurant_submission_id', $ids)->get()->each(function ($p) { Illuminate\\Support\\Facades\\Storage::disk($p->disk)->delete($p->path); $p->delete(); }); App\\Models\\RestaurantSubmission::whereIn('id', $ids)->delete(); $u->tokens()->delete(); $u->forceDelete(); }`)
let kept = null
let photoDisks = null
let reloadStatus = null
let focusKept = null
let cards = []
try {
await page.goto(`${APP}/welcome`, { waitUntil: 'networkidle' })
await page.getByRole('button', { name: 'Get started' }).click()
await page.waitForTimeout(800)
if (await page.getByRole('button', { name: 'Turn on location' }).count()) await page.getByRole('button', { name: 'Turn on location' }).click()
await page.getByRole('button', { name: 'Continue' }).click()
await page.getByRole('button', { name: 'No, show everything' }).click()
await page.waitForURL(`${APP}/`)

// A guest is asked to sign in first.
await page.goto(`${APP}/community/add`)
await page.getByRole('heading', { name: 'Sign in to add or edit places' }).waitFor()
await page.goto(`${APP}/sign-in`)
await page.getByLabel('Name').fill('Add Place Tester')
await page.getByLabel('Email').fill(email)
await page.getByLabel('Password').fill('correct-horse-9')
await page.getByRole('button', { name: 'Create account' }).click()
await page.waitForURL(`${APP}/`)

// 1. A new place, added by hand.
await page.goto(`${APP}/community/add`)
await page.getByPlaceholder('Search for a place').fill('Warung Kak Ros')
await page.getByText('No matches for “Warung Kak Ros”.').waitFor()
await shot('1-find')
await page.getByRole('button', { name: /Add it yourself/ }).click()
await page.getByRole('heading', { name: 'Tell us about this place' }).waitFor()
await page.getByRole('button', { name: 'Warung', exact: true }).click()
await page.getByLabel('Spend per person (RM)').fill('8')
await page.getByText('Add more details').click()
await page.getByLabel('Website').fill('kakros')
await page.getByRole('button', { name: 'Continue' }).click()
await page.getByText('Enter the full address, starting with https://').waitFor()
await page.getByLabel('Website').click()
await page.keyboard.press('ControlOrMeta+A')
await page.getByLabel('Website').pressSequentially('https://kakros.example.com')
focusKept = await page.getByLabel('Website').evaluate((element) => element === document.activeElement && element.closest('details').open)
await page.getByText('Add menu').click()
await page.getByLabel('Dish', { exact: true }).fill('Nasi lemak ayam')
await page.getByLabel('Price (RM)').fill('7.5')
await page.getByRole('button', { name: 'Add dish' }).click()
await shot('2-details')
await page.getByRole('button', { name: 'Continue' }).click()
// The community rules come first.
await page.getByRole('checkbox').check()
await page.getByRole('button', { name: 'I agree' }).click()
await page.getByRole('heading', { name: 'Where is it?' }).waitFor()
await page.getByRole('button', { name: /Use my current location/ }).click()
await page.getByRole('heading', { name: 'Is this the right spot?' }).waitFor()
await shot('3-location')
await page.getByRole('button', { name: 'Looks right' }).click()
await page.getByLabel('Add photos').waitFor({ state: 'attached' })
await page.getByLabel('Add photos').setInputFiles({ name: 'front.png', mimeType: 'image/png', buffer: PNG })
await page.waitForFunction(() => !document.body.textContent.includes('Uploading'))
await shot('4-review')
await page.getByRole('button', { name: 'Submit for review' }).click()
await page.getByRole('heading', { name: 'Sent for review!' }).waitFor()
await shot('5-done')
photoDisks = tinker(`echo App\\Models\\RestaurantPhoto::whereIn('restaurant_submission_id', App\\Models\\RestaurantSubmission::where('user_id', App\\Models\\User::where('email', '${email}')->value('id'))->pluck('id'))->pluck('disk')->join(',');`)
// Back from Sent for review: a fresh start, with the URL to match.
await page.goBack()
await page.getByRole('heading', { name: 'Find the place' }).waitFor()
const backUrl = new URL(page.url())
if (backUrl.searchParams.has('step')) throw new Error(`stale step in ${backUrl}`)

// A search handed over in the URL (Nearby and Saved do this).
await page.goto(`${APP}/community/add?q=Warung`)
if ((await page.getByPlaceholder('Search for a place').inputValue()) !== 'Warung') throw new Error('q not prefilled')

// Settings links to My places.
await page.goto(`${APP}/settings`)
await page.getByRole('link', { name: /My places/ }).click()
await page.waitForURL(`${APP}/community/mine`)

// 2. An edit to a listed place: no Location step; Back returns to the search as it was.
await page.goto(`${APP}/community/add`)
await page.getByPlaceholder('Search for a place').fill(listed.name.slice(0, 6))
await page.getByRole('button', { name: new RegExp(`${listed.name.slice(0, 6)}.*Suggest an edit`) }).click()
await page.getByRole('heading', { name: 'What should change?' }).waitFor()
await page.goBack()
await page.getByRole('heading', { name: 'Find the place' }).waitFor()
kept = await page.getByPlaceholder('Search for a place').inputValue()
await page.getByRole('button', { name: new RegExp(`${listed.name.slice(0, 6)}.*Suggest an edit`) }).click()
await page.getByLabel('Category', { exact: true }).fill('Kopitiam')
await page.getByRole('button', { name: 'Continue' }).click()
await page.getByRole('heading', { name: 'Looks good?' }).waitFor()
await page.getByText('Category', { exact: true }).last().waitFor()
await shot('6-edit-review')
await page.getByRole('button', { name: 'Submit for review' }).click()
await page.getByRole('heading', { name: 'Edit sent for review!' }).waitFor()

// 3. Leaving half way withdraws the draft.
await page.goto(`${APP}/community/add`)
await page.getByPlaceholder('Search for a place').fill('Kedai Abandoned')
await page.getByRole('button', { name: /Add it yourself/ }).click()
await page.getByRole('button', { name: 'Continue' }).click()
await page.getByRole('button', { name: /Use my current location/ }).click()
await page.getByRole('button', { name: 'Looks right' }).click()
await page.getByLabel('Add photos').waitFor({ state: 'attached' })
await page.getByRole('button', { name: 'Cancel' }).click()
const withdrawn = page.waitForResponse((response) => response.request().method() === 'DELETE' && response.url().includes('/community/submissions/'))
await page.getByRole('dialog').getByRole('button', { name: 'Discard' }).click()
await page.waitForURL(`${APP}/community`)
await withdrawn

// Reloading at Review: reports whether the keepalive request on pagehide withdrew the draft.
await page.goto(`${APP}/community/add`)
await page.getByPlaceholder('Search for a place').fill('Kedai Reloaded')
await page.getByRole('button', { name: /Add it yourself/ }).click()
await page.getByRole('button', { name: 'Continue' }).click()
await page.getByRole('button', { name: /Use my current location/ }).click()
await page.getByRole('button', { name: 'Looks right' }).click()
await page.getByLabel('Add photos').waitFor({ state: 'attached' })
await page.reload()
await page.waitForTimeout(2000)
reloadStatus = tinker(`echo App\\Models\\RestaurantSubmission::where('name', 'Kedai Reloaded')->where('user_id', App\\Models\\User::where('email', '${email}')->value('id'))->value('status');`)

// My places.
await page.goto(`${APP}/community/mine`)
await page.getByText('Warung Kak Ros').waitFor()
await shot('7-mine')
cards = await page.locator('li').allInnerTexts()

} finally {
  // Clean up: the test account, its submissions and their photos.
  cleanUp()
  await page.screenshot({ path: `${out}/addplace-${W}-last.png`, fullPage: true }).catch(() => {})
}

console.log(W, 'kept query', JSON.stringify(kept), 'focus kept while fixing', focusKept)
console.log(W, 'photo disks', photoDisks, 'after reload at Review', reloadStatus)
console.log(W, 'cards', JSON.stringify(cards.map((text) => text.replace(/\s+/g, ' '))))
console.log(W, 'api', JSON.stringify(statuses.filter((line) => !line.includes('places/search'))))
console.log(W, 'page errors', JSON.stringify(problems))
await browser.close()
