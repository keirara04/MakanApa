# MakanApa Plus — Subscription, Assistant & Referral Plan

Status: **plan only — nothing started.** Everything ships dark behind `BILLING_ENABLED` / `ASSISTANT_ENABLED` / `REFERRALS_ENABLED` (default OFF in production, same pattern as `config/brain.php`) until retention numbers justify charging.

Last audit: 2026-10-10 — codebase claims verified file-by-file, external facts checked against RevenueCat, Apple, OpenRouter and Anthropic docs. See [Audit changelog](#audit-changelog) at the end.

## Principles

- The main food picker stays free. Never paywall "decide what to eat".
- **Never paywall halal information or halal filters.** It's a dietary/religious need and the brand's trust anchor.
- **Never take away something that's free today** (e.g. Late night mode, Open now chip).
- Sell the outcome: **"Find food that fits your cravings, budget, and taste."**
- Community participation and ambassador status stay **earned**. Plus gets its own cosmetic badge; the ambassador crest is never sold.
- English-only copy rule — no "makan" in tier or feature copy (brand name MakanApa, dish names and the mascot name Bubu are fine).

## Reality check: expected revenue

Freemium download-to-paid in Southeast Asia is roughly **1.4%** (RevenueCat State of Subscription Apps 2026, via secondary source — directional only). At the standard RM 7.90/month, net per subscriber ≈ RM 7.90 ÷ 1.08 (SST, which Apple nets out before proceeds) × 0.85 (Small Business Program) ≈ **RM 6.22**:

| Monthly active users | Subscribers (~1.4%) | Gross/month | Net (after SST + 15%) |
|---|---|---|---|
| 1,000 | ~14 | ~RM 110 | ~RM 87 |
| 10,000 | ~140 | ~RM 1,106 | ~RM 871 (~$213) |

Founding-member subscribers (RM 5.90) pull this lower.

Plus is a **sustainability lever** (cover AI + Places costs of heavy users), not a business on its own at current scale. Size engineering effort accordingly. The restaurant side (Pro) has higher revenue per account.

## Tiers

| Tier | For | When | Entitlement |
|---|---|---|---|
| **Free** | Everyone | Launch | Core picker, community, earned badges, all halal features |
| **Plus** | Consumers | Launch | `plus` |
| **Pro** | Verified restaurant owners | Later | `pro` — sold only in an owner section, never on the consumer paywall |
| **Max** | Owner who also wants Plus | After Pro exists | `plus` + `pro`, shown only to owners |

Launch is **Free + Plus only**. Max on a consumer paywall reads as "a better Plus" — it only appears once Pro exists, and only to owners.

## Core design: entitlements, not tiers

- Code checks **entitlements** (`plus`, `pro`), never tier or product names. Max later is a product granting both — zero extra gating code.
- **One source of truth for both server and iOS: our `user_entitlements` table**, exposed on `GET auth/me` as `entitlements: [{ name, expiresAt, source }]`.
  - Paid access arrives from RevenueCat; free grants (admin comps, referral rewards) are written locally.
  - iOS UI reads `auth/me`, not the RevenueCat SDK's `customerInfo` — otherwise referral/admin grants would be invisible to the app. The SDK is used only to purchase and restore.
- Server enforces every gate; iOS only shows/hides UI.
- `users.id` is stable across guest upgrade (`AuthController::upgradeGuest` updates the same row) — safe to use as the RevenueCat `app_user_id`.

## Plus — what it sells

Ask Bubu is the anchor; the launch set adds two cheap features that reuse existing systems.

On the paywall, in order:
1. **Ask Bubu** — personal AI assistant that knows your cravings, budget and taste.
2. **Bubu's lunch call** — at your usual mealtime, a pick is already waiting with a one-line reason ("Rainy, you liked Thai last week, here's one 400 m away"). Builds on existing meal nudges (`MealNudge*`, `MealNudgeDispatcher`) + `MakanBrain::decide` over `localRestaurantsNear()` — no Places calls. **Free nudges stay exactly as they are**; Plus adds the ready-made pick and reason.
3. **Named collections** — organise saves into lists ("Date spots", "Near campus"). Today `RestaurantSave` is a flat list keyed on `installation_id`/`user_id`; collections are a new `collections` table + pivot. Existing saves stay free and unlimited.
4. **Your taste, explained** — deeper Selera insights (builds on `SeleraTraits::describe`) + a monthly taste recap.
5. **Plus crest** — cosmetic, separate from earned badges and the ambassador crest (see [Plus crest](#plus-crest)).

Later candidates (not in launch scope):
- **Home screen widget** "Bubu's pick for lunch" — no WidgetKit target exists yet (Medium). Free gets a generic widget, Plus the personal pick.
- Ask Bubu planning modes ("plan lunches for my week near campus").
- Precise price filter ("under RM15") — `RestaurantMenuItem` has a `price` column but menu data is near-empty; revisit once menu coverage exists.

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

**Asset work before shipping**
- The PNG is 1.7 MB: export iOS asset-catalog sizes (@1x/@2x/@3x for each display size) instead of shipping the master.
- "MakanApa" and "PLUS" text becomes unreadable below ~48 pt. Make a **small variant** for inline use (purple shield + Bubu head, no lettering) for name-adjacent spots.
- Prefer a vector (SVG/PDF) master like the v1 `makanapa-plus-badge.svg` so sizes stay crisp; v2 is currently raster only.
- Accessibility label: "MakanApa Plus member".

**Rules**
- Shown only while the `plus` entitlement is active (including referral/admin grants). It disappears on expiry; no "former member" badge.
- Ambassadors who also have Plus show the ambassador crest first; the Plus mark is secondary.

## Billing: RevenueCat

**Why RevenueCat:** solo dev, iOS-only, Laravel backend. Renewal/grace/refund/restore/transfer edge cases are the hard part and RevenueCat solves them; there's no official Apple server library for PHP. Product → multiple entitlements (Max later) is native. Free up to **$2,500 monthly tracked revenue**, then 1%. Low lock-in — subscriptions belong to Apple.

**Payment rail:** external-purchase-link entitlement is not confirmed for the Malaysian storefront (plan on IAP), and 3.1.3(c) excludes consumer/single-user sales, so **all in-app subscriptions use IAP** (including Pro later, unless Pro is sold only outside the app with no in-app call to action).

**Commission:** enrol in the **App Store Small Business Program** → 15% from day one (proceeds ≤ $1M/yr). Apple nets out Malaysian SST before proceeds.

**RevenueCat setup (launch)**
- Entitlement: `plus` (add `pro` when Pro ships).
- Products: `plus_monthly`, `plus_yearly` (one App Store subscription group).
- Restore behavior: **Transfer to new App User ID** (RevenueCat default/recommended).
- iOS: configure RevenueCat with `appUserID: String(user.id)` **only after the user ID is known** (after `auth/guest` or sign-in returns), never at app launch — configuring early mints a `$RCAnonymousID`, and a custom ID that already has an anonymous alias won't merge cleanly. Guests get configured too (ID is stable on upgrade); account switches use `logIn`. Purchase itself still requires a registered account (`registered` middleware on sync; paywall sends guests to sign-up first).

**API**
- `user_entitlements` table: `user_id`, `entitlement`, `expires_at`, `source` (`revenuecat` | `admin` | `referral`), `reference` (e.g. referral id). Effective access = any row with `expires_at` in the future. Keeps working if RevenueCat is down.
- `POST /webhooks/revenuecat`:
  - Verify the `Authorization` header (shared secret set in RevenueCat dashboard).
  - Return 200 fast and process on a queued job — RevenueCat times out at 60s and retries 5× (5/10/20/40/80 min) then stops.
  - Idempotent on event `id` (delivery is at-least-once).
  - On every event, refetch the customer from the RevenueCat REST API and sync `source=revenuecat` rows — don't write per-event logic. Use **API v2** (`GET /projects/{project_id}/customers/{customer_id}`, `gives_access`) for new work; needs a v2 secret key.
  - `TRANSFER` is sent only to the destination user — resync both `transferred_from` and `transferred_to`.
  - `BILLING_ISSUE` ≠ expiry (grace period may apply); `EXPIRATION` = remove access.
- `POST /me/billing/sync` (`registered`): client calls after purchase/restore; server refetches immediately so access unlocks without waiting for the webhook.
- Admin grant/revoke (Filament action, `source=admin`): test every gate with zero payments; support comps.
- Account deletion: inside `UserAccountDeletionService::delete`, before `forceDelete`, call RevenueCat delete-customer (async on their side; treat 200/404 as success). Show a "cancel your subscription in iPhone Settings" notice in the delete flow — deleting the account doesn't stop Apple billing, and a later restore can re-create the customer.
- Config: `REVENUECAT_SECRET_KEY`, `REVENUECAT_PROJECT_ID`, `REVENUECAT_WEBHOOK_AUTH` in `.env` → `config/billing.php`.

**iOS**
- Add `purchases-ios` (≥ 5.27.1 for Paywalls) via Xcode → Add Package, same as GoogleSignIn/GoogleMaps were added. **Never regenerate with XcodeGen** — `ios/project.yml` is stale and would wipe packages and entitlements.
- Paywall: start with RevenueCat Paywalls (iOS 15+, remotely configurable); swap to a custom SwiftUI sheet if it fails the ios-design-bar.
  - **Never use the "present if needed" variant** — it gates on the SDK's `customerInfo` and would show the paywall to users with a referral/admin grant. Decide whether to present from `auth/me`, then present unconditionally.
  - Post-purchase sequence: purchase succeeds → `POST /me/billing/sync` → refresh `auth/me` → dismiss. The RevenueCat success callback alone unlocks nothing.
- Settings: a Plus card after `yourMakanApaCard` in `SettingsView`, showing status/expiry from `auth/me`, Restore, and Manage subscription.

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
- Before overriding Korea or the US, confirm the app is actually **available in those storefronts** (App Store Connect → Availability). The app is English-only and restaurant data is Malaysian, so for non-MY users Plus mostly sells to visitors/students in Malaysia.

## Selling Plus

**Paywall placements** (where it may appear)
- Ask Bubu allowance used up.
- Teaser card in Selera insights ("See what your taste says about you").
- Plus card in Settings (after `yourMakanApaCard`).
- Tapping a locked collection or lunch-call setting.
- **Never** mid-pick, on first launch, or before the user's first accepted pick.

**Offers**
- **Free trial:** 1 week via Apple introductory offer (once per subscription group per Apple ID). Users who already had referral Plus see "Continue with Plus" copy instead of a trial pitch.
- **Yearly plan** shown as the default option (~2 months free).
- **Founding-member price:** launch at the founding price (RM 5.90 / ₩5,900 / $3.99) for a launch window (e.g. 60 days), then raise to standard for new subscribers and choose **"keep current price for existing subscribers"** in App Store Connect. No extra product or code — Apple handles price preservation. (A fixed "first 500" cap isn't enforceable this way; use a time window.)

**Ambassadors and universities**
- Each university ambassador gets a **custom offer code** (e.g. `UTM1MONTH`, one free month of Plus). Custom codes allow up to 25,000 redemptions per batch, max 6-month expiry, one redemption per customer per offer. Gives growth + per-university attribution (pairs with `ambassador_university_id`).
- **Active ambassadors get Plus free** while they hold the role (`source=admin` grant, revoked when the role ends). Makes the role more attractive without selling the crest.

**Keeping subscribers**
- **RevenueCat Customer Center** (in-app manage subscription): cancellation survey + a retention offer (e.g. 50% off for 3 months via Apple promotional offer) before cancel.
- **Apple win-back offers** for lapsed subscribers (in-app sheet iOS 18+).
- **Billing Grace Period ON** in App Store Connect — failed renewals keep access while Apple retries.
- **Family Sharing OFF** at launch — it can be turned on later but **not off** once enabled.

**Measurement and kill criteria**
- Funnel events: `paywall_view` (with `placement`), `purchase_start`, `purchase_success`, `purchase_cancel`, `trial_start`, `trial_convert`, `subscription_cancel`. RevenueCat charts cover revenue/churn; our events cover *where* people convert. Store in a small `billing_events` table (or reuse `MarketingEvent` only if it gains `user_id`).
- Admin page: conversion by placement, trial → paid rate, Ask Bubu cost per Plus user, free-tier Ask Bubu cost.
- **Success bar after 60 days of billing:** ≥ 2% of MAU paying, Ask Bubu + Places cost per Plus user < 30% of net revenue, **no drop** in free-user d30 retention. Miss it → turn Plus features free and add a tip jar instead.

**Compliance checklist**
- Paywall shows price, billing period, trial terms, auto-renew notice, and links to Terms and Privacy Policy (Guideline 3.1.2); same links in App Store metadata.
- Terms of Service: subscription section (billing, renewal, cancellation via Apple, refunds via Apple).
- Privacy Policy: AI chat processing by a third-party model provider (OpenRouter / Anthropic), retention 30 days.
- Billing support email on the paywall and in Settings.
- Restore purchases always reachable.

## Ask Bubu — personal AI assistant

Chat where users ask e.g. "spicy, cheap, near campus, open now", answered through the Brain and existing API services as tools. Named after the mascot, **Bubu** (see [Mascot name](#mascot-name-bubu)).

**How it differs from what's free today:** the `craving` field on `recommendations/solo` already does free natural-language search, but `JudgmentIntentParser` returns **one food concept only**. Ask Bubu's value is multi-constraint requests (food + budget + distance + time + mood), follow-ups ("something less oily"), and taste-aware explanations. The paywall copy must make that difference obvious.

**Rule inherited from `config/judgment.php`:** AI informs the system, never becomes it. Never changes halal status, hides content, or deletes anything.

**Prerequisites (do first)**
- **Brain must be ON in production.** `BRAIN_ENABLED` defaults OFF in production and stays off until the Filament **System → Makan Brain** page shows v2 beating v1 per cohort; with it off, `decide` is skipped and tune/what-if return 404. Ask Bubu is therefore blocked on the Brain rollout, not just on code. The Brain itself stays deterministic and LLM-free — the LLM only calls it as a tool.
- When extracting the service, keep the v1 path byte-identical with the switch off (existing rollout rule).
- **Extract the decision pipeline into a service.** All of it lives in `RecommendationController::solo()` / `soloWithBrain()`; there's nothing to call from the assistant. Extracting a `SoloDecisionService` also cleans up the controller.
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

1. **Proof in every answer (main lever).** Each place card Bubu returns carries short fact chips taken from our data, never from the model: *Open until 11 pm* · *400 m away* · *Halal certified* · *Fits your budget* · *You liked Thai last week*. Built from `ReasonComposer` / `ReasonCatalog` (the Brain's existing reason system) and `ContextEngine`, so the reasons are real and auditable.
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
- Allowances exist mainly to cap abuse and Places spend, not LLM spend.

**Allowances (starting values, tune from measured usage)**
- Plus: **150 turns/month**, shown in-app ("112 of 150 left this month").
- Referral 7-day Plus: **30 turns**.
- Free: **3 turns/week**, registered users only — enough to feel the value.
  - Worst case at scale: 10,000 free MAU × ~13 turns/month × $0.0008 ≈ **$104/month**, vs ~RM 871 (~$213) net Plus revenue at 10k MAU. The free tier scales with MAU and is **not** covered by revenue — watch it on the cost page; cut to 1/week or drop it if needed.
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

- Example: 70 messages = 7 × 10 = RM 6.30 / ₩4,900 / $3.43. The picker always shows when a fixed pack is cheaper ("100 messages for RM 4.90 is better value").
- How it works: one consumable product `bubu_messages_10`, bought with StoreKit's **purchase quantity** option (Apple allows quantity 1–10 per purchase for consumables). Ledger grant = 10 × purchased quantity.
- **Verify before building:** that RevenueCat's purchase API passes quantity through and reports it in the transaction/webhook. If it doesn't, drop the custom picker and keep the fixed ladder; users can still stack packs (50 + 20 = 70).
- Custom is priced at the small-pack rate on purpose, so the fixed packs stay the better deal.

Other storefronts auto-equalize from MYR. Exact values must match Apple price points.

- **Who can buy:** everyone registered. When a **free** user runs out, the sheet shows **Plus first** (150/month + lunch call + collections for the same RM 7.90/month), packs second. When a **Plus** user runs out, it shows packs only.
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
- `assistant_conversations` / `assistant_messages` migrations, dated after `2026_10_09_124348`.
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
- A local or RevenueCat grant does **not** postpone their Apple bill. Give them **+50 Bubu messages** (written to `assistant_credit_ledger`, `reason=referral`) instead. Never promise an automatic extension.

**Grant mechanism & App Review**
- Launch with our own server-side grant (`source=referral`): no payment method, no auto-renew, no renewal terms to disclose.
- Policy nuance: no guideline explicitly covers free in-app referral grants; 3.1.1 bans non-IAP mechanisms that unlock paid features, which a reviewer could stretch to cover it. Apple's documented route for member referral programs is **subscription offer codes** (up to 1M codes/quarter; eligibility per offer: new, existing, expired; one per customer per offer). If review pushes back, switch rewards to offer codes — which also gives paying subscribers a real free period.

## Mascot name: Bubu

The rice-ball mascot (red "LET'S EAT" cap, spoon) gets a proper name: **Bubu**, a nod to *bubur* (rice porridge). It replaces "Nasi", which is a generic word that clashes with dish names already in the app ("Nasi lemak", "Nasi kandar") and can't be owned as a brand.

**Not public until Plus launches (decided 2026-10-10).** App and website copy don't name the mascot until then ("Let MakanApa pick", "Thinking..."). Plus launch reveals the name with Ask Bubu: swap those strings back to Bubu in the same release.

**Why it works:** two syllables, a repeated sound that's easy to remember, easy to say in Malay, Chinese, Tamil and English, and warm in tone. Works in "Ask Bubu" and "Bubu picked this for you".

**Risks found in checks (2026-10-10):**
- **Malay meaning:** *bubu* is the traditional fish trap, a one-way funnel that fish enter but can't leave ([Wikipedia](https://en.wikipedia.org/wiki/Bubu_(fish_trap))). Most Malaysians will know it. Harmless on its own, but next to a paywall it invites a "Bubu = trap" joke. Keep the paywall honest (clear price, easy cancel) and consider leaning into it playfully in brand copy ("the only bubu you'll want to be caught in").
- **Existing character IP:** "Bubu & Dudu" (一二布布) are popular Chinese sticker/plush characters (a bear and a panda) with merchandise across Southeast Asia. A different design, but merch and trademark overlap is possible.
- **Same niche:** a web product called "Bubu AI" meal planner exists (bubuaimealplanner.com). There's also a "BuBu Bubble Tea" loyalty app on the App Store. Neither is a direct clone, but App Store search for "Bubu" will be shared.
- **English:** sounds like "boo-boo" (a small mistake or injury). Minor, mostly a joke risk.

**Decision:** Bubu is final (2026-10-10). Trademark, App Store and user checks were skipped by choice; the overlaps above are accepted. Kepi is no longer a fallback.

**Scope of the rename:** copy and assets only. Rename in-app copy, the mascot SVG `<title>`/`<desc>` text, and marketing. Leave the `nasiCream` colour token, `MascotView` and asset file names alone; renaming code identifiers gains nothing.

## Pro (later)

Gated on a verified `RestaurantOwner` link, sold via IAP inside an owner section. No owner UI exists today — `RestaurantOwner` is only a halal-moderation verification link. Building owner UI is the bulk of Pro's cost.

- Owner-managed menu and photos (still goes through moderation).
- Analytics: views, saves, times picked.
- "Featured" placement in Nearby — **always labelled, never inside Brain ranking**.

Max follows Pro: one RevenueCat product attached to both entitlements, shown only to owners.

## Phases

| # | Phase | Size | Depends on |
|---|---|---|---|
| P | Prerequisites: Brain rollout passes the Makan Brain eval page and is ON in production; extract `SoloDecisionService`; move what-if/choose into services | Medium, ~2–3 days | — |
| 0 | `user_entitlements`, admin grant, `entitlements` on `auth/me`, iOS reads it; free certified-halal filter | Low, ~1–2 days | — |
| 1 | RevenueCat Plus: dashboard, Small Business Program, storefront prices, intro offer, grace period, webhook + queue, sync, deletion hook, iOS SDK, paywall + placements, Customer Center, Settings card, funnel events, Terms/Privacy updates | Medium–High, ~6–7 days | 0 |
| 1b | Plus features: lunch call (nudge + local Brain pick), named collections | Medium, ~4–5 days | P, 0 |
| 2b | Bubu message packs: consumable products, credit ledger, sync + webhook grants, refunds, out-of-messages sheet | Low–Medium, ~2–3 days | 1, 2 |
| 2 | Ask Bubu: engine, tools, allowances, cost reporting, model eval, privacy, iOS chat, fact chips, "Why Bubu?" page | High, ~2 weeks + 1–2 days | P, 0 (parallel with 1) |
| 3 | Referrals + email verification + ambassador offer codes + ambassador Plus grants | Medium, ~6–7 days | 0, 2 |
| 4 | Pro: owner section, analytics, featured placement | High, 1–2 weeks | 0, 1 |
| 5 | Max | Low, hours | 1, 4 |

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

8. **Pro surface:** decide after Plus is live.

## Risks

- **High:** Plus too thin — launch set is Ask Bubu + lunch call + collections; don't charge with Ask Bubu alone.
- **Medium:** Low MYR price + free Ask Bubu — free-tier AI cost can approach Plus revenue at scale; watch the cost page.
- **Medium:** Manually overridden KRW/USD prices don't follow FX — review yearly.
- **High:** Google Places spend from assistant — local-only `find_places`; tune is the only Places-touching tool.
- **High:** Referral farming — Apple/Google sign-in requirement, hashed deleted identities, monthly cap, first-pick-after-attribution.
- **Medium:** App Review on referral grants — fallback to offer codes ready.
- **Medium:** Haiku 5.5 is days old — eval before launch, env switch back to 4.5.
- **Medium:** Webhook lag/outage — sync endpoint + 5 retries + local entitlement cache.
- **Medium (later):** "Featured" leaking into Brain ranking kills recommendation trust.
- **Low:** RevenueCat fee (1% above $2.5k MTR).

## When to turn it on

Charging before retention is solid kills growth. Use **Admin → Retention** (d30 by weekly cohort, from `app_sessions`). Flip `BILLING_ENABLED` when d30 is healthy and stable for several cohorts, a power-user group exists, and Places/AI cost per heavy user shows on the cost page. Referrals and free Ask Bubu can launch before billing — they grow the user base and measure real Plus usage.

## Audit changelog

Changes from the previous draft, with reasons:

| Change | Why |
|---|---|
| Default model → Haiku 5.5 | 10× cheaper than 4.5 ($0.10/$0.50 vs $1/$5 per 1M); eval first |
| Allowances raised (150/mo Plus, 5/wk free) | LLM cost per turn ~$0.0008 on Haiku 5.5 |
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
