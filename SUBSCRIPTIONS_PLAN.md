# MakanApa Plus — Subscription, Assistant & Referral Plan

Status: **plan only — nothing started.** Everything ships dark behind `BILLING_ENABLED` / `ASSISTANT_ENABLED` / `REFERRALS_ENABLED` (default OFF in production, same pattern as `config/brain.php`) until retention numbers justify charging.

Last audit: 2026-10-12 — re-checked against main after the web app (app.trymakanapa.com), Kakao Local for Korea and region packs shipped; external facts re-checked against RevenueCat, Stripe, Apple and Google Play docs. Previous audit 2026-10-10. See [Audit changelog](#audit-changelog) at the end.

**Biggest changes in this audit**
- Plus now has to work on **three surfaces** (iOS, web app, later Play) — see [Web and Android billing](#web-and-android-billing).
- **Lunch call is thinner than planned**: free mealtime nudges already name an open place. Plus sells the *reason*, not the pick.
- **Korea is a real region now, but Plus is weak there**: Kakao picks are live-only and never become `restaurants` rows, so Ask Bubu, saves and collections barely work in Korea.
- **Pro rethought**: owner claim flow and view/pick/save counters already exist; Apple rules push Pro to a web-only owner portal; Max proposed for removal.
- **Design approach** added: [Design approach](#design-approach).

## Principles

- The main food picker stays free. Never paywall "decide what to eat".
- **Never paywall halal information or halal filters.** It's a dietary/religious need and the brand's trust anchor.
- **Never take away something that's free today** (e.g. Late night mode, Open now chip).
- Sell the outcome: **"Find food that fits your cravings, budget, and taste."**
- Community participation and ambassador status stay **earned**. Plus gets its own cosmetic badge; the ambassador crest is never sold.
- English-only copy rule — no "makan" in tier or feature copy (brand name MakanApa, dish names and the mascot name Bubu are fine).

## Reality check: expected revenue

Median freemium **download-to-paid by day 35 is 2.1%** globally (RevenueCat State of Subscription Apps 2026, flat since 2025; hard paywalls 10.7%). No Southeast Asia breakout was found, and MY purchasing power is lower, so plan on **~1.5–2%** of active users — directional only. At the standard RM 7.90/month, net per subscriber ≈ RM 7.90 ÷ 1.08 (SST, which Apple nets out before proceeds) × 0.85 (Small Business Program) ≈ **RM 6.22**:

| Monthly active users | Subscribers (~1.5–2%) | Gross/month | Net (after SST + 15%) |
|---|---|---|---|
| 1,000 | ~15–20 | ~RM 120–160 | ~RM 95–125 |
| 10,000 | ~150–200 | ~RM 1,190–1,580 | ~RM 930–1,245 (~$230–305) |

Founding-member subscribers (RM 5.90) pull this lower. Web purchases net **about the same** as iOS: Stripe ~3% + RM 1 + 0.7% Billing (~RM 1.29 on RM 7.90), plus RevenueCat's fee and any tax we must remit ourselves (Apple does it on iOS), and +3% on KR/US cards. Pick the web rail for reach, not margin. Source: [RevenueCat 2026 benchmarks](https://www.revenuecat.com/blog/growth/subscription-app-trends-benchmarks-2026).

Plus is a **sustainability lever** (cover AI + Places costs of heavy users), not a business on its own at current scale. Size engineering effort accordingly. The restaurant side (Pro) has higher revenue per account.

## Tiers

| Tier | For | When | Entitlement |
|---|---|---|---|
| **Free** | Everyone | Launch | Core picker, community, earned badges, all halal features |
| **Plus** | Consumers | Launch | `plus` |
| **Owner tools** | Verified restaurant owners | Later | `owner` link (existing `RestaurantOwner`), **free** — menu, photos, basic stats |
| **Pro** | Verified owners who want reach | After owner tools | `pro` (+ `plus`) — sold only on the owner web portal, never in the consumer apps |

Launch is **Free + Plus only**. **Proposed: drop Max** (decision 13): owners who want Plus are rare, and a Pro product can simply grant `plus` too ("Pro includes Plus") with zero extra products or gating code. See [Pro](#pro-later).

## Core design: entitlements, not tiers

- Code checks **entitlements** (`plus`, `pro`), never tier or product names. A Pro product that also grants `plus` needs zero extra gating code.
- **One source of truth for server, iOS and web: our `user_entitlements` table**, exposed on `GET auth/me` as `entitlements: [{ name, expiresAt, source }]`.
  - Paid access arrives from RevenueCat (App Store and Web Billing) and later Google Play; free grants (admin comps, referral rewards, ambassador perk) are written locally.
  - iOS UI reads `auth/me`, not the RevenueCat SDK's `customerInfo` — otherwise referral/admin grants would be invisible to the app. The SDK is used only to purchase and restore.
  - **The web app gets this for free**: it uses the same Sanctum bearer token and calls `auth/me` on start (`web/src/auth/session.tsx`, `web/src/api/client.ts`); add the field to `AuthController::presentUser()` and both clients see it.
- Server enforces every gate; clients only show/hide UI.
- `users.id` is stable across guest upgrade (`AuthController::upgradeGuest` updates the same row) — safe to use as the RevenueCat `app_user_id`.
  - **New since 2026-10-10:** signing in to an *existing* account from a guest session absorbs the guest row (`AuthController::absorbGuest`), so the user id **changes**. iOS must call RevenueCat `logIn(newId)` after any sign-in, not only on account switch. Guests can't buy, so no purchase is lost, but any `user_entitlements` row on the absorbed guest (none expected: grants require a registered account) must move with it.

## Plus — what it sells

Ask Bubu is the anchor; the launch set adds two cheap features that reuse existing systems.

On the paywall, in order (reordered 2026-10-12: strongest proof first, thinnest last):
1. **Ask Bubu** — personal AI assistant that knows your cravings, budget and taste.
2. **Your taste, explained** — deeper Selera insights (builds on `SeleraTraits::describe`) + a monthly taste recap.
3. **Named collections** — organise saves into lists ("Date spots", "Near campus"). Today `RestaurantSave` is a flat list keyed on `installation_id`/`user_id`; collections are a new `collections` table + pivot. Existing saves stay free and unlimited.
4. **Lunch call, explained** — the mealtime nudge gains a one-line taste reason ("Rainy, and you liked Thai last week. 400 m away.") and a "Why this one?" section on the landing sheet.
   - **Audit correction (2026-10-12):** free nudges **already** name a specific open place: `NudgePickFinder` picks from `localRestaurantsNear()` + `topCandidates` (Brain state when on) and `NudgeCopyCatalog` writes distance, closing time and rain. So Plus can't sell "a pick already waiting"; it sells **the reason**. Paywall copy must say that. Free nudges stay exactly as they are.
   - Reason line comes from `ReasonComposer` (Brain ON required) and must keep `NudgeCopyCatalog`'s rule: no halal wording in nudges.
   - **Reach:** nudges now also go to the web app (Web Push counts as a device in `MealNudgeEligibility`), so the audience is iOS + web.
   - **Blocked on push opt-in.** Mealtime nudges are opt-in (`NotificationCategory::DEFAULT_FALSE`), the priming toggles default off on iOS and web (`NotificationPrimingView`, `web/src/features/notifications/Notifications.tsx`), priming shows once, the Settings toggle never checks OS permission (denied users are silently skipped as `no_device`), and nudges stop for good after 3 pauses (`MealNudgeDispatcher`, `config/nudges.php`). Fix the chain first (Phase P):
     - **Do not default the priming toggle on.** Changed 2026-10-12: the priming screen is written around App Review 5.1.1(iv) (neutral wording, always leads to the system prompt); a pre-checked opt-in is a review risk. Instead make the meal toggle's *benefit* clear in its copy.
     - Check `authorizationStatus` when the Settings toggle flips (prompt, or deep-link to iOS Settings if denied); same for web `Notification.permission`.
     - Offer the meal toggle again after a few accepted picks (from Settings or a card, never a surprise system prompt).
     - Replace the permanent stop after 3 pauses with a monthly win-back.
5. **Plus crest** — cosmetic, separate from earned badges and the ambassador crest (see [Plus crest](#plus-crest)).

**Value risk (High).** Ask Bubu carries Plus; taste insights and collections are nice-to-haves and the lunch call is a small add. Before charging, launch free Ask Bubu (3/week) and measure: weekly askers ÷ registered WAU, and how many hit the allowance. If fewer than ~5% of registered WAU ever hit it, Plus is too thin to charge for; widen the launch set first (candidates below) rather than lowering the price.

Later candidates (not in launch scope):
- **Home screen widget** "Bubu's pick for lunch" — no WidgetKit target exists yet (Medium). Free gets a generic widget, Plus the personal pick.
- Ask Bubu planning modes ("plan lunches for my week near campus").
- Precise price filter ("under RM15") — `RestaurantMenuItem` has a `price` column but menu data is near-empty; revisit once menu coverage exists. (`Region::priceBand()` already maps `price_level` to "under RM15 / ₩12,000 / $15" labels, but that's a coarse band, not a filter worth paying for.)

Removed after audit:
- ~~Smarter filters~~ — "open late" is already free (Late night mode), "under RM15" isn't expressible with `price_level`, and a halal-certified filter must be free. **Build the certified-only halal filter as a free improvement.**
- ~~Saved group presets for Rooms~~ — Rooms exist only as tables/models; no routes, controllers or iOS UI.
- Bigger rooms / streak freeze — underlying features don't exist.

## Plus crest

Final art: `marketing/plus-badge/makanapa-plus-purple-v2.png` (1254×1254, transparent). Bubu in a **purple cap** waving a spoon, gold laurels, "MakanApa" banner on top, "PLUS" ribbon below. Purple is Plus's colour; it stays visually distinct from the ambassador crest ("UNI") and from Bubu's everyday red "LET'S EAT" cap.

**Where it appears**
- Paywall header and the purchase-success celebration (full crest).
- Plus card in Settings (full crest, medium).
- Next to the user's name on profile and community posts (small variant).

**Asset work before shipping (blocks all Plus UI)**
- The PNG is 1.7 MB and is in neither the iOS asset catalog nor `web/public`: export asset-catalog sizes (@1x/@2x/@3x for each display size) instead of shipping the master.
- "MakanApa" and "PLUS" text becomes unreadable below ~48 pt (same reason the ambassador crest has a 64 pt floor, see `CommunityBadge.swift`, `AmbassadorPicksSection.swift`). Make a **separately drawn small mark** for inline use (purple shield + Bubu head, no lettering, 20–24 pt), not the crest scaled down.
- A vector (SVG/PDF) master is still missing: the only SVG is the old v1 gold/red design inside `makanapa-plus-badge.zip`. Commission or trace a v2 vector.
- Sizes: **full ~260 pt** (paywall header, purchase success; matches the ambassador crest in `AmbassadorWelcomeView`), **medium 64 pt** (Settings Plus card), **small 20–24 pt** (next to names).
- Accessibility label: "MakanApa Plus member".

**Rules**
- Shown only while the `plus` entitlement is active (including referral/admin grants). It disappears on expiry; no "former member" badge.
- Ambassadors who also have Plus show the ambassador crest first; the Plus mark is secondary.

## Billing: RevenueCat

**Why RevenueCat:** solo dev, iOS + web app, Laravel backend. Renewal/grace/refund/restore/transfer edge cases are the hard part and RevenueCat solves them; there's no official Apple server library for PHP. Product → multiple entitlements (Pro granting `pro` + `plus`) is native. Free up to **$2,500 monthly tracked revenue**, then 1%. Low lock-in — subscriptions belong to Apple.

**Payment rail:** external-purchase-link entitlement is not confirmed for the Malaysian storefront (plan on IAP), and 3.1.3(c) excludes consumer/single-user sales, so **all in-app subscriptions use IAP** (including Pro later, unless Pro is sold only outside the app with no in-app call to action).

**Commission:** enrol in the **App Store Small Business Program** → 15% from day one (proceeds ≤ $1M/yr). Apple nets out Malaysian SST before proceeds.

**RevenueCat setup (launch)**
- Entitlement: `plus` (add `pro` when Pro ships).
- Products: `plus_monthly`, `plus_yearly` (one App Store subscription group).
- Restore behavior: **Transfer to new App User ID** (RevenueCat default/recommended).
- iOS: configure RevenueCat with `appUserID: String(user.id)` **only after the user ID is known** (after `auth/guest` or sign-in returns), never at app launch — configuring early mints a `$RCAnonymousID`, and a custom ID that already has an anonymous alias won't merge cleanly. Guests get configured too (ID is stable on upgrade); call `logIn(String(user.id))` after **every** sign-in, since `absorbGuest` can change the id. Purchase itself still requires a registered account (`registered` middleware on sync; paywall sends guests to sign-up first).

**API**
- `user_entitlements` table: `user_id`, `entitlement`, `expires_at`, `source` (`revenuecat` | `admin` | `referral`, later `stripe` / `play`), `reference` (e.g. referral id). Effective access = any row with `expires_at` in the future. Keeps working if RevenueCat is down.
- `POST /webhooks/revenuecat`:
  - Verify the `Authorization` header (shared secret set in RevenueCat dashboard).
  - Return 200 fast and process on a queued job — RevenueCat times out at 60s and retries 5× (5/10/20/40/80 min) then stops.
  - Idempotent on event `id` (delivery is at-least-once).
  - On every event, refetch the customer from the RevenueCat REST API and sync `source=revenuecat` rows — don't write per-event logic. Use **API v2** (`GET /projects/{project_id}/customers/{customer_id}`, `gives_access`) for new work; needs a v2 secret key.
  - `TRANSFER` is sent only to the destination user — resync both `transferred_from` and `transferred_to`.
  - `BILLING_ISSUE` ≠ expiry (grace period may apply); `EXPIRATION` = remove access.
- `POST /me/billing/sync` (`registered`): client calls after purchase/restore; server refetches immediately so access unlocks without waiting for the webhook.
- Admin grant/revoke (Filament action, `source=admin`): test every gate with zero payments; support comps.
- Account deletion: inside `UserAccountDeletionService::delete`, before `forceDelete`, call RevenueCat delete-customer (async on their side; treat 200/404 as success). Show a "cancel your subscription first" notice for **Apple** subscribers (iPhone Settings). Web (RevenueCat Web Billing) subscriptions are cancelled by us server-side **before** the RevenueCat delete-customer call, so web users need no notice. Deleting the account doesn't stop Apple billing, and a later restore can re-create the customer. 
- Config: `REVENUECAT_SECRET_KEY`, `REVENUECAT_PROJECT_ID`, `REVENUECAT_WEBHOOK_AUTH` in `.env` → `config/billing.php`.

**iOS**
- Add `purchases-ios` (≥ 5.27.1 for Paywalls) via Xcode → Add Package, same as GoogleSignIn/GoogleMaps were added. **Never regenerate with XcodeGen** — `ios/project.yml` is stale and would wipe packages and entitlements.
- Paywall: start with RevenueCat Paywalls (iOS 15+, remotely configurable); swap to a custom SwiftUI sheet if it fails the ios-design-bar.
  - **Never use the "present if needed" variant** — it gates on the SDK's `customerInfo` and would show the paywall to users with a referral/admin grant. Decide whether to present from `auth/me`, then present unconditionally.
  - Post-purchase sequence: purchase succeeds → `POST /me/billing/sync` → refresh `auth/me` → dismiss. The RevenueCat success callback alone unlocks nothing.
- Settings: a Plus card after `yourMakanApaCard` in `SettingsView`, showing status/expiry from `auth/me`, Restore, and Manage subscription.

## Web and Android billing

The web app is live at app.trymakanapa.com (React + Vite PWA in `web/`, same API and `auth/me`). Android users use it today; a Play listing via TWA is ANDROID_PWA_PLAN phase 8. No payment code exists anywhere yet.

**Rails by surface**

| Surface | Rail | Why |
|---|---|---|
| iOS app | Apple IAP via RevenueCat | Required (3.1.1). Malaysia has no link-out allowance; Korea's alternative-payment entitlement needs a separate Korea-only binary at 26% ([Apple](https://developer.apple.com/support/storekit-external-entitlement-kr)) — not worth it |
| Web app (browser / installed PWA) | **RevenueCat Web Billing** (Stripe) | Same project, same `app_user_id` = `users.id`, same `plus` entitlement, same webhook → our `user_entitlements`. No second integration |
| Play TWA (later) | Google Play Billing (Digital Goods API) | Play requires Play Billing for digital goods in a Play-distributed TWA. Malaysia and Korea: 15% on subscriptions; Malaysia isn't in a user-choice billing program ([Play fees](https://support.google.com/googleplay/android-developer/answer/112622), [user choice](https://support.google.com/googleplay/android-developer/answer/13821247)) |

**RevenueCat Web Billing facts (checked 2026-10-12)**
- Currencies: MYR (min RM 3.99) and KRW (min ₩999) supported ([multi-currency](https://www.revenuecat.com/docs/web/web-billing/multi-currency-support)).
- Payment methods: **cards (3DS), Apple Pay, Google Pay only** ([payment methods](https://www.revenuecat.com/docs/web/web-billing/payment-methods.md)). **No FPX, GrabPay or Touch 'n Go.** Many MY students have no credit card; debit cards work, FPX doesn't.
- Stripe MY fees: cards 3% + RM 1 (+1% international, +2% FX); Stripe Billing +0.7% ([Stripe MY pricing](https://stripe.com/my/pricing)). RevenueCat's own fee on Web Billing is unclear (docs vs launch post disagree) — check the pricing page before launch.
- **Verify in sandbox before building:** a web purchase under `app_user_id = users.id` shows as the same `plus` entitlement on iOS (RevenueCat's design intent; not stated outright in the docs found).

**Card gap (decide after launch, not before).** If web checkout drop-off shows students without cards, add **FPX one-time passes** via Stripe Checkout directly: non-renewing "Plus 30 days" / "Plus 365 days", written to `user_entitlements` with `source=stripe`. Our entitlement table already treats every rail as a source, so this is one Checkout session + one webhook handler. Don't build it speculatively.

**Cross-surface rules**
- A web-bought Plus unlocks iOS (3.1.3(b) multiplatform: allowed because the same Plus is also sold as IAP in the app).
- **The iOS app never mentions web prices or links to web checkout** outside the US storefront (3.1.1(a) US exception only). No US link-out at launch either: keep one rule for every storefront.
- **Play TWA (when ANDROID_PWA_PLAN phase 8 happens, not before):** hide the web checkout inside the TWA until Play Billing is integrated. No RevenueCat Digital Goods API support was found, so Play purchases would likely sync to us manually (`source=play`).
- Manage subscription: iOS → Apple / Customer Center; web → RevenueCat Web Billing's customer portal link. The Settings card shows the source ("Billed by Apple" / "Billed on the web") so users know where to cancel.
- Prices on web come from the RevenueCat offering, never hard-coded; `Region` (location-based, `web/src/lib/region.ts`) picks copy only, not price.

## Pricing by storefront (MYR, KRW, USD)

Yes — App Store Connect supports a different price per storefront. Apple prices by the **Apple ID's country**, not the user's location (a Korean visitor in KL with a Korean Apple ID pays in KRW).

**How to set it up**
- Set **Malaysia as the base storefront** with the MYR price. Apple auto-generates equalized prices for all ~175 storefronts and keeps them updated for FX and tax changes.
- **Manually override** the storefronts you care about (Korea, United States). Manually set prices **stop auto-adjusting** — review them yearly.
- Prices must be chosen from Apple's **price point list** per currency. Pick the nearest available point in App Store Connect; the exact values below are targets, not confirmed points.
- Displayed prices always come from StoreKit (`product.displayPrice` / RevenueCat package price). **Never hardcode "RM"** in copy — the paywall must work in every currency.
- RevenueCat converts and reports everything in USD (MTR is USD); no server change needed for multi-currency.

**Target prices**

| Storefront | Monthly | Yearly (default on paywall) | Founding-member monthly | Net per monthly sub* |
|---|---|---|---|---|
| Malaysia (base) | **RM 7.90** | RM 69 | RM 5.90 | ~RM 6.22 (~$1.52) |
| South Korea | **₩7,900** | ₩69,000 | ₩5,900 | ~₩6,100 (~$4.55) |
| United States | **$5.99** | $49.99 | $3.99 | ~$5.09 (US prices exclude sales tax) |
| Everyone else | auto-equalized from MYR | auto | auto | — |

\*After VAT/SST (MY 8%, KR 10%) and Apple 15%. FX as of 2026-10-09 mid-market: **1 USD ≈ RM 4.09 ≈ ₩1,341** (XE). Recheck before setting prices.

Notes:
- Korean pricing convention ends in 900 (₩5,900 / ₩7,900 / ₩9,900) — reads more natural than ₩7,000 or ₩8,000.
- A KRW price ~3× the MYR price is normal (purchasing-power pricing); storefront switching is hard, so arbitrage risk is low.
- Before overriding Korea or the US, confirm the app is actually **available in those storefronts** (App Store Connect → Availability).
- **Who the KR/US prices are for (rewritten 2026-10-12):** region packs made Korea and the US real regions (`Region.php` MY/KR/US/OTHER). The audience there is **Malaysian and Muslim students abroad** (ambassadors at Korea University and Penn State), not Korean or American locals. They usually hold a local Apple ID once settled, so they see ₩/$ prices. ₩7,900 / $5.99 is high for a student; the founding price (₩5,900 / $3.99) and ambassador offer codes matter more there than in Malaysia.
- **Plus is weak in Korea today** — see [Korea](#korea-plus-is-thin-there). Set the KRW price, but don't run KR-targeted Plus promotion until Ask Bubu works there.
- `Region` is location-based and Apple prices by storefront; a Malaysian student in Seoul with a Malaysian Apple ID pays RM. Never derive price from `Region`.

## Korea: Plus is thin there

Kakao Local powers Korean search and Decide, but its data is **live-only**: `decisions.kakao_pick` stores just `{place_id, url, shown_ids, query, accepted_at}` (`KakaoDecide`), the pick is random with no Brain, tune/what-if/choose return 404 and why-not 422, and Kakao places **never become `restaurants` rows**. Kakao takes over whenever fewer than `services.kakao.decide_min_pool` (5) local rows qualify.

What that means for Plus in Korea:
- **Ask Bubu `find_places`** reads `localRestaurantsNear()` — only Google-synced or community rows exist in Korea, so answers will often be "nothing nearby yet".
- **Saves and collections** need a `restaurant_id`, so Kakao picks can't be saved.
- **Lunch call reason** needs the Brain; Kakao picks have none.
- **Pro / owner tools** can't attach to Kakao places (no row to own).

Options, smallest first:
1. **Launch Plus without Korea-specific promotion** (recommended). Price exists, paywall placements are feature-driven, Ask Bubu admits gaps honestly. Track KR "nothing nearby" rate on the admin page.
2. Give Ask Bubu a **live Kakao tool** (no storage, same terms as Decide). Blocked: KAKAO_PLAN.md contradicts itself (header says AI use of Kakao results permitted 2026-10-11; section 1 says it stays out of the LLM until Kakao answers). Resolve that first.
3. Grow the Korean `restaurants` pool through community adds by the Korea University ambassador — the only way saves/collections/owner tools work there.

## Selling Plus

**Paywall placements** (where it may appear)
- Ask Bubu allowance used up.
- Teaser card in Selera insights ("See what your taste says about you").
- Plus card in Settings (after `yourMakanApaCard`).
- Tapping "New collection" or the "Why this one?" section on a lunch call.
- **Never** mid-pick, on first launch, or before the user's first accepted pick.

**Offers**
- **Free trial:** 1 week via Apple introductory offer (once per subscription group per Apple ID). Users who already had referral Plus see "Continue with Plus" copy instead of a trial pitch.
- **Yearly plan** shown as the default option (~2 months free).
- **Founding-member price:** launch at the founding price (RM 5.90 / ₩5,900 / $3.99) for a launch window (e.g. 60 days), then raise to standard for new subscribers and choose **"keep current price for existing subscribers"** in App Store Connect. No extra product or code — Apple handles price preservation. (A fixed "first 500" cap isn't enforceable this way; use a time window.)

**Ambassadors and universities**
- Each university ambassador gets a **custom offer code** (e.g. `UITMJASIN1M`, one free month of Plus). Custom codes allow up to 25,000 redemptions per batch, max 6-month expiry, one redemption per customer per offer. Gives growth + per-university attribution (pairs with `ambassador_university_id`).
- **Active ambassadors get Plus free** while they hold the role (`source=admin` grant, revoked when the role ends). Makes the role more attractive without selling the crest.

**Keeping subscribers**
- **RevenueCat Customer Center** (in-app manage subscription): cancellation survey + a retention offer (e.g. 50% off for 3 months via Apple promotional offer) before cancel.
- **Apple win-back offers** for lapsed subscribers (in-app sheet iOS 18+).
- **Billing Grace Period ON** in App Store Connect — failed renewals keep access while Apple retries. Web Billing has its own retry settings in RevenueCat; `BILLING_ISSUE` handling is the same.
- **Web purchases:** trial and founding price are set per web product in RevenueCat (no Apple price preservation; keep founding subscribers on the old product). Win-back and Customer Center are Apple-only; web users manage via the web billing portal.
- **Family Sharing OFF** at launch — it can be turned on later but **not off** once enabled.

**Measurement and kill criteria**
- Funnel events: `paywall_view` (with `placement`), `purchase_start`, `purchase_success`, `purchase_cancel`, `trial_start`, `trial_convert`, `subscription_cancel`. RevenueCat charts cover revenue/churn; our events cover *where* people convert. Store in a small `billing_events` table (or reuse `MarketingEvent` only if it gains `user_id`).
- **Push reach** (needed before the lunch call can be sold): store OS push permission status alongside the device token, and report push opt-in rate and mealtime-nudge opt-in rate on the admin page.
- **Install attribution:** add `ct=share` to `share/go/download` and `ct=web_<from>` to the website's App Store buttons (reuse `MarketingController::downloadUrl()`; today only campus links carry `ct`). Without it, installs from shares look organic and the referral/ambassador channels in Phase 3 can't be compared.
- Admin page: conversion by placement, trial → paid rate, Ask Bubu cost per Plus user, free-tier Ask Bubu cost.
- **Success bar after 60 days of billing:** ≥ 1.5% of MAU paying (bottom of the forecast range; set above it and a product on forecast would be killed), Ask Bubu + Places cost per Plus user < 30% of net revenue, **no drop** in free-user d30 retention. Miss it → turn Plus features free and add a tip jar instead.

**Compliance checklist**
- Paywall shows price, billing period, trial terms, auto-renew notice, and links to Terms and Privacy Policy (Guideline 3.1.2); same links in App Store metadata.
- Terms of Service: subscription section (billing, renewal; cancellation and refunds via Apple for App Store purchases, via us / the web billing portal for web purchases).
- Privacy Policy: AI chat processing by a third-party model provider (OpenRouter / Anthropic), retention 30 days.
- Billing support email on the paywall and in Settings.
- Restore purchases always reachable.

## Ask Bubu — personal AI assistant

Chat where users ask e.g. "spicy, cheap, near campus, open now", answered through the Brain and existing API services as tools. Named after the mascot, **Bubu** (see [Mascot name](#mascot-name-bubu)).

**How it differs from what's free today:** the `craving` field on `recommendations/solo` already does free natural-language search, but `JudgmentIntentParser` returns **one food concept only**. Ask Bubu's value is multi-constraint requests (food + budget + distance + time + mood), follow-ups ("something less oily"), and taste-aware explanations. The paywall copy must make that difference obvious.

**Rule inherited from `config/judgment.php`:** AI informs the system, never becomes it. Never changes halal status, hides content, or deletes anything.

**Prerequisites (do first)**
- **Brain must be ON in production.** `BRAIN_ENABLED` defaults OFF in production; with it off, `decide` is skipped and tune/what-if return 404. Ask Bubu is therefore blocked on the Brain rollout, not just on code. The Brain itself stays deterministic and LLM-free — the LLM only calls it as a tool.
  - **How it's judged.** `BrainStateFactory::enabled()` is one global switch, so production is 100% v1 or 100% v2 — there's no per-cohort split to compare. Judge it before/after instead, with the kill switch ready.
  - **Primary metric (proposed — confirm):** accept rate (accepted decisions ÷ decisions), with regret rate as the guardrail. Flip only after at least 300 v1 decisions are logged; keep v2 if, after 300 v2 decisions, accept rate is not lower and regret is not higher. The other eval-page metrics (fatigue, concentration, coverage) are regression checks, not the verdict.
  - **Switch-over runbook:** (1) confirm taste events are being recorded with the Brain off (they are since 2026-10-10: accept, search choice and vibe tags no longer check the switch); (2) `php artisan brain:backfill-events` (idempotent) to seed history from before that; (3) `php artisan brain:rebuild-taste --all`; (4) set `BRAIN_ENABLED=true` on web, worker and scheduler together; (5) watch the eval page daily for a week. Skipping (2)–(3) means every user starts with an empty Selera.
  - **v1 is pinned by a test** (`MakanBrainTest::test_v1_ranking_is_pinned_and_ignores_taste_history`) — the "byte-identical v1" rule below is now enforced, including that taste history never changes v1.
- **Hard daily cap on Google Places spend.** Today `admin:check-api-budget` only emails at 08:00; nothing stops spend, and guest tokens are free to mint (now 20/min and 300/day per IP, `config/auth.php` `guest_signups`; raised since the last audit, so free spend is *less* bounded than before). Tiling caps a single Nearby call at 7 tiles (`PlacesService::MAX_TILES`), but nothing caps calls per day. Add a daily call cap using the `DailyAiBudget` pattern with a `places` scope; over the cap, serve `localRestaurantsNear()` instead of calling Google. Also cap the viewport radius in `NearbyRequest` (e.g. 10 km). This bounds the existing free Nearby/Decide paths, not just the assistant.
- When extracting the service, keep the v1 path byte-identical with the switch off (existing rollout rule).
- **Extract the decision pipeline into a service.** All of it lives in `RecommendationController::solo()` / `soloWithBrain()`; there's nothing to call from the assistant. Extracting a `SoloDecisionService` also cleans up the controller. Since 2026-10-11 `solo()` also holds the Kakao branch, so the extraction is a bit bigger; keep Kakao out of the assistant's local mode.
- **Move what-if and choose logic out of `DecisionBrainController`** into services (tune already lives in `TuneService`).

**Architecture**
- New `Services/Assistant/` engine — multi-turn tool loop, separate from the single-shot `OpenRouterJudgmentEngine`.
- Reuses the OpenRouter key and HTTP/retry conventions.
- Model: `env('ASSISTANT_MODEL', 'anthropic/claude-haiku-5.5')`. Haiku 5.5 was released 2026-10-07 — run a small eval set (20–30 real requests, tool-call accuracy, grounding) against Haiku 4.5 before launch; keep the env switch.
- Prompt caching via OpenRouter `cache_control` on the system prompt + tool definitions (cache reads 0.1× price; Haiku 5.5 caches from 512 tokens).
- Max 3 tool rounds per turn. Non-streaming first with a typing indicator; SSE later.

**Tools — thin wrappers over existing code**

| Tool | Reuses | Places cost |
|---|---|---|
| `find_places` | extracted `SoloDecisionService` in **local mode**: `PlacesService::localRestaurantsNear()` (DB only) + `MakanBrain::decide` — *verify in Phase P that `decide` accepts local-only rows* | **none** |
| `why_not` | `TasteEventRecorder::whyNot` | none |
| `what_if` | extracted counterfactual service (`Counterfactual`) | none |
| `tune` | `TuneService::tune` — but present step calls `enrichWinner` | Place Details + Atmosphere (cached 10 min) |
| `my_taste` | `SeleraTraits::describe` + `TasteOwner` (as in `SeleraController::show`) | none |
| `context` | `ContextEngine` (weather, time) | none |
| `save_place` | `RestaurantSave` — requires the client's `installation_id` (send it in a header) | none |

The LLM produces structured tool arguments itself (cuisine, budget, distance, open-now); `IntentParser` is not a constraint parser. The assistant **never triggers Nearby/Text Search** — local DB only. If the local area is empty, it says so and offers to open the normal picker.

**Why Bubu, not a general AI chatbot**

Users already have free AI chatbots that will answer "where should I eat?". Ask Bubu has to **show** why it's better, in two places.

1. **Proof in every answer (main lever).** Each place card Bubu returns carries short fact chips taken from our data, never from the model: *Open until 11 pm* · *400 m away* · *Fits your budget* · *You liked Thai last week*. Halal status shows as the existing `HalalBadge` on the card, never as a text chip. Chips are built from `ReasonComposer` / `ReasonCatalog` (the Brain's existing reason system) and `ContextEngine`, so the reasons are real and auditable.
2. **"Why Bubu?" explanation page.** A sheet reachable from the paywall ("Why Bubu?" link), the first time a user opens Ask Bubu, and the Plus card in Settings. Content:

| General AI chatbot | Ask Bubu |
|---|---|
| Suggests places from the internet, which may be closed, moved or made up | Only suggests real places in MakanApa, with live opening hours and distance |
| Guesses whether a place is halal | Shows halal status from MakanApa's records and certificates, never guessed |
| Starts from zero every chat | Remembers your taste (Selera): what you picked, skipped and liked |
| Doesn't know it's raining or nearly closing time | Uses weather, time of day and your budget |
| Gives you text | Gives you place cards: map, directions, save, share |
| Generic internet opinions | Community vibes and ambassador picks from people near you |

   Plus one illustrative example: the same question ("spicy, cheap, near campus, open now") answered as a plain text list vs as Bubu's place cards with fact chips.

**Copy rules for this page**
- Say "general AI chatbots", never name competitors.
- Only claim what's true at launch. Bubu must admit gaps: "I only know places in MakanApa. Nothing nearby matches yet; want to try the normal picker?"
- English-only, no emoji, no em dashes in user-facing strings.
- Honest about privacy: "Chats are deleted after 30 days. Your taste memory is yours to view, mute or reset in Selera."

**Implementation**
- iOS: one SwiftUI sheet (static content + the illustrative example built from real card components with sample data). Per ios-design-bar: sheet, not a push; dismissible; works in light/dark.
- No API work beyond the fact chips, which the assistant response already includes (`reasons: [...]` per place ID).

**Safety**
- Grounding: the LLM may only reference restaurant IDs returned by a tool in that turn. iOS renders place cards from IDs, never from LLM prose. Halal status always from DB.
- Prompt injection: tool results (community posts, menus, submissions) are wrapped as data, never instructions.
- Authorization: server-side tool calls check `decision.user_id === user.id` (the HTTP `X-Decision-Token` header check is a per-decision secret on a private controller trait, not reusable server-side).

**Cost**

| Item | Unit cost | Source |
|---|---|---|
| LLM turn, Haiku 5.5 (~2 calls, ~6k in, ~400 out, before caching) | **~$0.0008** | "from" $0.10 / $0.50 per 1M — confirm standard rate is flat |
| LLM turn, Haiku 4.5 (same) | ~$0.008 | $1 / $5 per 1M |
| Place Details + Atmosphere (per `tune`) | $0.025 | `admin_budgets.php` |
| Nearby Search, cache miss, radius > 1 km | up to 7 × $0.035 = **$0.245** | `PlacesService` tiling, 8 h area cache |
| OpenRouter credit purchase fee | +5.5% | OpenRouter FAQ |

Takeaways:
- On Haiku 5.5, LLM cost is small: 100 turns ≈ $0.08. **Google Places is the real cost** — one cache-miss decision costs as much as ~300 assistant turns. Hence local-only `find_places`.
- Allowances exist mainly to cap abuse and Places spend, not LLM spend — but they only bound the assistant. The free Nearby/Decide paths have no Places ceiling today; the hard daily cap in the prerequisites is what bounds those.

**Allowances (starting values, tune from measured usage)**
- Plus: **150 turns/month**, shown in-app ("112 of 150 left this month").
- Referral 7-day Plus: **30 turns**.
- Free: **3 turns/week**, registered users only — enough to feel the value.
  - Worst case at scale: 10,000 free MAU × ~13 turns/month × $0.0008 ≈ **$104/month**, vs ~RM 930–1,245 (~$230–305) net Plus revenue at 10k MAU. The free tier scales with MAU and is **not** covered by revenue — watch it on the cost page; cut to 1/week or drop it if needed.
- Global: per-day ceiling via existing `DailyAiBudget`-style counter + raise the OpenRouter monthly budget (currently $20) before launch.
- Per-user counting = count of the user's messages in `assistant_messages` for the current period — no separate counter table. (`DailyAiBudget` is a global per-day cache counter, not per-user.)
- Order of use: **period allowance first, then message-pack balance** (see below).

**Bubu message packs (top-ups)**

When the allowance runs out, users can buy extra messages instead of waiting for the next week/month.

**Fixed packs** (bigger = cheaper per message)

| Pack | Malaysia | Korea | US | Per message (MY) | Our cost (Haiku 5.5) |
|---|---|---|---|---|---|
| **20 messages** | RM 1.90 | ₩1,400 | $0.99 | RM 0.095 | ~$0.016 |
| **50 messages** | RM 2.90 | ₩2,900 | $1.99 | RM 0.058 | ~$0.04 |
| **100 messages** | RM 4.90 | ₩4,900 | $3.99 | RM 0.049 | ~$0.08 |
| **200 messages** (best value) | RM 7.90 | ₩7,900 | $5.99 | RM 0.040 | ~$0.16 |

**Custom amount** (user picks any amount from 10 to 100, in steps of 10)

| Unit | Malaysia | Korea | US |
|---|---|---|---|
| **10 messages** × quantity 1–10 | RM 0.90 per 10 | ₩700 per 10 | $0.49 per 10 |

- Example: 70 messages = 7 × 10 = RM 6.30 / ₩4,900 / $3.43. The picker always shows when a fixed pack is cheaper ("100 messages for {price} is better value", price from the store product).
- How it works: one consumable product `bubu_messages_10`, bought with StoreKit's **purchase quantity** option (Apple allows quantity 1–10 per purchase for consumables). Ledger grant = 10 × purchased quantity.
- **Verify before building:** that RevenueCat's purchase API passes quantity through and reports it in the transaction/webhook. If it doesn't, drop the custom picker and keep the fixed ladder; users can still stack packs (50 + 20 = 70).
- Custom is priced at the small-pack rate on purpose, so the fixed packs stay the better deal.

Other storefronts auto-equalize from MYR. Exact values must match Apple price points.

**Web packs:** RevenueCat Web Billing's MYR minimum is RM 3.99, so the RM 1.90 / RM 2.90 packs and the RM 0.90 custom unit can't be sold on web. A web ladder would start at the 100-message pack (RM 4.90) and up. Decide when web packs are built.

- **Who can buy:** everyone registered. When a **free** user runs out, the sheet shows **Plus first** (150/month + lunch call reason + collections for the Plus monthly price from the store product), packs second. When a **Plus** user runs out, it shows packs only.
- **Never expire.** App Review Guideline 3.1.1: credits bought via IAP may not expire. Monthly/weekly allowances still reset; purchased messages carry over forever.
- **Store type:** Apple **consumable** IAP via RevenueCat (`bubu_messages_20`, `bubu_messages_50`, `bubu_messages_100`, `bubu_messages_200`, `bubu_messages_10` for custom amounts). Apple doesn't restore consumables, so the balance lives on **our server, tied to the account** — survives reinstalls and new devices.
- **Ledger, not a counter:** `assistant_credit_ledger` (`user_id`, `delta`, `reason` = `purchase` | `refund` | `referral` | `admin` | `usage`, `store_transaction_id` unique, `created_at`). Balance = `SUM(delta)`. Unique transaction ID makes purchase grants idempotent across webhook + sync.
- **Grant flow:** purchase → `POST /me/billing/sync` → server reads the transaction from RevenueCat → ledger `+50`/`+200`. Webhook `NON_RENEWING_PURCHASE` is the backup path (same idempotency key).
- **Refunds:** RevenueCat `CANCELLATION` on a consumable → ledger `-N`. Balance may go negative; Ask Bubu blocks until it's back above zero.
- **Reuse:** the paying-subscriber referral reward (+50 messages) and admin comps write to the same ledger. No separate mechanism.
- **Account deletion:** delete flow warns "Unused Bubu messages will be lost."
- **Copy:** say "messages", not "credits" ("+50 Bubu messages"). No em dashes, no emoji.
- **Unit name decided: "messages"** (not credits, tokens, or a custom currency). One unit = one question, no conversion math, most honest. Revisit a branded currency (e.g. "Grains") only if packs start buying several things at different costs; the ledger stores plain units, so balances would convert 1:1 with no migration.
- **Guardrail:** a purchased balance doesn't bypass the global daily ceiling or per-minute throttle (abuse/cost protection).

**Cost visibility**
- `ApiUsageDaily` stores call counts only, and the admin cost page prices OpenRouter from `ai_judgments` tokens. So: store `input_tokens` / `output_tokens` / `model` on `assistant_messages` and add an assistant line in `ApiCostReport::openRouterLines`.
- **Add `anthropic/claude-haiku-5.5` to `admin_budgets.openrouter.models`** — unlisted models fall back to the $1/$5 default and would overstate cost 10×.

**Data & privacy**
- `assistant_conversations` / `assistant_messages` migrations, dated after the newest file in `api/database/migrations` at the time (currently `2026_10_12_050933`).
- 30-day retention via a scheduled prune; deleted with the account (FK cascade).
- "Delete chat history" button in the chat screen. Selera reset only inserts a boundary event today, so if chats should clear on reset, that's a new explicit delete.
- Chat logs are personal data under Malaysia's PDPA (amended 2024, in force 2025): breach notification to the Commissioner within 72 hours; check whether a DPO appointment is required. Update the privacy policy to cover AI chat processing by a third-party model provider.

**Plumbing**
- `ASSISTANT_ENABLED` + `config/assistant.php` (model, allowances, max rounds), own throttle class, `registered` middleware.
- PHPUnit feature tests (repo uses PHPUnit classes, not Pest) with a fake provider modelled on `tests/Support/FakesJudgments.php`.
- iOS: chat screen reusing existing place cards; allowance-exhausted state → paywall sheet; empty/error/offline states per ios-design-bar.

## Referral: 7 days of Plus for a successful invite

Reward a **successful referral**, not a tap on Share.

**Flow**
1. User shares their personal invite link `/i/{code}`.
2. Friend joins and **accepts their first pick** (`decisions/{id}/accept`) **after** the invite was attributed.
3. Both get **7 days of Plus** (`source=referral`, with the 30-turn Ask Bubu allowance).

**Copy**

> **Good food is better with friends.**
> Invite a friend to MakanApa. When they join and make their first pick, you both get 7 days of Plus.
> [Invite a friend]

**UI**
- Cream-and-gold reward card, Bubu holding a gift, prominent **"+7 days"**.
- States: *Invite sent → Friend joined → Reward unlocked*; once unlocked show expiry + "Try Ask Bubu".
- Full state cycle per ios-design-bar: empty, pending, unlocked, capped, expired.

**Attribution**
- Add `/i/*` to the AASA components in `SharePlaceController::appSiteAssociation` and an `invite` case to `DeepLinkDestination(url:)`.
- Landing page "Open app" button uses `makanapa://` (a universal link tapped on its own domain stays in Safari — same as `/p/*`).
- Fresh installs lose the link (no install referrer on iOS): landing page shows the code; onboarding/sign-up has "Have an invite code?" (valid 7 days after sign-up).
- New `referrals` table (`inviter_id`, `invitee_id`, `code`, `attributed_at`, `qualified_at`, `rewarded_at`). `MarketingEvent` has no `user_id` and `signup_source` is a 40-char tag — neither can carry attribution.

**Anti-abuse rules**
- Max **4 rewarded referrals per month** per inviter; **one welcome reward per invitee identity, ever**.
- **Invitee qualifies via Apple/Google sign-in, or via a verified email.** Email sign-up has no verification today (`MustVerifyEmail` is off), so add email verification: email accounts can use the app as now, but only count for referral rewards once verified.
- "New" = account created, or guest upgraded (`upgraded_from_guest_at`), **after** attribution. Guest rows can be months old; their earlier picks don't count.
- Account deletion is a hard delete that frees the Apple/Google identity — store **hashed** `apple_sub` / `google_sub` / email on `AccountDeletion` and refuse referral rewards for returning identities.
- Device signals are weak (`installation_id` is a UserDefaults UUID that resets on reinstall; APNs tokens need push permission) — log them for review, don't rely on them.
- Rewards stack as consecutive 7-day windows.

**Existing paying subscribers**
- A local or RevenueCat grant does **not** postpone their Apple bill. Give them **+50 Bubu messages** (written to `assistant_credit_ledger`, `reason=referral`) instead. This reward waits for the ledger (Phase 2b); until then paying inviters get nothing extra, and the copy only promises the invitee's 7 days. Never promise an automatic extension.

**Grant mechanism & App Review**
- Launch with our own server-side grant (`source=referral`): no payment method, no auto-renew, no renewal terms to disclose.
- Policy nuance: no guideline explicitly covers free in-app referral grants; 3.1.1 bans non-IAP mechanisms that unlock paid features, which a reviewer could stretch to cover it. Apple's documented route for member referral programs is **subscription offer codes** ([up to 1M codes/quarter](https://developer.apple.com/help/app-store-connect/manage-subscriptions/set-up-subscription-offer-codes); eligibility per offer: new, existing, expired; one per customer per offer). If review pushes back, switch rewards to offer codes — which also gives paying subscribers a real free period.

## Mascot name: Bubu

The rice-ball mascot (red "LET'S EAT" cap, spoon) gets a proper name: **Bubu**, a nod to *bubur* (rice porridge). It replaces "Nasi", which is a generic word that clashes with dish names already in the app ("Nasi lemak", "Nasi kandar") and can't be owned as a brand.

**Not public until Plus launches (decided 2026-10-10).** App and website copy don't name the mascot until then ("Let MakanApa pick", "Thinking..."). Plus launch reveals the name with Ask Bubu: swap those strings back to Bubu in the same release.

**Timing conflict (found 2026-10-12, open decision 11):** [When to turn it on](#when-to-turn-it-on) lets free Ask Bubu launch *before* billing, which reveals the name early. Either (a) tie the reveal to the **Ask Bubu** launch rather than billing, or (b) launch free Ask Bubu under a neutral name and rename at billing. (a) is simpler: one reveal, and the name is learned before anyone is asked to pay. Either way, every "Bubu" string (iOS, web, push copy, Blade) sits behind the server flag that turns Ask Bubu on, so the reveal is one switch.

**Why it works:** two syllables, a repeated sound that's easy to remember, easy to say in Malay, Chinese, Tamil and English, and warm in tone. Works in "Ask Bubu" and "Bubu picked this for you".

**Risks found in checks (2026-10-10):**
- **Malay meaning:** *bubu* is the traditional fish trap, a one-way funnel that fish enter but can't leave ([Wikipedia](https://en.wikipedia.org/wiki/Bubu_(fish_trap))). Most Malaysians will know it. Harmless on its own, but next to a paywall it invites a "Bubu = trap" joke. Keep the paywall honest (clear price, easy cancel) and consider leaning into it playfully in brand copy ("the only bubu you'll want to be caught in").
- **Existing character IP:** "Bubu & Dudu" (一二布布) are popular Chinese sticker/plush characters (a bear and a panda) with merchandise across Southeast Asia. A different design, but merch and trademark overlap is possible.
- **Same niche:** a web product called "Bubu AI" meal planner exists (bubuaimealplanner.com). There's also a "BuBu Bubble Tea" loyalty app on the App Store. Neither is a direct clone, but App Store search for "Bubu" will be shared.
- **English:** sounds like "boo-boo" (a small mistake or injury). Minor, mostly a joke risk.

**Decision:** Bubu is final (2026-10-10). Trademark, App Store and user checks were skipped by choice; the overlaps above are accepted. Kepi is no longer a fallback.

**Scope of the rename:** copy and assets only. Rename in-app copy, the mascot SVG `<title>`/`<desc>` text, and marketing. Leave the `nasiCream` colour token, `MascotView` and asset file names alone; renaming code identifiers gains nothing.

## Pro (later)

Rewritten 2026-10-12 after the code audit and an App Store guideline check.

**What already exists**
- **Owner claim flow** on iOS (`OwnerClaimSheet`) and web (`web/src/features/halal/Halal.tsx`), posting to the claim route in `routes/api.php`, reviewed in the moderation queue (`RestaurantSubmissionModerationService`), with an `OwnerClaimDecided` notification. Approved claims create a `RestaurantOwner` link.
- **Counters Pro analytics can read without new tracking:** `restaurants.impressions_count / accepted_count / rejected_count`, `RestaurantSave` rows, `taste_events.restaurant_id`, and `MarketingEvent` share events with `restaurant_id`.
- Missing: any owner-facing management UI and a Filament resource for owners.

**Proposed shape: free owner tools first, paid reach second**

| Step | What | Price | Why |
|---|---|---|---|
| 4a **Owner tools** | Owner web portal: claim status, menu + photos (into the existing moderation queue, "Pending review"), weekly numbers (shown, saves, picked) | **Free** | Owner-supplied menus fix the empty `RestaurantMenuItem` data that blocks the price filter and improves Ask Bubu; verified owners are the supply side of Pro. Free tools build the list Pro sells to |
| 4b **Pro** | "Sponsored" slot near campuses + richer stats (by hour, vs nearby average) | Paid, monthly per outlet | Only once 4a has active owners |

**Where Pro is sold: owner web portal only (Stripe), never in the consumer apps.**
- Apple 3.1.3(g), verbatim: *"Digital purchases for content that is experienced or consumed in an app, including buying advertisements to display in the same app (such as sales of 'boosts' for posts in a social media app) must use in-app purchase."* A paid Sponsored slot is that kind of purchase. The guideline governs purchases made **through the app**; App Review has no hook on a sale that happens entirely on a website with no path from the app (how web ad managers work). So the plan rests on: **no Pro purchase, price, link or call to action anywhere in the iOS app**, owners buy only on the owner portal. (3.1.3(f) doesn't apply: MakanApa isn't a free stand-alone companion, it sells Plus.)
- **Owners reach the portal by email only.** `OwnerClaimDecided` today sends `database`, `apn` and web push (and only with `COMMUNITY_SUBMISSIONS` opted in), all of which surface inside the iOS app. Add a **mail channel** for claim approval carrying the portal link, and never put the portal URL in the APNs, database or web-push payloads. The web app may link the portal from an owner's own claim page (web isn't under App Review).
- **Risk (Medium–High):** a reviewer could still treat in-app Sponsored rows as an unpaid-through-IAP boost. Fallbacks, cheapest first: (1) make Sponsored rows web-only and keep owner tools free in the app; (2) sell Pro as an IAP in an in-app owner section (far more work). Decide before building 4b.
- Portal build: a **second Filament panel** scoped to the owner's restaurants, reusing the moderation services. Payments via **RevenueCat Web Billing** (already integrated in Phase W): a `pro_monthly` web product granting `pro` + `plus`, arriving through the same webhook → `user_entitlements`. One rail, no second Stripe integration. If owners ask for FPX, add Stripe Checkout directly (`source=stripe`) then.
- **Pro includes Plus** for the owner (one web product grants `pro` + `plus`). Replaces Max (proposed, decision 13).

**Price anchors (directional, 2026)**
- Delivery platforms take 12–30% commission (GrabFood, foodpanda; third-party estimates). POS software: StoreHub from ~RM 102/month, Eats365 from ~RM 99/month. Google Business Profile: free.
- Korea: Naver Place ads are cost-per-click (₩50 minimum); Baemin moved from a ₩88,000/month flat fee to per-click ads in 2025. US: Yelp Ads ~$300–500/month typical for small businesses (agency estimates).
- Starting point to test: **RM 49/month per outlet** for a campus-radius Sponsored slot, first month free for owners from ambassador campuses. Validate with 5–10 owners near UiTM Jasin / Sg Buloh before building 4b.

**Sponsored placement rules (trust anchor)**
- Label **"Sponsored"**, not "Featured" (proposed, decision 15): the US FTC expects paid placement to be clearly disclosed, and Malaysia's Consumer Protection Act 1999 (ss. 9, 10) bars misleading representations; "Featured" reads as an editorial pick.
- Its own row **above** the results and repeated in the place sheet. **Never** inside the ranked list, never passed to `MakanBrain::decide`, never returned by Ask Bubu, never in nudges.
- Halal status of a Sponsored place comes from the same records as everyone else; payment never changes it.
- **Not in Korea**: Kakao places have no row to sponsor.

## Design approach

Applies `.claude/skills/ios-design-bar/SKILL.md`. Paths relative to `ios/MakanApa/` unless noted. Checked 2026-10-12.

**Ground truth the bar gets wrong**
- **The app is light-only**: `App/MakanApaApp.swift` forces `.preferredColorScheme(.light)` and the core colours (sambalRed, nasiCream, kicap, kunyit, pandan) have no dark variants. The bar's "both themes from day one" check is moot for Plus until dark mode exists app-wide. Don't build dark mode for Plus alone.
- Radius drift: tokens are card 24 / row 16 (`Core/DesignSystem/Shape.swift`), but `SettingsCard` uses 20 and buttons 18. New Plus UI uses card, row or capsule only; the Settings Plus card keeps 20 to match its neighbours.

**Plus colour**
- Existing palette (`Core/DesignSystem/Palette.swift`): sambalRed #E94B35 (accent), nasiCream #FFF4DD, kicap #29201D, pandan #4F7A57, kunyit #F4B942, kicapSecondary #7A6F63, surface #FFFBF3, hairline #ECDFC6.
- Add **one** token, `plus` #7945C8 (from the crest prompt): 5.5:1 on nasiCream, white on it 6.0:1. Optional `plusDeep` #48216F for small text. Light purple #B68AE8 is fill-only (2.5:1). Mirror as `--color-plus` in `web/src/index.css`.
- **Purple marks membership; it is never an accent.** Only the crest, the small mark and a 12% tint behind the Settings row icon. **Every button stays sambalRed** (`MakanPrimaryButton`). No purple gradients or glow (anti-slop laws 1–2).

**Paywall** — `.sheet`, large detent, own `NavigationStack` with Close; `.interactiveDismissDisabled` only while purchasing. Not a `fullScreenCover`: it's a decline-able interruption.
- Layout: full crest → one display line ("Find food that fits your cravings, budget, and taste.") → four benefit rows (SF Symbol + one line) → "Why Bubu?" link → plan picker (yearly preselected, per-month price in `.monospacedDigit()`) → button pinned with `.safeAreaInset(edge: .bottom)` → compliance text + Restore.
- States: loading products (`.redacted` picker) · products failed (inline error + Try again; Restore still reachable) · purchasing (button progress, not dismissible) · cancelled (silent) · success (content swaps to celebration: full crest, `.sensoryFeedback(.success)`, **fade only**, no scale pop or confetti on Bubu art; Done completes the action that opened the paywall) · restore found / not found (one inline line) · already entitled via grant (paywall never shown) · guest (`accountSignInSheet` from `Features/Auth/AccountRequired.swift` first, then the paywall from the same caller).
- **RevenueCat Paywalls spike, pass/fail:** (1) headline in SF Rounded heavy (`makanDisplay`) via font provider; (2) button matches `MakanPrimaryButton` (56 pt, sambalRed); (3) "Why Bubu?" opens our own sheet; (4) respects forced light; (5) prices from StoreKit, no "RM" literal; (6) no clipping at Dynamic Type XL; (7) full 3.1.2 footer (price, period, trial terms, auto-renew, Terms, Privacy, Restore, support email); (8) separate offering for "Continue with Plus" (no trial pitch) after a referral; (9) post-purchase hook to run `POST /me/billing/sync` → refresh `auth/me` → dismiss. **Any of 1, 3, 7, 9 fails → custom SwiftUI sheet.**

**Ask Bubu chat**
- **Push** in the Decide tab (`case askBubu` in `Route`, `App/AppRouter.swift`); entry card on Home beside `savedPickCard` (`Features/Home/HomeView.swift`). Title "Ask Bubu"; toolbar menu: "Why Bubu?", "Delete chat history" (`.confirmationDialog`). Conversation lives in an `@Observable` store so it survives tab switches.
- Place cards reuse **`SearchResultRow(bare: true)`** (`Features/Nearby/NearbySearchPanel.swift`: name, distance, open/closing, spend, `HalalBadge`, rating) inside a surface card with Directions / Save / Share. API returns rows shaped like `PlaceSearchResult`. Tap → Nearby place sheet via `pendingDeepLink.placeRequest` with a new `PlaceOpenSource.assistant` (tab switch; the sheet is private to `NearbyView`).
- Fact chips: capsule style from `Features/Result/ResultView.swift` (surface fill, hairline, `.footnote`), one SF Symbol per reason family as `KenapaNiSection` does. **Never render the server's `icon` emoji field.** Halal is always `HalalBadge`, never a text chip.
- Allowance: text line above the input, "112 of 150 left this month", `.monospacedDigit()`, kicapSecondary → sambalRed at ≤ 10. No progress bar, no purple.
- States: first open → "Why Bubu?" via `FeatureGuideSheet` · empty → still mascot + three example prompt chips · typing → "Thinking..." text (never a bouncing mascot) · no local match → reply + "Open the picker" button · error → inline Retry under the message · offline → banner with `Copy.connectionErrorDetail`, input disabled · rate-limited → own copy · out of messages → medium-detent sheet (free: Plus first, packs second; Plus: packs only) · guest → `AccountRequired`.
- **Mascot never animates:** `MascotView` loops in idle/thinking/sad; use `.wave` (held still) or a plain `Image`.

**Lunch call** — push routes through `App/DeepLinkDestination.swift` into the Nearby place sheet (`source: .nudge`). Plus adds one reason line to the copy and a "Why this one?" section (from `KenapaNiSection`) on that sheet. Text-only push (no notification service extension).

**Collections** — on `Features/Favorites/FavoritesView.swift` (a `List` over `PlacePreferencesStore.shared`): horizontal chip row above the list, "All saved" first (free, unlimited), "New collection" last (paywall for non-Plus). Add via `.swipeActions` + `.contextMenu`; create in a small sheet; rename/delete from the chip's context menu. Empty: "Swipe a saved place to add it here."

**Settings Plus card** — `plusCard` after `yourMakanApaCard` (`Features/Settings/SettingsView.swift`), built from `SettingsCard`/`SettingsRow`, 64 pt crest + status line. States: not subscribed ("Get Plus") · active ("Renews 12 Nov", Manage, Restore, "Billed by Apple" / "Billed on the web") · granted ("Plus until 19 Oct, from a friend's invite"; same for admin/ambassador) · billing grace (warning row) · expired (back to not subscribed) · guest (sign-in). Always: "Why Bubu?" and support email.

**Plus mark next to names** — own profile (`SettingsView`, beside the ambassador badge), community posts (`Features/Community/Posts/CommunityPostRow.swift`, on the name line before "· You"), restaurant sheet post authors (`CommunityRestaurantDetailSheet.swift`), ambassador picks (`AmbassadorPicksSection.swift`). Ambassador badge first, Plus second. Needs an `isPlus` flag on post-author payloads.

**State rule** — Plus and lunch-call state in the tab shell (an ancestor of `NearbyView`) comes from `AuthStore` (`auth/me`) or `@State` + `UserDefaults`, **never `@AppStorage`** (GMS writes UserDefaults on marker updates → render loop).

**Web app** — tokens in `web/src/index.css`. Ship first: small Plus mark in `web/src/features/community/PostRow.tsx` and a Plus status row in Settings under "Your MakanApa" **for members only** (referral/admin/iOS-bought). No purchase prompt on web until Phase W. "Why Bubu?" (`web/src/ui/Sheet.tsx` + static `web/src/ui/Mascot.tsx`) and every other "Bubu" string on web sit behind the same Ask Bubu flag as iOS. Web paywall (RevenueCat Web Billing), chat and packs follow in Phase W.

**Owner portal (Pro)** — Filament panel, not the consumer design system. "Sponsored" tag: small caption-style tag near the "◇ Community find" label (`NearbySearchPanel.swift`, plain pandan text), but **kicapSecondary** text in a hairline capsule, not pandan green (green reads as a community endorsement). Never sambalRed or purple.

## Phases

| # | Phase | Size | Depends on |
|---|---|---|---|
| P | Prerequisites. **Done:** `brain:backfill-events`, `brain:rebuild-taste`, v1 pin test. **Still to do:** run the Brain switch-over runbook and turn it ON in production (judged on the primary metric); hard daily Places cap + viewport radius cap; push opt-in chain fixed (no default-on) + push/nudge opt-in tracked, iOS and web; `ct=share` / `ct=web_*` install attribution; extract `SoloDecisionService` (Brain decide also runs in `NearbyController`; `solo()` now includes the Kakao branch); move what-if/choose into services; add Haiku 5.5 to `admin_budgets.openrouter.models` | Medium, ~5 days | — |
| A | **Assets:** Plus crest vector master, small no-lettering mark, asset-catalog + web exports; `plus` colour token (iOS + web) | Design, ~2–3 days | — (blocks all Plus UI) |
| 0 | `user_entitlements`, admin grant, `entitlements` on `auth/me` (iOS + web read it); free certified-halal filter | Low, ~1–2 days | — |
| 2 | Ask Bubu: engine, tools, allowances, cost reporting, model eval, privacy, iOS chat, fact chips, "Why Bubu?" page; **free 3/week launches here, before billing** (measures the value risk) | High, ~2 weeks + 1–2 days | P, 0 |
| 1 | RevenueCat Plus (iOS): dashboard, Small Business Program, storefront prices, intro offer, grace period, webhook + queue, sync, deletion hook, `logIn` after every sign-in, iOS SDK, paywall + placements (spike first), Customer Center, Settings card, ambassador offer codes, funnel events, Terms/Privacy updates | Medium–High, ~6–7 days | 0, A |
| 1b | Plus features: lunch call reason + "Why this one?", named collections, taste recap | Medium, ~4–5 days | P (incl. push opt-in fix), 0, 1, A |
| W | **Web Plus:** RevenueCat Web Billing (sandbox-test cross-platform entitlement first), web paywall, Settings row with "Billed on the web" + portal link | Medium, ~3–4 days | 1 |
| 2b | Bubu message packs: consumable products, credit ledger, sync + webhook grants, refunds, out-of-messages sheet (iOS first; web packs via Web Billing later). **Build only once data shows users hitting the allowance** | Low–Medium, ~2–3 days | 1, 2 |
| 3 | Referrals + email verification + ambassador Plus grants (local `user_entitlements`, no store products needed). Ambassador **offer codes** move to Phase 1 (they need the App Store subscription products) | Medium, ~6–7 days | 0, 2 |
| 4a | **Owner tools (free):** owner Filament panel, claim status, menu/photo uploads into moderation, weekly stats from existing counters, **new mail channel** on `OwnerClaimDecided` with the portal link | Medium, ~1 week | 0 |
| 4b | **Pro (paid):** RevenueCat Web Billing product → `pro` + `plus`, Sponsored row in Nearby (outside ranking), richer stats | Medium, ~1 week | W, 4a + 5–10 owners validated |

Order to build: P → 0 (+ A in parallel) → 2 (free Ask Bubu live, measure) → 3 (referrals can ship before billing) → 1 → 1b → W → 2b → 4a → 4b. Billing (1) only flips on once the free Ask Bubu numbers clear the value-risk bar and retention is healthy ([When to turn it on](#when-to-turn-it-on)).

## Decisions

Answered 2026-10-10:

1. **Prices:** RM 7.90 / ₩7,900 / $5.99 monthly; yearly RM 69 / ₩69,000 / $49.99; founding RM 5.90 / ₩5,900 / $3.99. US raised 2026-10-10 to line up with Korea; packs $1.99 / $5.99. Still to do: confirm Apple price points and Korea/US availability in App Store Connect.
2. **Plus launch features:** Ask Bubu + lunch call + named collections (+ taste insights, Plus badge). Widget later.
3. **Free Ask Bubu:** 3 messages/week, registered users only.
4. **Paywall:** RevenueCat Paywalls first; custom SwiftUI only if it fails the ios-design-bar.
5. **Referral identity bar:** both — Apple/Google sign-in qualifies immediately; email accounts qualify after verifying their email (adds email verification).
6. **Founding window:** 60 days from billing launch.
7. **Mascot name:** Bubu, final. Trademark/App Store/user checks skipped by choice; known overlaps accepted.

9. **Ask Bubu top-ups:** sell message packs (20 / 50 / 100 / 200) plus a custom amount (10–100 in steps of 10) to everyone; free users see Plus first.
10. **Top-up unit:** "messages". Custom currency only if packs ever buy more than one thing.

Still open:

8. **Pro surface:** proposed 2026-10-12 — owner web portal, free owner tools first (4a), paid Sponsored later (4b), sold only on the web (RevenueCat Web Billing), never in the consumer apps. Confirm.
11. **Bubu name reveal:** tie it to the free Ask Bubu launch (recommended) or keep it until billing and launch Ask Bubu under a neutral name?
12. **Web rail:** RevenueCat Web Billing (cards, Apple Pay, Google Pay) at Phase W (recommended); FPX passes via Stripe only if card drop-off shows up?
13. **Drop Max** and make Pro include Plus? (recommended)
14. **Korea:** launch Plus without KR promotion (recommended), and resolve the KAKAO_PLAN contradiction on AI use of Kakao results before giving Ask Bubu a Kakao tool?
15. **Sponsored label:** "Sponsored" instead of "Featured" (recommended for disclosure)?
16. **Success bar:** lowered 2% → 1.5% of MAU paying in this audit (2% sat at the top of the forecast). Confirm.

## Risks

- **High:** Plus too thin — lunch call turned out to be only a reason line (free nudges already name a place). Measure free Ask Bubu first; don't charge until the value-risk bar is met.
- **Medium:** Web Billing has no FPX — students without cards can't buy on web; FPX passes are the fallback.
- **Medium:** Korea — Ask Bubu, saves, collections and the lunch call barely work on Kakao-only areas.
- **Medium–High:** Pro sold on web while Sponsored shows in-app — App Review could cite 3.1.3(g); no Pro mention in the iOS app, portal link by email only, web-only Sponsored as fallback.
- **Medium:** Plus UI blocked on crest assets (no v2 vector, no small mark).
- **Low:** Bubu name leaks before billing if free Ask Bubu launches first — decision 11.
- **Medium:** Low MYR price + free Ask Bubu — free-tier AI cost can approach Plus revenue at scale; watch the cost page.
- **Medium:** Manually overridden KRW/USD prices don't follow FX — review yearly.
- **High:** Google Places spend from assistant — local-only `find_places`; tune is the only Places-touching tool.
- **High:** Google Places spend from the free app — no hard cap exists today (alert email only); hard daily cap is a Phase P prerequisite.
- **Medium:** Lunch call reaches few users until the push opt-in chain is fixed.
- **Medium:** Billing gate read from biased retention — use `segment=registered`.
- **High:** Referral farming — Apple/Google sign-in requirement, hashed deleted identities, monthly cap, first-pick-after-attribution.
- **Medium:** App Review on referral grants — fallback to offer codes ready.
- **Medium:** Haiku 5.5 is days old — eval before launch, env switch back to 4.5.
- **Medium:** Webhook lag/outage — sync endpoint + 5 retries + local entitlement cache.
- **Medium (later):** "Sponsored" leaking into Brain ranking, Ask Bubu or nudges kills recommendation trust.
- **Low:** RevenueCat fee (1% above $2.5k MTR).

## When to turn it on

Charging before retention is solid kills growth. Use **Admin → Retention** (d30 by weekly cohort, from `app_sessions`). Flip `BILLING_ENABLED` when d30 is healthy and stable for several cohorts, a power-user group exists, and Places/AI cost per heavy user shows on the cost page.

**Read the registered segment.** `PruneStaleGuests` hard-deletes guests after 90 idle days, so in any cohort older than ~13 weeks the guests who churned have vanished from the denominator and the all-users d30 reads too high. Gate on `segment=registered`, which isn't affected. To make the all-users view honest too, keep a small tombstone (id, created_at, is_guest) for each pruned guest and count it in cohorts. Referrals and free Ask Bubu can launch before billing — they grow the user base and measure real Plus usage.

## Audit changelog

Changes from the previous draft, with reasons:

| Change | Why |
|---|---|
| **2026-10-12 audit** | |
| Web and Android billing section; RevenueCat Web Billing for the web app, Play Billing later, iOS never links out | Web app is live and shares `auth/me`; Web Billing supports MYR/KRW but cards/Apple Pay/Google Pay only; Play requires Play Billing in a TWA; Korea's alt-payment entitlement needs a separate binary at 26% |
| Lunch call reframed as "the reason", moved last on the paywall | `NudgePickFinder` + `NudgeCopyCatalog` already put an open place in free nudges |
| Do not default the priming toggle on | Priming screen is built around App Review 5.1.1(iv); pre-checked opt-in is a review risk |
| Nudges reach web too | Web Push counts as a device in `MealNudgeEligibility` |
| Korea section; KR/US pricing rationale rewritten | Kakao picks are live-only, never `restaurants` rows (no saves, no Brain, no owner); KR/US users are Malaysian/Muslim students abroad |
| `absorbGuest` → RevenueCat `logIn` after every sign-in | Signing in from a guest session to an existing account changes the user id |
| Pro: free owner tools (4a) then paid Sponsored (4b), web-only via Web Billing; Max proposed dropped, Pro includes Plus | Claim flow + counters already exist; 3.1.3(b)/(f)/(g); delivery/POS price anchors |
| "Featured" → "Sponsored" | FTC disclosure expectations; Malaysia CPA 1999 ss. 9–10 |
| Conversion 1.4% → 2.1% global median, plan 1.5–2% | RevenueCat 2026 benchmark (no SEA breakout found) |
| Design approach section; Phase A (assets) added | Light-only app, radius drift, purple-as-membership rule, paywall spike criteria, crest blocks UI |
| Guest token limits 20/min, 300/day | `config/auth.php` changed since last audit |
| Phase P: backfill/rebuild/pin test marked done; `SearchChoiceService` removed from decide list | Commands and test exist; that service only records taste events |
| Bubu reveal timing flagged | Free Ask Bubu before billing would leak the name |
| Success bar 2% → 1.5% of MAU paying | Bar sat at the top of the forecast range, so an on-forecast launch would be killed |
| Halal removed from fact chips; ambassador offer codes moved to Phase 1; subscriber referral reward waits for the ledger; web pack minimum noted | Review pass 3 contradictions |
| **2026-10-10 audit** | |
| Brain judged before/after on accept rate (regret as guardrail), proposed min 300 decisions per version; switch-over runbook added | `enabled()` is one global switch, so "v2 beats v1 per cohort" can't be measured; backfill + rebuild were missing |
| Taste events recorded with the Brain off; v1 ranking pinned by a test | Profiles were empty on Brain day; nothing enforced "byte-identical v1" |
| Hard daily Places cap + radius cap added to Phase P | Only an alert email existed; free Nearby/Decide spend was unbounded |
| Lunch call blocked on the push opt-in fix; push/nudge opt-in rates added to measurement | Nudges are opt-in, priming defaults off, denied users silently skipped, permanent stop after 3 pauses |
| Billing gate reads `segment=registered` retention | 90-day guest hard-delete inflates older all-user cohorts |
| `ct=share` / `ct=web_<from>` attribution added | Share and website installs looked organic |
| Default model → Haiku 5.5 | 10× cheaper than 4.5 ($0.10/$0.50 vs $1/$5 per 1M); eval first |
| Allowances raised (150/mo Plus; free later cut to 3/wk, see below) | LLM cost per turn ~$0.0008 on Haiku 5.5 |
| `find_places` local-only | Places dominates cost: cache-miss decision up to $0.245; area cache is 8 h, not 24 h |
| `tune` flagged as Places-touching | `present()` → `enrichWinner` → Place Details + Atmosphere |
| Prerequisite phase added | Decision pipeline lives in the controller; Brain is OFF in production by default |
| Auth check → `decision.user_id` | Decision token is a per-request secret header, not a reusable ownership check |
| `parse_craving` tool removed | `IntentParser` returns one food concept, not constraints |
| `my_taste` → `SeleraTraits::describe` | `TasteMemory` is a static reducer |
| `save_place` needs `installation_id` | `RestaurantSave` requires it |
| Token logging → `assistant_messages` + `ApiCostReport` | `ApiUsageDaily` stores call counts only |
| Add Haiku 5.5 to `admin_budgets` | Unlisted models priced at $1/$5 default |
| Allowance counting via message count | `DailyAiBudget` is global per-day, not per-user |
| iOS reads entitlements from `auth/me` | SDK `customerInfo` can't see local referral/admin grants |
| Smarter filters removed; certified-halal filter made free | Late night already free; RM15 not expressible; never paywall halal |
| Rooms presets removed | Rooms not built |
| RevenueCat API v2, queued webhook, TRANSFER both sides | RevenueCat docs: 60 s timeout, 5 retries, TRANSFER only to destination |
| Small Business Program, IAP-only rail | 15% from day one; no link-out entitlement for Malaysia; 3.1.3(c) excludes consumer sales |
| Referral anti-abuse rewritten | Apple/Google subs are unique (device check was a no-op), email unverified, hard delete frees identity, guest rows predate referrals |
| `/i/*` AASA + deep link + `makanapa://` | AASA covers only `/p/*`, `/g/*` |
| PHPUnit, not Pest | Tests are PHPUnit classes |
| Revenue reality check added | ~1.4% freemium conversion in SEA |
| PDPA notes added | Chat logs are personal data; 72 h breach notification |
| Plus launch set: lunch call + collections | Reuse nudges, Brain and saves; Plus was too thin with Ask Bubu alone |
| US raised to $5.99 / $49.99 / founding $3.99; packs $1.99 / $5.99 | User asked; now in line with Korea (₩7,900 ≈ $5.89) instead of below it |
| Prices → RM 7.90 / ₩7,900 / $3.99, MY base storefront | User wants RM 6–7.90 and KRW ~7–8k; Korean prices conventionally end in 900 |
| Net revenue now subtracts SST/VAT | Apple calculates proceeds on the tax-exclusive price |
| Free Ask Bubu 5 → 3 turns/week | Lower price shrinks revenue headroom |
| Added Selling Plus section | Paywall placements, trial, yearly, founding price, ambassador codes, Customer Center, grace period, Family Sharing, funnel + kill criteria, compliance |
| Pack ladder 20/50/100/200 + custom 10–100 | User wants flexible amounts; Apple IAP needs fixed products, so custom uses a 10-message unit × purchase quantity (max 10) |
| Plus crest (purple v2) + "Why Bubu?" page + fact chips | Crest art delivered; users need proof Ask Bubu beats free general AI chatbots |
| Bubu message packs added | User wants paid top-ups when the allowance runs out; consumable IAP + server ledger; never expire per 3.1.1 |
| Decisions 1–7 answered | Prices, launch scope, free Ask Bubu, paywall, referral identity (both), 60-day founding window, Bubu final |
| Mascot renamed Nasi → Bubu | "Nasi" is generic and clashes with dish names; Bubu risks documented |
