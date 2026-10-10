"""90s score for the "This is MakanApa" product film, rendered by ../../audio/score_kit.py.

Event times are the same numbers the compositions animate on.

    python3 audio/make_score.py   ->  assets/audio/score.wav
"""

import os
import sys

here = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(here, "..", "..", "audio"))
import score_kit  # noqa: E402

# One level per 3 s bar.
LEVELS = [
    "intro", "calm",                                  # 0-6    question, reveal
    "full", "full", "full", "full", "full",           # 6-21   pick for me, craving
    "calm", "full", "lift", "lift",                   # 21-33  saved shuffle
    "full", "full", "full",                           # 33-42  nearby
    "calm", "calm", "calm",                           # 42-51  halal
    "calm", "calm", "calm", "calm",                   # 51-63  community
    "full", "full", "lift", "lift",                   # 63-75  ambassador, crest
    "full", "lift",                                   # 75-81  collage
    "full", "calm", "end",                            # 81-90  end card
]

EVENTS = [
    ("note", 0.25, 659.25), ("note", 1.1, 554.37),
    ("reveal", 2.6),
    *[("tap", t) for t in [5.75, 12.45, 13.45, 14.8, 15.3, 16.0, 16.4, 17.05, 17.5, 22.5, 30.75,
                           33.0, 35.0, 37.6, 42.65, 44.1, 52.6, 53.3, 55.0, 55.5, 56.75, 57.25,
                           59.1, 60.45, 67.875]],
    *[("swish", t) for t in [13.6, 15.42, 16.52, 23.25, 26.6, 27.0, 35.1, 44.2, 53.4, 55.6, 57.35,
                             59.2, 61.5, 65.0, 68.0, 71.25, 74.0]],
    ("soft", 7.4), ("soft", 18.45), ("tick", 37.62),
    ("thud", 24.0), ("pop", 24.75), ("fan", 25.5), ("flips", 26.25), ("riffle", 27.3),
    ("impact", 28.5),
    *[("tick", t) for t in [29.5, 29.75, 30.0]],
    ("chime", 30.75),
    ("soft", 60.6), ("soft", 63.25),
    ("crest", 70.2),
    ("logo", 81.0),
]

score_kit.render(LEVELS, EVENTS, os.path.join(here, "..", "assets", "audio", "score.wav"), fade_at=88.8)
