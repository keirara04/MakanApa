"""30s score for the MakanApa "what's new" film, rendered by ../../audio/score_kit.py.

Event times are the same numbers the compositions animate on.

    python3 audio/make_score.py   ->  assets/audio/score.wav
"""

import os
import sys

here = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(here, "..", "..", "audio"))
import score_kit  # noqa: E402

# One level per 3 s bar: hook, phone, box + shuffle, pick, ambassador, crest, guides, ending.
LEVELS = ["intro", "calm", "full", "lift", "lift", "full", "lift", "full", "full", "end"]

EVENTS = [
    ("note", 0.3, 659.25), ("note", 1.5, 554.37),
    ("reveal", 3.0),
    *[("tap", t) for t in [4.5, 12.75, 16.875, 24.375]],
    *[("swish", t) for t in [5.25, 8.6, 9.0, 14.0, 17.0, 23.0, 24.5, 20.25]],
    ("thud", 6.0), ("pop", 6.75), ("fan", 7.5), ("flips", 8.25), ("riffle", 9.3),
    ("impact", 10.5),
    *[("tick", t) for t in [11.5, 11.75, 12.0]],
    ("chime", 12.75),
    ("crest", 19.2),
    ("logo", 27.0),
]

score_kit.render(LEVELS, EVENTS, os.path.join(here, "..", "assets", "audio", "score.wav"), fade_at=29.2)
