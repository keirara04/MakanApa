# MakanApa web app (Android PWA) plan

Status: **confirmed 2026-10-10; Phase 0 and 1 in progress on branch `webapp`.**

Goal: a complete MakanApa for Android users, as an installable web app (PWA), built with React + Vite + Tailwind. Full parity with the iOS app, not a demo. Reachable from the landing page, with sign-in and push notifications. Later wrapped for Google Play.

## 1. What "complete" means

Everything the iOS app does for a normal user, except the in-app admin screens (the Filament admin panel already covers those). Feature inventory from the iOS source (132 Swift files, ~21k lines in Features):

| Area | iOS screens | In the PWA |
|---|---|---|
| Onboarding | 3 pages, silent guest account, taste prompt | Yes |
| Sign-in | Email, Google, Apple, guest, link account, upgrade guest | Yes, plus **password reset** (new, server work) |
| Decide | Home, quick pick, craving / budget / distance flow, lenses, context strip, recent picks, guide | Yes |
| Result | Photo, reasons, deciding factor, what-if, tune, why-not, another one, not this, let's go, send to friends, vibe follow-up | Yes (Brain parts appear only when the server sends them, same as iOS) |
| Saved-places pick | Shuffle and reveal | Yes, simpler animation |
| Nearby | Map, clustering, search this area, filters, area panel, search with Google fallback, place sheet, gallery, save, don't suggest, quick-add photo, directions, pick from map | Yes |
| Community | Feed, community switcher, trending, new in area, ambassador picks, posts board, threads, composer with place tag, reactions, report, block, delete | Yes |
| Contributions | Add place wizard (new / edit / closure / reopen, pin picker, up to 5 photos), My places | Yes |
| Halal | Halal section, report with certificate photos, owner claim, history ledger | Yes |
| Ambassadors | Apply, manage picks, share card, welcome screen | Yes |
| Settings | Profile, edit profile, Selera, saved places, notification settings, hide non-halal, maps app choice, blocked people, legal, replay intro, support, log out, delete account | Yes |
| Notifications | Priming sheet, 6 preference toggles, tap routing | Yes, via Web Push |
| Admin | Users, moderation, halal queue, requests | **No.** Use Filament |

Not in the PWA: home-screen widgets, Live Activities, App Store billing (none exist on iOS today either).

## 2. Decisions to make before building

| # | Decision | Recommendation |
|---|---|---|
| 1 | Where it lives | `app.trymakanapa.com`, a separate static site. The Laravel app appends `Permissions-Policy: camera=(), microphone=(), geolocation=()` to every response (`SecurityHeaders`, global middleware), so a PWA served from the Laravel origin couldn't get the location. |
| 2 | Repo location | New top-level `web/` folder in this repo, next to `ios/` and `api/`. |
| 3 | Stack | React 19, TypeScript, Vite, Tailwind v4, React Router, TanStack Query, `vite-plugin-pwa` (Workbox). No UI kit: components built on the brand tokens. New web packages: `react`, `react-router`, `@tanstack/react-query`, `vite-plugin-pwa`, `@vis.gl/react-google-maps`, `@googlemaps/markerclusterer`. |
| 4 | Maps | Google Maps JavaScript API with a new web key restricted to the app's domain, via `@vis.gl/react-google-maps` and `@googlemaps/markerclusterer`. Same map as iOS, and the API already returns Google place data. Watch cost (see Risks). |
| 5 | Sign-in methods | **Decided: Google and email only** (plus guest). No Sign in with Apple on the web app. |
| 6 | Copy | English only, and no mascot name in any user-facing text until Plus launches. Port strings from `Copy.swift` so both apps say the same thing. |
| 7 | Production API | The iOS app still points at `api-dev`. Production must exist before the PWA is public. |

## 3. Server work (Laravel)

Nothing on this list exists yet. Each item gets tests.

