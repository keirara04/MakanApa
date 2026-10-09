---
name: ios-design-bar
description: Design and polish bar for MakanApa's native SwiftUI screens — HIG fidelity, navigation semantics (push vs sheet vs fullScreenCover, one-way doors), anti-slop checks, motion rules, full state cycles, and a definition of done. Load when building, redesigning, or reviewing any iOS screen or flow in ios/MakanApa (onboarding, result, home, sheets, empty/error states, animations, illustrations).
license: MIT
metadata:
  adapted-from: appllama-app-design-skill v1.3.0 (github.com/Appllama/appllama-skills, MIT)
---

# MakanApa iOS design bar

Adapted from Appllama's app design skill. Their Expo/React Native code is
dropped; rules are translated to SwiftUI and to this project's own design
system. Pair with the `swiftui-pro` skill for API-level review.

## Project ground truth (use these, don't invent new ones)

| Concern | Source |
|---|---|
| Colors | `Core/DesignSystem/Palette.swift` — `sambalRed`, `nasiCream`, `kicap`, `pandan`, `kunyit` (asset catalog, light + dark) |
| Type | `Core/DesignSystem/Typography.swift` — `makanDisplay(_:)`, `makanBody(_:)` (Dynamic Type scaled) |
| Motion | `Core/DesignSystem/Motion.swift` — `quick`, `standard`, `playful` |
| Copy | `Core/DesignSystem/Copy.swift` — all user-facing strings |
| Components | `Core/DesignSystem/Components/` |

Before adding a color, font size, animation curve, or string: check these
files. A new token is a decision, not a convenience — add it to the file, not
inline.

**Verification:** build with `xcodebuild` only. Never boot or drive the
simulator — the user runs it. Hand the user the visual checklist at the end
instead of claiming visual verification.

## Native fidelity laws

Violating any of these is a finding, not a style preference.

1. **Both themes, day one.** Every color resolves through the asset catalog
   or semantic system colors (`Color(.label)`, `.secondarySystemBackground`).
   No hard-coded hex in views. A screen isn't done until light and dark both
   hold.
2. **Native controls over rebuilt ones.** `Toggle`, `Slider`, `Picker`
   (`.segmented` for 2–5 options), `DatePicker`, `Menu`, `.contextMenu`,
   `ShareLink`, `PhotosPicker`, `.searchable`, `.refreshable`,
   `.confirmationDialog` for destructive confirms. Rebuild only when the
   design truly diverges — then match iOS timing and haptics.
3. **SF Symbols for UI icons.** One icon family per screen. Filled variant
   for the active tab. Mascot/illustration SVGs are content, not chrome.
4. **Typography is hierarchy.** One display size per screen.
   `.monospacedDigit()` for anything that counts, prices (RM), distances,
   timers. `.textSelection(.enabled)` on data users may copy (addresses).
5. **Continuous corners.** `RoundedRectangle(cornerRadius:style: .continuous)`
   everywhere.
6. **One elevation system.** Shadows signal elevation, not decoration.
7. **Spacing rhythm.** One base unit (4 or 8). Prefer stack `spacing:` over
   stacked padding. No rogue 13pt gaps.
8. **Safe areas are design.** Check content scrolled under the Dynamic
   Island/status bar, bottom CTAs clearing the home indicator
   (`.safeAreaInset(edge: .bottom)` for pinned CTAs).
9. **Titles belong to the navigator.** `.navigationTitle` + large-title
   collapse over hand-rolled headers where possible.
10. **Haptics are punctuation.** `.sensoryFeedback` on the same frame as the
    visual: `.selection` when a value steps, `.impact(weight: .light)` on a
    snap, `.success`/`.error` for outcomes. One per action, never on scroll,
    never the only feedback.
11. **Format like a product.** `RM 12`, `1.2 km`, `38k`. Trim trailing zeros.
    Use `FormatStyle`, localized.
12. **Tap targets ≥ 44pt.** Contrast passes in both themes.

## Navigation laws

Every transition answers: what is the destination to here, must the user
come back, and what does back / edge swipe do afterwards.

1. **Push goes deeper, replace moves on.** `NavigationStack` push
   (`NavigationLink(value:)` / path append) when the user will want to
   return. When coming back would land in a stale state, reset the path or
   swap the root view instead of pushing. Back undoes *navigation*, never
   *events*.
2. **Presentation is meaning.**
   - Multi-step self-contained task → `.sheet` with its own
     `NavigationStack` and Cancel/Done.
   - Short interruption (filters, picker, item options) → `.sheet` with
     `.presentationDetents([.medium, .large])`, drag-to-dismiss.
   - Immersive content → `.fullScreenCover` with an explicit Close.
   - Destructive confirm → `.confirmationDialog`.
   - Item actions → `.contextMenu`.
   - Share / web / photos → system controllers (`ShareLink`,
     `SFSafariViewController`, `PhotosPicker`), never a rebuilt route.
   - A sheet that grows a second step was a modal all along.
3. **One-way doors leave the stack.** Finished onboarding (Skip included),
   sign-in, purchase, completed session: switch the root by state so back
   can never re-enter it. But keep the user's place — sign-in demanded by one
   action is a sheet that completes the action where it was tapped.
4. **Back is blocked in exactly two cases:** an irreversible request in
   flight (visible progress, seconds), and unsaved work in a sheet
   (`.interactiveDismissDisabled` + ask first). Transient in-screen state
   (expanded search, selection mode) consumes the first back. Anything else
   trapping back is a defect. Never break the interactive pop gesture.
