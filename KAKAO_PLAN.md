# Kakao Local search for Korea — plan

Status: **phases 1–2 built 2026-10-11 (uncommitted).** Kakao rows are opt-in per client (`kakao=1` on `/places/search`): builds 1.2 (17) and older decode `provenance` strictly and would fail on `kakao`, so they keep getting the Google fallback. Directions use Kakao's web link `map.kakao.com/link/to/name,lat,lng` (no app scheme, no Info.plist change). AI use of Kakao results: permitted (user decision 2026-10-11). The storage rule still applies.

**Decide Kakao pick (option b), built 2026-10-11 (uncommitted).**
- **When:** a solo request sends `kakao=true` from Korea, and fewer than `KAKAO_DECIDE_MIN_POOL` (default 5) MakanApa places qualify, or none match the craving. Decide then picks live from Kakao: keyword search for a craving, category `FD6` otherwise.
- **Pick:** random from Kakao's nearest page; no Brain scoring.
- **Response:** `recommendation: null, kakaoPick: {…}`.
- **Storage:** `decisions.kakao_pick` json holds `{place_id, url, shown_ids, query, accepted_at}` and nothing else.
- **Reroll:** re-runs the search and skips the places already shown.
- **Not available:** tune, what-if, choose (all 404) and why-not (422).
- **Recent:** keeps only the link, labelled "A place on Kakao Map".
- **Falls back to the normal pick when** Kakao fails or returns nothing.

Goal: Malaysians in Korea (Korea Univ ambassadors first) get useful search results. In Korea, Kakao Local takes the place of the Google fallback in Nearby search. MakanApa adds its own layer on top: halal info, English labels and community finds.

## 1. Facts this plan rests on

- **Kakao Local API:**
  - keyword search and category search (`FD6` = restaurants)
  - `x`/`y`/`radius` (up to 20 km), `sort=distance`
  - 15 results per page, 45 per query
  - free quota: 100k calls a day per endpoint; after that, 2 KRW per call
  - the free quota applies only to the first app with Kakao Map turned on for the account
- **Storage rule (Kakao staff answer on its forum, Sep 2026):**
  - we may store only the Kakao place `id` and `place_url`
  - we may not store name, address or coordinates, not even temporarily; live calls only
  - Kakao has no lookup-by-id API
- **Third-party map:** showing results on our Google map is allowed.
- **AI use:** Kakao's position is unknown. Kakao data stays out of the LLM until Kakao answers in writing.
- **Live probes, 5 km radius, `sort=distance`:**

  | query | Korea Univ | Itaewon | notes |
  |---|---|---|---|
  | `halal` | 17 | 43 | |
  | `할랄` | 5 | 10 | |
  | `무슬림` | 0 | 2 | useless |
  | `kebab` | 19 | 41 | English matches |
  | `indian` | 10 | 9 | English matches |
  | `mcdonalds` | 23 | 19 | English matches |
  | `nasi lemak` | 0 | 0 | |
  | `malaysian` | 0 | 1 | the embassy |
  | `나시고랭` | 37 | 56 | mostly Vietnamese/Asian places |
  | `FD6`, 1 km around Korea Univ | 992 total | | only 45 can be paged |

  - Kakao indexes some English keywords, and `halal` does *better* than `할랄`.
  - Malay dish names return nothing.
  - Malaysian food is scarce in Korea. That gap is where community finds matter.

## 2. Phase 1: backend, Korea fallback in search (≈1 day)

1. **Config:** `config/services.php` gets `'kakao' => ['rest_key' => env('KAKAO_REST_KEY')]`.
   - It has its own gate, separate from `PLACES_PROVIDER`.
   - No key means no call, so fixture mode and tests never go live.
2. **`KakaoLocalClient`:** one class with `keyword()` and `category()`, built on `Http::withHeaders(['Authorization' => 'KakaoAK …'])->timeout(4)`.
   - Always sends `sort=distance` plus `radius`.
