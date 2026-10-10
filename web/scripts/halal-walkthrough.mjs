// Browser walkthrough of halal on a place (phase 6, part 3) against a real local API: vouch with
// a photo, see it waiting for review, claim ownership, and get told about the duplicate claim.
// Uses a fixture place (its details never call Google) and blocks Nearby's Google-backed list.
// Cleanup also rebuilds the place's halal snapshot, which a vouch marks as under review.
// Run the API with local photo disks (see local-api-photos-go-to-spaces in memory):
//   node scripts/halal-walkthrough.mjs ./shots 412 ../api
import { execFileSync } from 'node:child_process'
import { chromium } from 'playwright'

const [, , out = '.', width = '412', API_DIR = '../api'] = process.argv
const W = Number(width)
const APP = 'http://localhost:5174'
const email = `halal-${Date.now()}@example.test`
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code], { cwd: API_DIR }).toString().trim()
const place = JSON.parse(tinker(`echo json_encode(App\\Models\\Restaurant::where('is_active', true)->where('provider', '<>', 'google')->first(['id', 'name']));`))
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64')
const photo = (name) => ({ name, mimeType: 'image/png', buffer: PNG })

const browser = await chromium.launch()
const ctx = await browser.newContext({ viewport: { width: W, height: W < 700 ? 915 : 860 }, deviceScaleFactor: W < 700 ? 2 : 1 })
const page = await ctx.newPage()
const problems = []
const statuses = []
page.on('pageerror', (error) => problems.push(error.message))
page.on('response', (response) => {
  const url = new URL(response.url())
  if (/halal|owner-claim|submissions|details/.test(url.pathname)) statuses.push(`${response.request().method()} ${url.pathname.replace('/api/v1/', '')} ${response.status()}`)
})
await page.route('**/api/v1/places/nearby*', (route) => route.abort())

const shot = async (name) => {
  await page.waitForTimeout(400)
  await page.screenshot({ path: `${out}/halal-${W}-${name}.png`, fullPage: true })
}
const cleanUp = () =>
  tinker(`$u = App\\Models\\User::where('email', '${email}')->first(); if ($u) { $ids = App\\Models\\RestaurantSubmission::where('user_id', $u->id)->pluck('id'); App\\Models\\RestaurantPhoto::whereIn('restaurant_submission_id', $ids)->get()->each(function ($p) { Illuminate\\Support\\Facades\\Storage::disk($p->disk)->delete($p->path); $p->delete(); }); App\\Models\\RestaurantSubmission::whereIn('id', $ids)->delete(); $u->tokens()->delete(); $u->forceDelete(); } app(App\\Services\\Halal\\HalalSnapshotService::class)->rebuild(App\\Models\\Restaurant::find(${place.id}));`)