5. **Tabs are peers.** No slide between tabs, each tab keeps its own stack,
   re-tapping the active tab pops to root. Full-attention screens live above
   the tabs. Cold start lands by state — never a login/onboarding flash
   before Home.

## Anti-slop laws

AI-built apps share a look. Each is a default ban; override only when the
brand asks for it AND you can say why it fits MakanApa.

1. **No AI-default styling.** Purple/indigo glow gradients, glassmorphism on
   every card, mesh-gradient heroes, confetti for minor events, sparkles in
   headings.
2. **One accent, locked.** The accent is spent where the money is (primary
   CTA, active state, progress). Neutrals carry the screen. No new hue in
   screen seven.
3. **One grey family.** Warm or cool, never both.
4. **Shape lock.** State the radius rule once (e.g. "CTAs are capsules,
   cards 20, chips capsule, inputs 12") and never violate it.
5. **Voice and emoji.** All copy is plain English — no Manglish or Malay
   slang (dish names and the brand stay). Emoji are allowed **in copy**
   (`Copy.swift`) sparingly, one per message max. Never as icons, buttons,
   or chrome. UI icons are SF Symbols.
6. **One label per intent.** Pick one phrasing per action and reuse it from
   `Copy.swift` everywhere it appears.
7. **Emphasis stays in the family.** Emphasize with weight of the same
   typeface; don't inject a different family for flair.
8. **Ship full state cycles.** Skeletons match the final layout's shape,
   empty states say how to fill them, errors are inline and specific.
   Loading, empty, error, rate-limited, location-denied — all designed.
9. **Mechanical pre-flight** before handing a flow to the user:
   - distinct accent hues = 1
   - every corner radius from the stated scale
   - emoji in UI chrome = 0
   - gradients without a brand reason = 0
   - hard-coded colors / font sizes / strings in views = 0
   - duplicate labels for one intent = 0

   A failed count is a fix, not a judgment call.

## Motion laws

Decide in this order:

1. **Frequency gate.** Seen 100+ times a day (tab switch, keyboard, scroll,
   back) → system default, nothing added. Tens a day (press, row select) →
   near-imperceptible, < 150 ms. Occasional (sheets, toasts, result reveal) →
   standard motion. Delight only on rare moments (first result, a "settled"
   pick). Deleting an animation is often the best move.
2. **Name the purpose in one word** — feedback, spatial continuity, state
   change, preventing a jarring cut, explanation, delight — or don't build
   it. Text the user is reading never moves for style.
3. **Finger involved → spring, seeded with velocity.** In `DragGesture`
   `.onEnded`, use `value.velocity` / `predictedEndTranslation` to pick the
   target so a flick commits. Distance OR velocity thresholds. Interruptible:
   a new drag grabs the current value, not the destination.
4. **Otherwise timing, < 300 ms, strong ease-out.** Never ease-in on an
   entrance. Exits ~0.7× entrance duration and leave the way they came. Enter
   from `scale(0.95)` + fade, never `scale(0)`.
5. **Press feedback on press-in**, 100–150 ms: scale 0.97 on buttons/cards
   (custom `ButtonStyle` reading `configuration.isPressed`), highlight (not
   scale) on list rows, opacity on bar buttons.
6. **One vocabulary.** Use `Motion.quick` / `.standard` / `.playful` from
   `Motion.swift`. `playful` (bouncy) only for rare delight moments.
7. **Stagger** list entrances by index (~40 ms), cap at ~8 items.
8. **Continuity.** `matchedGeometryEffect` / `.navigationTransition(.zoom)`
   (iOS 18) when a card becomes a detail; match corner radius and aspect so
   the eye reads one object.
9. **Animate transform and opacity**, not frame/padding, in hot paths.
10. **Reduce Motion.** Read `@Environment(\.accessibilityReduceMotion)`;
    spatial motion collapses to cross-fades. Native transitions stay the
    system's.

## Perceived performance

- Skeletons (`.redacted(reason: .placeholder)`) only for content whose shape
  you know; never a full-screen spinner for a partial update.
- `LazyVStack` / `List` for growing lists, stable `id`s.
- Optimistic by default: taps reflect instantly, reconcile, roll back loudly.
- Kick off the next screen's fetch on tap, not on appear.
- Right-size images; placeholder while loading; no layout jump when they
  arrive.

## Illustration assets

See [references/image-assets.md](references/image-assets.md). MakanApa
already has a mascot + illustration SVG set at the repo root — new art must
match that style family.

## Definition of done, per screen

- [ ] Can name the reference pattern adopted (from real apps, not imagination)
- [ ] Navigation answered: push / sheet / fullScreenCover / root swap; what
      back does; one-way doors can't be re-entered
- [ ] Uses only Palette / Typography / Motion / Copy tokens
- [ ] Loading, empty, error, long-content states designed
- [ ] Anti-slop pre-flight counts pass
- [ ] Reduce Motion fallback present; haptics single and synced
- [ ] Dynamic Type XL considered (layout wraps, nothing clipped)
- [ ] Tap targets ≥ 44pt
- [ ] `xcodebuild` build passes

Then give the user this **simulator checklist** to run (do not run it
yourself):

- Light + dark mode
- Content scrolled under Dynamic Island; bottom CTA clears home indicator
- Dynamic Type at XL
- Long text / empty / error states forced
- Screen-record the flow (`xcrun simctl io booted recordVideo flow.mov`):
  every transition, back path + edge swipe, sheet present/drag/cancel
  mid-drag, keyboard up and down, rapid taps
- Scrub the recording for one-frame flashes, wrong-theme frames, layout
  jumps, springs overshooting into content