3. **Korea gate:** use Kakao when lat is 33.0–38.7 and lng is 124.5–131.0. The box is rough and also catches bits of the sea and North Korea, which is harmless.
4. **`PlacesService::searchPlaces()`:** inside Korea, the fallback branch calls Kakao instead of Google, with the same "thin local results" trigger and the same `try/catch` that falls back to local only and logs a `Log::warning`.
5. **Query fan-out:** send the raw query **and** its Korean form, then merge by Kakao `id`.
   - A static dictionary (PHP array) covers about 40 terms, e.g. nasi goreng → 나시고랭, chicken rice → 치킨라이스, halal → 할랄, mamak/indian → 인도음식, kebab → 케밥, malaysian/indonesian → 말레이시아/인도네시아, korean bbq → 고기구이, ramen → 라멘, bibimbap → 비빔밥.
   - Terms the dictionary doesn't know go out raw only. `SearchMiss` already records misses, so we can grow the list from real data.
   - At most 2 Kakao calls per search.
6. **New provenance `kakao`:**
   - Results carry `kakaoPlaceId`, `kakaoUrl` (`place_url`), `distanceKm`, the Korean name and address as-is, and `cuisine` as an English label taken from `category_name` (static map: 한식 → Korean, 일식 → Japanese, 중식 → Chinese, 양식 → Western, 분식 → Korean street food, 치킨 → Fried chicken, 인도음식 → Indian, 튀르키예음식 → Turkish, 베트남음식 → Vietnamese, …).
   - Kakao's `x` is **longitude** and `y` is latitude. `PlaceSearchController::present()` reads `latitude`/`longitude` with no fallback, so these must be set.
   - `rating`, `openStatus`, `priceLevel` and `id` are null or `unknown`.
   - `halal` is always `unknown`, unless MakanApa's own data says otherwise (phase 3).
7. **Ranking:** `rankSearchResults`/`matchTier` assume `rating`, `open_status` and name matching.
   - Kakao rows go in a separate block *after* MakanApa rows, sorted by Kakao's distance.
   - They don't go through the comparator, so there are no null-crashes and no Korean names sinking below English ones.
   - Branch grouping is skipped for Kakao rows.
8. **Halal filter:** when the user has halal on, Kakao rows whose `category_name` path shows alcohol or pork are dropped:
   - alcohol: `술집` (bars), `호프`, `요리주점`
   - pork: `삼겹살`, `족발,보쌈`, `돼지`, `순대`, `감자탕`, `곱창`
   - This only *hides* likely-bad places. It never labels anything halal.
   - This matches `HalalEligibility::filter`, which drops only `non_halal` and lets `unknown` through. Kakao rows show as "halal status unknown", the same as unverified local rows.
