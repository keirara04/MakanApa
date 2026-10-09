# Illustration assets — generation pipeline

Adapted from appllama-app-design-skill (MIT). App-quality artwork is
generated, curated, and post-processed — never "one prompt, ship it".

## 0. Match what already exists

MakanApa has an established style family:

- `MakanApa_Mascot_SVG_Set/` — Nasi mascot poses (Default, Celebrate, Geng,
  Location) + `MakanApa_Nasi_Mascot.svg`
- `MakanApa_Illustration_SVG_Set/` — Budget, Distance, Mood, Geng, Solo,
  Location illustrations

Open several of these before generating anything. New art must read as the
same commissioned set: same line weight, palette, shading, proportions.
Prefer SVG (vector) to stay consistent with the existing set.

## 1. Style system (write it down once)

- **Style family**: lifted from the existing SVGs. ONE family.
- **Palette**: hexes from the asset catalog behind `Palette.swift`
  (SambalRed, NasiCream, Kicap, Pandan, Kunyit), including the exact surface
  color the asset sits on.
- **Lighting/texture**: same words in every prompt.
- **Subject grammar**: mascot? food objects? people (in what rendering)?

Every prompt = style system + subject.

## 2. Generate

Use the best image tool available (e.g. Higgsfield MCP if connected).

- Highest quality, largest size, then downscale. ≥ 3× rendered size. Never
  upscale.
- 3–4 candidates per asset; if the winner drifted, regenerate the rest to
  match it.
- Sets (icons, repeated elements): generate as one sheet, then slice — same
  lighting and palette guaranteed.
- Backgrounds: exact surface hex or true transparency. Inspect edges at 400%
  — halos or matte fringe mean regenerate or remove background.

## 3. Prompt pattern

```
[subject], [style family] illustration, [palette words + hexes],
[lighting], solid background #XXXXXX, centered composition,
generous negative space, app illustration, no text, no watermark
```

- Always `no text` — models bake in gibberish labels.
- Empty states: quiet subject (resting mascot, soft scene), not a busy hero.
- Success/celebration: motion implied by composition, not speed lines.
- Onboarding heroes: leave a third empty for the headline; say so in the
  prompt.

## 4. Post-process

1. Trim to content + consistent padding.
2. Vector: add to asset catalog with "Preserve Vector Data". Raster: @2x/@3x
   PNG, or WebP/HEIC for photographic art.
3. Check both themes — art made on a dark ground often halos on light. Make
   theme variants in the asset catalog or keep art on theme-invariant
   surfaces.
4. Size budget: < 200 KB per screen-level asset.

## 5. Quality gate — reject if any

- Style drifts from the existing set (lighting, palette, line weight)
- Halo/fringe at 400% zoom
- Baked-in text or watermark artifacts
- Composition fights the layout (focal point under a button, cropped by
  safe areas)
- Reads as clip-art / default model style with no art direction
