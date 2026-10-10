// Browser walkthrough of Community (phase 6, part 1) against a real local API: join a
// university, agree to the rules, post with a tagged place, react, reply in the thread, delete,
// open the ambassador application, then delete the test account (which removes its posts).
// Place search is mocked: it can reach Google. Setup as in account-walkthrough.mjs:
//   node scripts/community-walkthrough.mjs ./shots 412 ../api
import { execFileSync } from 'node:child_process'
import { chromium } from 'playwright'

const [, , out = '.', width = '412', API_DIR = '../api'] = process.argv
const W = Number(width)
const APP = 'http://localhost:5174'
const email = `community-${Date.now()}@example.test`

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
  if (url.pathname.startsWith('/api/v1/') && !url.pathname.includes('app-sessions')) statuses.push(`${response.request().method()} ${url.pathname.replace('/api/v1/', '')} ${response.status()}`)
})
let taggable = null
await page.route('**/api/v1/community/places/search?*', async (route) => {
  // Offer a real place from the database so the tag survives the POST.
  const nearby = await page.evaluate(async () => {
    const token = JSON.parse(localStorage.getItem('makanapa:v1')).token
    const res = await fetch('http://localhost:8001/api/v1/community/feed?latitude=2.929&longitude=101.7775', { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } })
    return res.ok ? (await res.json()) : null
  })
  const first = nearby?.trending?.[0] ?? nearby?.newInArea?.[0] ?? null
  taggable = first
  await route.fulfill({ json: { existing: first ? [{ id: first.id, name: first.name, address: null, foodCategory: first.foodCategory, priceLevel: null, latitude: 2.93, longitude: 101.78, distanceKm: 0.3 }] : [], google: [] } })
})

const shot = async (name) => {
  await page.waitForTimeout(500)
  await page.screenshot({ path: `${out}/community-${W}-${name}.png`, fullPage: true })
}

await page.goto(`${APP}/welcome`, { waitUntil: 'networkidle' })
await page.getByRole('button', { name: 'Get started' }).click()
await page.waitForTimeout(800)
if (await page.getByRole('button', { name: 'Turn on location' }).count()) await page.getByRole('button', { name: 'Turn on location' }).click()
await page.getByRole('button', { name: 'Continue' }).click()
await page.getByRole('button', { name: 'No, show everything' }).click()
await page.waitForURL(`${APP}/`)
await page.goto(`${APP}/sign-in`)
await page.getByLabel('Name').fill('Community Tester')
await page.getByLabel('Email').fill(email)
await page.getByLabel('Password').fill('correct-horse-9')
await page.getByRole('button', { name: 'Create account' }).click()
await page.waitForURL(`${APP}/`)
// New accounts can't post on their first day (server rule): age this test account by two days.
execFileSync('php', ['artisan', 'tinker', '--execute', `App\\Models\\User::where('email', '${email}')->update(['created_at' => now()->subDays(2)]);`], { cwd: API_DIR })

// Public first, then join the first university on the list.
await page.goto(`${APP}/community`, { waitUntil: 'networkidle' })
await shot('1-public')
await page.getByRole('button', { name: /Change community/ }).click()
await page.getByRole('dialog').getByRole('radio').nth(1).check()
await page.getByRole('dialog').getByRole('button', { name: 'Save' }).click()
await page.waitForTimeout(1200)
await shot('2-university')

// Post: the rules come first, then the composer.
await page.getByRole('button', { name: 'Share a thought' }).first().click()
await page.getByRole('checkbox').check()
await page.getByRole('button', { name: 'I agree' }).click()
await page.getByPlaceholder('Share a thought… best teh tarik? new spot?').fill('Best teh tarik near campus is at the corner mamak. Try it before 9am.')
await page.getByRole('button', { name: 'Tag a place' }).click()
await page.getByPlaceholder('Search places on MakanApa').fill('nasi')
await page.waitForTimeout(900)
if (taggable) await page.getByRole('dialog').getByRole('button', { name: new RegExp(taggable.name) }).click()
else await page.getByRole('dialog').getByRole('button', { name: 'Cancel' }).click()
await page.getByRole('button', { name: 'Post', exact: true }).click()
await page.getByText('Best teh tarik near campus').first().waitFor()
await shot('3-posted')

// React, then open the thread and reply.
await page.getByRole('button', { name: /^Fire, / }).first().click()
await page.waitForTimeout(600)
await page.getByText('Best teh tarik near campus').first().click()
await page.waitForURL(/\/community\/posts\/\d+/)
await page.getByPlaceholder('Write a reply…').fill('Agreed, the roti is good too.')
await page.getByRole('button', { name: 'Send reply' }).click()
await page.getByText('Agreed, the roti is good too.').waitFor()
await shot('4-thread')

// Delete the post from the thread, which closes it.
await page.getByRole('button', { name: 'More options' }).first().click()
await page.getByRole('button', { name: 'Delete', exact: true }).click()
await page.getByRole('dialog').getByRole('button', { name: 'Delete', exact: true }).click()
await page.waitForURL(`${APP}/community`)

// The ambassador application form.
await page.getByRole('button', { name: /Become an ambassador for/ }).click()
await page.getByRole('heading', { name: /Represent .* on MakanApa/ }).waitFor()
await shot('5-apply')
await page.keyboard.press('Escape')

// Clean up: remove the test account and everything it posted.
execFileSync('php', ['artisan', 'tinker', '--execute', `$u = App\\Models\\User::where('email', '${email}')->first(); $u?->tokens()->delete(); $u?->forceDelete();`], { cwd: API_DIR })

console.log(W, 'tagged', taggable?.name ?? '(no place available)')
console.log(W, 'api', JSON.stringify(statuses.filter((line) => !line.startsWith('GET auth/me'))))
console.log(W, 'page errors', JSON.stringify(problems))
await browser.close()