9. **Meta and throttle:**
   - `meta.source` gains `kakao` / `mixed`.
   - Inside Korea, `includeGoogle=true` (the clients' "search wider" button) forces the Kakao path instead of Google.
   - `googleAvailable` keeps its meaning of "a fallback is available", so the button still works there. The clients need no change for this.
   - `/places/search` already has its own throttle (`90,1,place-search`). Fan-out of 2 calls keeps us far under 100k/day.
   - A Kakao 429 or 5xx degrades to local results only.
10. **Tests:** feature tests with `Http::fake`, covering the Korea gate, the Google path unchanged outside Korea, fan-out merging, the halal filter, and degrading on 429. No live calls.

## 3. Phase 2: clients (≈1 day)

- **Tap action (web and iOS):** web `SearchPanel.tsx:86` and iOS `NearbyViewModel.swift:461` only handle `google_fallback`. Add a `kakao` branch that does **not** call `/places/resolve`.
  - It opens a light sheet built only from the live row: name, English cuisine, address, distance.
  - Buttons: **Open in Kakao Map** (`kakaoUrl`), **Directions** (public transport), **Add to MakanApa**.
- **Kakao results UI:**
  - A small "from Kakao Map" label on these rows (attribution; check Kakao's logo guidelines).
  - Kakao results show as map pins for the session only and are never saved locally.
- **Directions:**
  - Web: `https://m.map.kakao.com/scheme/route?ep=lat,lng&by=publictransit`.
  - iOS: `kakaomap://route?ep=lat,lng&by=publictransit`, with the web URL as fallback.
  - **Verify both on a device first.** These come from community sources and one forum report says `by` is ignored.
  - iOS needs `kakaomap` in `LSApplicationQueriesSchemes`; check the built plist afterwards.
- **Kakao Map as a maps choice:** add it next to Naver in `PreferredMapsLauncher.swift` and `web/src/lib/maps.ts`.
- **Add to MakanApa:** opens the existing community add-place flow with **no prefill** from Kakao. The user types the details and drops the pin, so it is their content.
  - We may store the Kakao `id` on the submission as a link.
- **No save/favourite on Kakao rows** in phase 2, because we can't store the name and can't look it up by id. The sheet says "Add to MakanApa to save it" instead.
- iOS: `xcodebuild build` only. New files go in via the xcodeproj gem.

## 4. Phase 3: MakanApa's own layer on Kakao IDs (≈2 days)

Kakao `id` is storable, so MakanApa data can be keyed to it:

1. **`kakao_place_signals` table:** `kakao_place_id`, halal reports/votes, "Malaysians liked this" count, `added_restaurant_id` (once someone adds it via community).
   - Holds no Kakao fields, only our own data.
2. **Search joins on the Kakao ids it got back**, so a live Kakao row can show:
   - a "Community: Muslim-friendly (3 reports)" badge;
   - a jump to the canonical restaurant once someone has added it.
   - This is the main payoff. Kakao covers where places are; MakanApa says which ones are safe for Muslim users.
3. **Halal starter set:** *find a current source first.* No KTO dataset turned up on data.go.kr; the counts found were from 2017, plus a blog figure of about 15 KMF-certified places. Places to check: english.visitkorea.or.kr, kmfhalal.org, the MUFKO app. The set itself: about 15 KMF-certified places plus the KTO Muslim-friendly list, entered by hand as canonical `restaurants` rows from public sources (our data, not Kakao's). Tag them with the KTO tier (certified / self-certified / Muslim-friendly / pork-free). The lower two may serve alcohol, so label them honestly.
4. **Nearby browse in Korea:** when local rows are thin, fill with `FD6` category search.
   - It caps at 45 per point, so use 2–3 small tiles or cuisine-keyword fan-out.
   - Rows are live-only and use the same halal filter.

## 5. Ask Kakao (DevTalk, in writing). AI use (#2) is cleared by the user; the storage questions are still open

Send before phase 3 ships:
1. May we keep the place **name** next to the stored `id` so a user's favourites list is readable?
2. May Kakao Local results be passed to an LLM to rank or explain recommendations (no training, nothing stored)?
3. May our UI show our own data (halal reports) on live Kakao results?

Until "yes" to #2, Kakao rows stay out of Decide, Makan Brain and the assistant. Those keep using `localRestaurantsNear()`.

## 6. Ops

- Add `KAKAO_REST_KEY` to api-dev (152.42.217.68), which is production. A missing key means silent local-only results, not an error.
- Watch the Kakao console under Statistics → Quota. At 2 calls per search, 100k/day is about 50k Korea searches a day.
- UI copy is English only. Korean place names and addresses are data and stay as they are.

## 7. Open questions for the user

- Show Kakao rows to all users in Korea, or only when local results are thin (the current Google rule)? Plan default: thin-only, same as Google.
- Kakao rows for every user in Korea, or only users with the halal preference on? Plan default: everyone. Non-Muslim users get Korean food coverage; halal users get the same rows with alcohol and pork places filtered out.
- Are Korean names enough, or do we want romanisation later? Plan default: Korean only, plus the English cuisine label.
