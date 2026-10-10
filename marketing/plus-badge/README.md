# MakanApa Plus badge

- `makanapa-plus-badge.svg`: self-contained vector with outlined lettering; no fonts or embedded bitmaps required.
- `makanapa-plus-badge-2048.png`: transparent high-resolution export.
- `makanapa-plus-badge-512.png`: transparent app-size export.
- `preview.jpg`: cream presentation background, not a transparent asset.

Design: gold membership medallion, sambal-red PLUS ribbon, existing Celebrate Bubu vector mascot. Separate from the university ambassador crest. The source app and existing assets were not modified.

Rebuild: `DYLD_FALLBACK_LIBRARY_PATH=/opt/homebrew/lib python3 marketing/plus-badge/build_badge.py` (CairoSVG, Pillow and fontTools; macOS Arial Rounded Bold font).

## v2 (current)

- `makanapa-plus-purple-v2.png`: 1254x1254 transparent raster. Purple cap Bubu, gold laurels, MakanApa banner, PLUS ribbon. Prompt in `purple-v2-prompt.txt`. Needs a vector master and a small no-lettering variant before shipping (see SUBSCRIPTIONS_PLAN.md, Plus crest).
