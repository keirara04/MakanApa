"""60s score for the MakanApa "what's new" tour, rendered by ../../audio/score_kit.py.

Event times are the same numbers the compositions animate on.

    python3 audio/make_score.py   ->  assets/audio/score.wav
"""

import os
import sys

here = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(here, "..", "..", "audio"))
import score_kit  # noqa: E402

# One level per 3 s bar. Community (bars 5-9) sits back so the reading moments breathe.
LEVELS = [
    "intro", "calm", "full", "lift", "lift",          # hook, phone, shuffle, pick
    "calm", "calm", "calm", "calm", "calm",           # community
    "full", "lift", "full",                           # ambassador, crest
    "full", "full", "full", "lift",                   # guides
    "lift", "full", "end",                            # ending
]

EVENTS = [
    ("note", 0.3, 659.25), ("note", 1.5, 554.37),
    ("reveal", 3.0),
    *[("tap", t) for t in [4.5, 12.75, 16.6, 17.3, 19.0, 19.5, 20.75, 21.25, 23.1, 24.45,
                           31.875, 38.95, 40.55, 42.75, 44.35, 46.6, 48.4]],
    *[("swish", t) for t in [5.25, 8.6, 9.0, 14.0, 17.4, 19.6, 21.35, 23.2, 25.5, 29.0, 32.0,
                             35.25, 39.05, 42.85, 46.7, 50.2]],
    ("thud", 6.0), ("pop", 6.75), ("fan", 7.5), ("flips", 8.25), ("riffle", 9.3),
    ("impact", 10.5),
    *[("tick", t) for t in [11.5, 11.75, 12.0, 47.3, 47.55, 47.8]],
    ("chime", 12.75),
    ("soft", 24.6), ("soft", 27.25),
    ("crest", 34.2),
    ("reveal", 51.0),
    ("logo", 54.75),
]

score_kit.render(LEVELS, EVENTS, os.path.join(here, "..", "assets", "audio", "score.wav"), fade_at=59.2)
