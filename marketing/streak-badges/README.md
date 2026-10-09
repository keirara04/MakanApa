# MakanApa streak badge collection

12 milestones: 1, 10, 30, 60, 90, 120, 180, 270, 365, 500, 730, and 1000 days.

The root SVG files are portable, entirely vector artwork with outlined lettering and a transparent background. They have a 512 × 512 viewBox, no embedded bitmap images, and no external dependencies. Editable counterparts with live text and named groups are in `editable/`.

The collection is designed for consecutive-day streaks. The same day-count artwork can support total active days by changing the ribbon label in the editable source. This delivery contains artwork only; no app tracking or award logic was changed.

Suggested consecutive-day behavior: count at most once per local calendar day; consecutive dates increment the current streak; a missed day starts a new current streak at 1 on the next open. Earned milestone badges remain unlocked. Preserve the best streak independently of the current streak. Keep the streak timezone consistent across travel.

`milestones.json` maps each threshold to its artwork and display name. More milestones can follow the same layout: retain the rice mascot, day count, and ribbon, and progress the rim color and ornamentation. The 365-day-and-up tiers add a crown.