let disks = null
let duplicate = null
let certBlocked = null
try {
  await page.goto(`${APP}/welcome`, { waitUntil: 'networkidle' })
  await page.getByRole('button', { name: 'Get started' }).click()
  await page.getByRole('button', { name: 'Not now' }).click()
  await page.getByRole('button', { name: 'No, show everything' }).click()
  await page.waitForURL(`${APP}/`)
  await page.goto(`${APP}/sign-in`)
  await page.getByLabel('Name').fill('Halal Tester')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('correct-horse-9')
  await page.getByRole('button', { name: 'Create account' }).click()
  await page.waitForURL(`${APP}/`)

  await page.goto(`${APP}/nearby?place=${place.id}`)
  const section = page.getByRole('region', { name: 'Halal' })
  await section.waitFor()
  await section.scrollIntoViewIfNeeded()
  await shot('1-section')

  // Vouch: the rules first, then the sheet. A certified claim needs the certificate photo.
  await section.getByRole('button', { name: /Know if this place is halal\?|Is this still accurate\?/ }).click()
  await page.getByRole('checkbox').check()
  await page.getByRole('button', { name: 'I agree' }).click()
  const vouch = page.getByRole('dialog', { name: 'Vouch for this place' })
  await vouch.waitFor()
  certBlocked = await vouch.getByRole('button', { name: 'Send vouch for review' }).isDisabled()
  await vouch.getByLabel('No certificate (community notes)').check()
  await vouch.getByLabel('How do you know?').fill('Owner is Muslim, no alcohol or pork on the menu.')
  await vouch.getByLabel('Storefront or menu photos').setInputFiles(photo('front.png'))
  await shot('2-vouch')
  await vouch.getByRole('button', { name: 'Send vouch for review' }).click()
  await page.getByRole('heading', { name: 'Thanks for vouching!' }).waitFor()
  await shot('3-thanks')
  await page.getByRole('dialog').getByRole('button', { name: 'Close' }).click()
  await section.getByText(/Waiting for review/).waitFor()
  await shot('4-waiting')

  // Claim the place, then try again: the server says a claim is already open.
  await section.getByRole('button', { name: 'Own this place?' }).click()
  const claim = page.getByRole('dialog', { name: `Claim ${place.name}` })
  await claim.getByLabel('Contact phone').fill('012-345 6789')
  await claim.getByLabel('Proof photo (required)').setInputFiles(photo('licence.png'))
  await claim.getByRole('button', { name: 'Send claim' }).click()
  await page.getByRole('heading', { name: 'Claim sent' }).waitFor()
  await page.getByRole('dialog').getByRole('button', { name: 'Close' }).click()
  await section.getByRole('button', { name: 'Own this place?' }).click()
  await claim.getByLabel('Contact phone').fill('012-345 6789')
  await claim.getByLabel('Proof photo (required)').setInputFiles(photo('licence.png'))
  await claim.getByRole('button', { name: 'Send claim' }).click()
  duplicate = await claim.getByRole('alert').innerText()
  await shot('5-claim-again')

  // Approved evidence and History, which the local data has none of: injected into the real
  // details response, with one page of history.
  await page.route(`**/api/v1/restaurants/${place.id}/details`, async (route) => {
    const response = await route.fetch()
    const details = await response.json()
    details.halal.historyCount = 2
    details.halal.reports = [
      { id: 1, claim: 'certified', resolvedStatus: 'muslim_friendly', isCurrent: true, comment: 'Cert on the wall, but it expired last year.', userName: 'Aina', userTrusted: true, approvedAt: '2026-09-02T10:00:00+08:00', photos: [] },
    ]
    await route.fulfill({ response, json: details })
  })
  await page.route(`**/api/v1/restaurants/${place.id}/halal/history*`, (route) =>
    route.fulfill({
      json: {
        entries: [
          { id: 2, status: 'muslim_friendly', state: 'active', method: 'manual_review', evidenceSource: 'community', authority: null, summary: 'Expired certificate, owner is Muslim.', effectiveFrom: '2026-09-02T10:00:00+08:00', effectiveUntil: null },
          { id: 1, status: 'certified', state: 'superseded', method: 'registry_verified', evidenceSource: 'registry', authority: 'jakim', summary: null, effectiveFrom: '2025-06-01T10:00:00+08:00', effectiveUntil: '2026-09-02T10:00:00+08:00' },
        ],
        nextPage: null,
      },
    }),
  )
  await page.goto(`${APP}/nearby?place=${place.id}`)
  await section.getByText('Current evidence').waitFor()
  await section.scrollIntoViewIfNeeded()
  await shot('6-evidence')
  await section.getByRole('button', { name: 'History (2)' }).click()
  await page.getByRole('dialog', { name: 'Halal history' }).getByText('Checked against the official registry, JAKIM').waitFor()
  await shot('7-history')

  disks = tinker(`echo App\\Models\\RestaurantPhoto::whereIn('restaurant_submission_id', App\\Models\\RestaurantSubmission::where('user_id', App\\Models\\User::where('email', '${email}')->value('id'))->pluck('id'))->pluck('disk')->join(',');`)
} finally {
  cleanUp()
  await page.screenshot({ path: `${out}/halal-${W}-last.png`, fullPage: true }).catch(() => {})
}

console.log(W, 'place', place.name, 'send disabled without cert photo', certBlocked)
console.log(W, 'duplicate claim', JSON.stringify(duplicate), 'photo disks', disks)
console.log(W, 'api', JSON.stringify(statuses))
console.log(W, 'page errors', JSON.stringify(problems))
await browser.close()