1. **Web Push**
   - New table `web_push_subscriptions` (installation id, user id nullable, endpoint, p256dh, auth, user agent, last used).
   - Public `POST web-push/subscriptions` (like `device-tokens`, works before login), `DELETE web-push/subscriptions`, plus claim / unclaim on login and logout (reuse the device-token claim endpoints or mirror them).
   - Add the `laravel-notification-channels/webpush` package (needs approval, it's a new dependency) and VAPID keys in env.
   - Every notification that goes to APNs also goes to Web Push, with the same `type` and ids in the payload, and the same preference checks and quiet hours (`config/nudges.php`).
   - Expired subscriptions (HTTP 404/410 from the push service) are deleted.
2. ~~Sign in with Apple from the web~~: **dropped** (decision 5). Apple accounts made on iPhone can't sign in on the web app; they can add a password later if that becomes a need.
3. **Password reset**: no flow exists in the app or API. Add request-reset and reset endpoints using Laravel's broker, an email template, and a reset page on the web app. Needs a working mail sender in production.
4. **Guest creation limit**: `auth/guest` allows 5 per minute and 30 per day per IP (`AppServiceProvider`, limiter `guest`). A campus Wi-Fi shares one IP, so a busy campus would hit it in an hour. Raise it, or key it by installation id plus IP.
5. **CORS**: add `config/cors.php` limiting origins to the web app's domains (today any origin is allowed) and set `max_age` so preflights are cached. `X-Decision-Token` must be an allowed header.
6. **Platform tagging**: send `platform: web` with `auth/guest`, `app-sessions/start`, recommendations and sign-ups (`signupSource: web`), so iOS and web can be compared in Filament.
7. **Shared place links**: `/p/{place}` pages get an "Open in the web app" action for Android and desktop visitors. Later, `/.well-known/assetlinks.json` for the Google Play version.
8. **Landing page** (see section 6).

## 4. Web app architecture

```
web/
  src/
    app/          router, providers, error boundary, install prompt, update toast
    api/          fetch client, one module per area, TypeScript types ported from Endpoints.swift
    auth/         session store, guest bootstrap, Google / Apple / email, link account, reset
    features/     decide, result, saved-pick, nearby, community, posts, add-place, halal,
                  ambassador, settings, selera, notifications, onboarding
    ui/           design-system components (see section 5)
    lib/          location, photos (resize), share, maps launcher, storage, haptics
  public/         manifest icons, mascot art (exported from marketing/mascot-refresh/)
  sw.ts           service worker: app shell cache, push, notification click routing
```

- **API client**: one `fetch` wrapper that adds `Authorization: Bearer`, `X-Decision-Token` on decision calls, `Accept: application/json`. Handles the same responses as iOS: 401 clears the session and returns to sign-in, 403 `account_required` opens the sign-up prompt, 403 `terms_required` opens the agreement, 403 `account_suspended` shows the suspended screen, 429 reads `Retry-After`, other 4xx show the server's `message`.
- **Types**: hand-ported from `Endpoints.swift` (1,640 lines). A small contract test per area hits the dev API and checks the shape, so the two clients don't drift silently.
- **Session**: token in `localStorage` (the 90-day Sanctum token), cached user, `GET auth/me` on start. Guest account created on first open exactly like iOS. A strict Content-Security-Policy keeps script injection out, since a stored token is only as safe as the page.
- **Local state** that iOS keeps on the device: saved snapshot, don't-suggest list, recent 5 picks, halal flag, ignored context keys, maps app choice, onboarding / priming / taste-prompt flags, guest-upgrade counters, pending vibe prompt, last budget and distance, installation id. All in `localStorage` under one versioned key.
- **Location**: `navigator.geolocation` one-shot, about 100 m accuracy, refreshed when the app comes back to the foreground. Clear "location is off" state with how to turn it on in Chrome.
- **Photos**: `<input type="file" accept="image/*">` (Android offers camera or gallery), resized in a canvas to 1600 px and JPEG 0.85 before upload, same as iOS. Multipart `photo` + `photoType`.
- **Directions**: Google Maps by default. **Decided:** Google Maps, Waze, and Naver Map (for Korea) in settings.
- **Sharing**: Web Share API with files for the ambassador card (rendered to PNG in a canvas), `wa.me` links for sending a place to friends, copy-link fallback.
- **Push**: permission asked only from the priming sheet after the first accepted pick (same moment as iOS), never on load. Notification taps open the right screen by `type`: meal nudge to the place or a quick pick, replies and reactions to the thread, submission decided to My places, release and account to Decide. Also route the types iOS ignores (halal and ambassador decisions) to the relevant screen.
- **Offline**: app shell cached, last result and saved list readable offline, a plain offline banner, actions disabled with a reason. No offline queueing of writes.
- **Install**: custom "Add to home screen" card using `beforeinstallprompt`, shown after the second accepted pick, never on first open.
- **Analytics**: `app-sessions/start` and `/end` on `visibilitychange`.

## 5. Design direction

The brand is already set: the iOS colours, the sketchbook marketing site and the refreshed mascot. The web app should feel like the iOS app first (it is the same product), with the website's hand-drawn details as its accent.

**Colour** (from the iOS asset catalog, so both apps match):

| Name | Hex | Use |
|---|---|---|
| Nasi cream | `#FFF4DD` | Page background |
| Surface | `#FFFBF3` | Cards, sheets |
| Kicap | `#29201D` | Text, outlines |
| Sambal | `#E94B35` | Primary actions, the pick |
| Pandan | `#4F7A57` | Halal certified, success |
| Kunyit | `#F4B942` | Highlights, tape, stars |

Secondary text `#7A6F63`, hairline `#ECDFC6`. Light mode only, like iOS. These iOS values are canonical. The website uses slightly different ones (cream `#fdf6ec`, paper `#f4efe6`, sambal `#e8461f`); reconcile the website to these when the web app ships, so the three surfaces match. Check every text pair for 4.5:1 contrast (sambal on cream fails for small text; use kicap for body text and sambal for large type and buttons with white text).

**Type**: SF Rounded doesn't exist on Android. Use **Fredoka** (already self-hosted for the website) for display and buttons, **Instrument Sans** for body (also on the website), and **Caveat** only for short handwritten notes, the way the website uses it. Scale: 15/17/20/24/32/44 px, body line height 1.5.

**Layout**:

```
Phone (most users)                 Wide screen (desktop, tablets)
+------------------------+         +-----------+------------------------+
| top bar: place  [ava]  |         |  sketch   |  same phone layout,    |
|                        |         |  margin   |  max 480 px wide,      |
|   screen content       |         |  with     |  centred; Nearby uses  |
|                        |         |  doodles  |  map left, list right  |
| [Decide][Nearby][Comm] |         |           |                        |
+------------------------+         +-----------+------------------------+
```

- Bottom tab bar with the same three tabs as iOS. Settings from the avatar, as a full-screen sheet.
- Left-aligned text everywhere except the pick reveal and empty states, which are centred.
- Bottom sheets for place details, filters and confirmations, as on iOS.

**Principles**:
- **One memorable moment: the pick.** After the person taps Pick, the plate-and-spoon spin from the website hero plays once while the API answers, then the result card is taped onto the page. It answers a tap, so it fits the motion rule; with reduced motion it's a plain fade. The mascot itself stays still. Everything else stays quiet and fast.
- Hand-drawn details only where they carry meaning: a marker circle around the chosen option, tape on the result card, the wobbly double outline on the result and ambassador card. Plain clean cards elsewhere.
- The mascot never moves (static poses from `marketing/mascot-refresh/`: wave, thinking, excited, sad). Motion answers taps only: press feedback, sheet open, the pick reveal. Respect reduced motion.
- No all-caps labels, no middle-dot meta strings where a list or separate lines read better, no arrows appended to button text.
- Empty and error states say what happened and what to do next, in plain English.

Review against generic defaults: the cream and red palette is close to a common generated look, but it is the brand's existing identity on iOS and the website, so it stays. What makes this one specific is the Malaysian food vocabulary (dish tiles, halal status, mamak, campus communities), the sketchbook marks borrowed from the site, and the pick reveal.

The build itself uses the frontend-design skill, with screenshots at 360 px and 412 px widths (common Android sizes) for each screen.

## 6. Reaching it from the landing page

- **Android visitors**: every "Get the app" button (nav, hero, final call to action, FAQ links) becomes **"Open MakanApa"** and goes to the web app. Detection on the server from the user agent in the `download-button` component, plus a client-side check as a backup.
- **Everyone else**: the App Store button stays, with a plain "Or use it in your browser" link under the hero button.
- **Tracking**: new `/go/web-app?from=…` redirect counted in `marketing_events`, like `/go/app-store`.
- **FAQ**: "Is there an Android version?" answers yes, how to open it, and how to add it to the home screen.
- **Shared place pages** `/p/…`: "Open in MakanApa" goes to the place in the web app for Android and desktop visitors.
- **Ambassador page**: the same Android swap, since the ambassador campuses (UiTM Jasin, UniMAP) are mostly Android.

## 7. Phases

| Phase | What | Estimate |
|---|---|---|
| 0 | Decisions in section 2; production API; server: CORS, guest limit, platform tag | 2–3 days |
| 1 | Foundation: `web/` scaffold, tokens and fonts, UI components, API client and types, guest session, email and Google sign-in, onboarding, location, PWA manifest and service worker shell, deploy to a staging subdomain | 1 week |
| 2 | Decide: home, quick pick, craving / budget / distance, lenses, context strip, result with all Brain parts, another one, not this, let's go, send to friends, recent picks, vibe follow-up, taste prompt, saved-places pick | 1.5 weeks |
| 3 | Nearby: map, clustering, filters, search with Google fallback, area panel, place sheet, gallery, save and don't suggest, quick-add photo, pick from map | 1.5 weeks |
| 4 | Account: link account, guest upgrade, password reset (server), edit profile, Selera, saved places, blocked people, legal, terms agreement, delete account, log out | 1 week |
| 5 | Push: server Web Push, subscription flow, priming sheet, preferences, tap routing, nudge events | 1 week |
| 6 | Community: feed, switcher and requests, posts, threads, composer, reactions, report, block; add place wizard with pin picker and photos; My places; halal report, owner claim, history; ambassador apply, picks, share card, welcome | 2.5 weeks |
| 7 | Landing page entry (section 6), install card, offline states, performance and accessibility pass, Lighthouse PWA checks | 3–4 days |
| 8 | Google Play: Trusted Web Activity with Bubblewrap, `assetlinks.json`, store listing | 3–4 days |

Total: roughly **9–10 weeks** of focused work. Each phase ends with a deploy to staging and a test on a real Android phone (Chrome). The landing page only starts sending people there after Phase 7, so earlier phases can ship to staging without risk.

## 8. Risks

| Risk | Level | Mitigation |
|---|---|---|
| Two clients to maintain alone | High | Contract tests per API area; iOS and web share copy decisions and API changes in the same PR |
| Google Maps web cost | Medium | Load the map only on the Nearby tab; static previews elsewhere; reuse map instance; budget alert in Google Cloud |
| Places API cost from a new client | Medium | Same server caching applies; the shared `places` rate limit stays per user |
| Campus Wi-Fi hitting the guest limit | High until fixed | Server item 4 before launch |
| Token in `localStorage` | Medium | Strict CSP, no third-party scripts beyond Google and Apple sign-in and Maps |
| Web Push reliability on Android (battery savers, Chrome not open) | Medium | Mealtime nudges are nice-to-have; the app works without push |
| iPhone users landing on the web app | Low | iPhone visitors keep the App Store button; web push on iOS needs install, not a target |
| Plus billing later | Medium | App Store payments don't exist on the web. Plus on Android needs RevenueCat Web Billing (Stripe), or Play Billing once it is on Google Play. Add to SUBSCRIPTIONS_PLAN.md when Plus is confirmed |
| Production API not provisioned | High | Phase 0 blocker |

## 9. Owner decisions (2026-10-10)

1. Address: `app.trymakanapa.com`.
2. Sign-in: Google and email (plus guest). No Apple.
3. Directions: Google Maps, Waze, Naver Map.
4. Start Phase 0 and 1 now, on branch `webapp`.
