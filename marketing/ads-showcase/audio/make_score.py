"""Original 30s score + UI sound design for the MakanApa showcase film.

Synthesized from scratch (plucked-string arps, warm pads, a little piano, soft drums),
so it is ours to use anywhere. 80 BPM in A major: one beat = 0.75s, one bar = 3s.
Event times are the same numbers the composition animates on.

    python3 audio/make_score.py   ->  assets/audio/score.wav
"""

import os
import wave

import numpy as np

SR = 44100
DUR = 30.0
N = int(SR * DUR)
BEAT = 0.75
BAR = 3.0
rng = np.random.default_rng(80)

# ---------------------------------------------------------------- event map
TEXT_NOTES = [(0.3, 659.25), (1.3, 554.37), (2.25, 440.0)]
REVEAL = 3.0
SCREEN_ON = 5.25
BEAT_IN = 6.0
TAPS = [6.375, 7.5, 8.25, 9.75, 10.875, 12.75, 14.25]
PUSHES = [6.5, 8.4, 11.0, 14.4]
RESULT = 15.0
STATS = [21.0, 22.125, 23.25]
FINALE = 24.0
LOGO = 27.0

# Bars 0..9: Asus2 intro, then A E F#m D A E F#m D A.
CHORDS = {
    "A": ([220.0, 277.18, 329.63, 493.88], 55.0),
    "E": ([164.81, 207.65, 246.94, 369.99], 82.41),
    "F#m": ([185.0, 220.0, 277.18, 415.30], 92.50),
    "D": ([146.83, 185.0, 220.0, 329.63], 73.42),
    "Asus": ([220.0, 246.94, 329.63, 493.88], 55.0),
}
PROG = ["Asus", "A", "E", "F#m", "D", "A", "E", "F#m", "D", "A"]


# ---------------------------------------------------------------- helpers
def t_of(d):
    return np.arange(int(d * SR)) / SR


def mask(n, lo=None, hi=None, order=4):
    f = np.fft.rfftfreq(n, 1 / SR)
    m = np.ones_like(f)
    if lo:
        m *= 1 / np.sqrt(1 + (lo / np.maximum(f, 1e-6)) ** (2 * order))
    if hi:
        m *= 1 / np.sqrt(1 + (f / hi) ** (2 * order))
    return m


def filt(x, lo=None, hi=None, order=4):
    return np.fft.irfft(np.fft.rfft(x) * mask(len(x), lo, hi, order), len(x))


def noise(d):
    return rng.standard_normal(int(d * SR))


def norm(x):
    p = np.max(np.abs(x))
    return x / p if p > 0 else x


class Bus:
    def __init__(self):
        self.x = np.zeros((2, N))

    def add(self, sig, at, gain=1.0, pan=0.0):
        i = int(round(at * SR))
        if i >= N:
            return
        j = min(N, i + len(sig))
        s = sig[: j - i] * gain
        a = (pan + 1) * np.pi / 4
        self.x[0, i:j] += s * np.cos(a) * np.sqrt(2)
        self.x[1, i:j] += s * np.sin(a) * np.sqrt(2)

    def add_st(self, l, r, at, gain=1.0):
        i = int(round(at * SR))
        j = min(N, i + len(l))
        self.x[0, i:j] += l[: j - i] * gain
        self.x[1, i:j] += r[: j - i] * gain


# ---------------------------------------------------------------- instruments
def pluck(f, d=0.9, bright=0.6, decay=0.996):
    """Karplus-Strong plucked string, vectorized one period at a time."""
    p = max(2, int(round(SR / f)))
    n = int(d * SR)
    y = np.zeros(n + p + 1)
    y[: p] = filt(rng.uniform(-1, 1, p * 8), None, 1500 + 6000 * bright)[: p]
    for k in range(p, n + p, p):
        a = y[k - p : k]
        b = np.concatenate(([y[k - p - 1] if k - p - 1 >= 0 else 0.0], y[k - p : k - 1]))
        y[k : k + p] = (0.5 * (a + b) * decay)[: len(y[k : k + p])]
    out = y[p : p + n]
    out *= np.minimum(1, (d - t_of(d)[: len(out)]) / 0.05)
    return norm(out)


def piano(f, d=3.0):
    t = t_of(d)
    x = np.zeros_like(t)
    for k, a in enumerate([1.0, 0.45, 0.22, 0.12, 0.07, 0.04], start=1):
        fk = f * k * np.sqrt(1 + 0.0004 * k * k)
        x += a * np.sin(2 * np.pi * fk * t) * np.exp(-t / (2.2 / k**0.7))
    hammer = filt(noise(0.02), 800, 5000) * np.exp(-t_of(0.02) / 0.004)
    x[: len(hammer)] += 0.08 * hammer
    x *= np.minimum(1, t / 0.003)
    return norm(x)


def saw(freq, t):
    nh = int(min(40, 9000 / freq))
    k = np.arange(1, nh + 1)[:, None]
    return (np.sin(2 * np.pi * freq * k * t[None, :]) / k).sum(0)


def pad(freqs, d, cutoff=1600, attack=0.6, release=0.8):
    t = t_of(d)
    l = np.zeros_like(t)
    r = np.zeros_like(t)
    for f in freqs:
        for v, det in enumerate([-0.007, -0.0025, 0.0025, 0.007]):
            s = saw(f * (1 + det), t + v * 0.013)
            if v % 2:
                l += s
            else:
                r += s
    env = np.minimum(1, t / attack) * np.minimum(1, (d - t) / release)
    l = filt(l, 60, cutoff, 2) * env
    r = filt(r, 60, cutoff, 2) * env
    g = max(np.max(np.abs(l)), np.max(np.abs(r)))
    return l / g, r / g


def bass(f, d):
    t = t_of(d)
    x = np.sin(2 * np.pi * f * t) + 0.3 * np.sin(4 * np.pi * f * t) + 0.12 * np.sin(6 * np.pi * f * t)
    env = np.minimum(1, t / 0.006) * np.exp(-t / 0.28) * np.minimum(1, (d - t) / 0.02)
    return np.tanh(1.6 * x * env)


def kick(d=0.5):
    t = t_of(d)
    f = 48 + 90 * np.exp(-t / 0.03)
    x = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / 0.22)
    return norm(np.tanh(1.4 * x))


def rim():
    d = 0.12
    t = t_of(d)
    x = 0.7 * np.sin(2 * np.pi * 1700 * t) * np.exp(-t / 0.012) + norm(filt(noise(d), 1800, 7000)) * np.exp(-t / 0.02)
    return norm(x)


def clap():
    out = np.zeros(int(0.4 * SR))
    for off in [0.0, 0.012, 0.024]:
        b = filt(noise(0.03), 900, 5000) * np.exp(-t_of(0.03) / 0.007)
        i = int(off * SR)
        out[i : i + len(b)] += norm(b)
    tail = filt(noise(0.35), 900, 6000) * np.exp(-t_of(0.35) / 0.12)
    i = int(0.03 * SR)
    out[i : i + len(tail)] += 0.5 * norm(tail)
    return norm(out)


def shaker():
    d = 0.09
    t = t_of(d)
    env = np.sin(np.pi * np.clip(t / d, 0, 1)) ** 2
    return norm(filt(noise(d), 6000, 15000) * env)


def ui_tap():
    d = 0.05
    t = t_of(d)
    x = np.sin(2 * np.pi * 1900 * t) * np.exp(-t / 0.008) + 0.3 * np.sin(2 * np.pi * 3800 * t) * np.exp(-t / 0.004)
    return norm(x)


def swish(d=0.3):
    t = t_of(d)
    x = filt(noise(d), 1500, 9000) * np.sin(np.pi * t / d) ** 2
    return norm(x)


def boom(d=3.0):
    t = t_of(d)
    f = 28 + 60 * np.exp(-t / 0.4)
    x = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / 1.1)
    rumble = filt(noise(d), 30, 400) * np.exp(-t / 0.9)
    return norm(x + 0.3 * norm(rumble)) * np.minimum(1, (d - t) / 0.3)


def swell(d=2.0, lo=600, hi=9000):
    t = t_of(d)
    x = filt(noise(d), lo, hi) * (t / d) ** 3
    return norm(x)


def bell(f, d=3.0):
    t = t_of(d)
    parts = [(1, 1.0, 1.8), (2.0, 0.4, 1.1), (3.0, 0.2, 0.6), (4.2, 0.1, 0.35)]
    x = sum(a * np.sin(2 * np.pi * f * r * t) * np.exp(-t / dd) for r, a, dd in parts)
    x *= np.minimum(1, t / 0.002)
    return norm(x)


def reverb(st, seconds=3.2, tone=7000):
    t = t_of(seconds)
    out = []
    for ch in range(2):
        ir = filt(noise(seconds), 150, tone) * np.exp(-t / (seconds / 5))
        ir[: int(0.02 * SR)] = 0
        ir = norm(ir)
        size = 1 << int(np.ceil(np.log2(st.shape[1] + len(ir))))
        y = np.fft.irfft(np.fft.rfft(st[ch], size) * np.fft.rfft(ir, size), size)[: st.shape[1]]
        out.append(y)
    y = np.stack(out)
    return y / (np.max(np.abs(y)) + 1e-9)


# ---------------------------------------------------------------- arrangement
music, drums, sfx, send = Bus(), Bus(), Bus(), Bus()

# Pads under every bar; the intro pad swells in from silence.
for b, name in enumerate(PROG):
    freqs, _ = CHORDS[name]
    at = b * BAR
    d = BAR + 0.9
    l, r = pad(freqs, d, cutoff=1100 if b == 0 else (2400 if b >= 5 else 1700), attack=2.2 if b == 0 else 0.25)
    gain = 0.2 if b in (0, 7) else 0.26
    if b == 9:
        l, r = pad(freqs, 3.0, cutoff=2000, attack=0.1, release=2.2)
    music.add_st(l, r, at, gain)
    send.add_st(l, r, at, 0.25)

# Piano motif on the opening words; a resolving chord on the logo.
for at, f in TEXT_NOTES:
    p = piano(f, 3.0)
    music.add(p, at, 0.34, 0.15)
    send.add(p, at, 0.45)
for f in [220.0, 329.63, 440.0, 554.37, 659.25]:
    p = piano(f, 3.0)
    music.add(p, LOGO, 0.16, 0.0)
    send.add(p, LOGO, 0.25)

# Plucked arps from the reveal on (16ths), resting under the stats bar.
step = BEAT / 4
for b in range(1, 9):
    if b == 7:
        continue
    freqs, _ = CHORDS[PROG[b]]
    notes = [f * 2 for f in freqs] + [freqs[1] * 4, freqs[2] * 2]
    order = [0, 1, 2, 3, 4, 2, 5, 3]
    for k in range(16):
        at = b * BAR + k * step
        if b == 1 and k < 4:
            continue
        f = notes[order[k % 8]]
        g = (0.18 if b >= 5 else 0.14) * (1.0 if k % 4 == 0 else 0.7)
        pl = pluck(f, 0.8, bright=0.75 if b >= 5 else 0.55)
        music.add(pl, at, g, 0.35 if k % 2 else -0.35)
        send.add(pl, at, 0.12)

# Bass pulse in eighths from the beat-in.
for b in range(2, 9):
    if b == 7:
        continue
    _, root = CHORDS[PROG[b]]
    for k in range(8):
        music.add(bass(root, 0.36), b * BAR + k * (BEAT / 2), 0.32 if k % 2 == 0 else 0.22)

# Drums: beat-in at 6.0, lift at 15.0, breakdown for the stats, full again at the finale.
K, R, C, S = kick(), rim(), clap(), shaker()
for b in range(2, 9):
    if b == 7:
        continue
    t0 = b * BAR
    lift = b >= 5
    for q in [0, 2]:
        drums.add(K, t0 + q * BEAT, 0.75)
    if lift:
        drums.add(K, t0 + 2.5 * BEAT, 0.45)
    for q in [1, 3]:
        drums.add(C if lift else R, t0 + q * BEAT, 0.42 if lift else 0.32, 0.05)
        send.add(C if lift else R, t0 + q * BEAT, 0.2)
    for e in range(8):
        drums.add(S, t0 + e * BEAT / 2 + 0.01, 0.1 if e % 2 else 0.06, 0.3)

# Stats bar: one soft hit per number, then a swell into the finale.
for at in STATS:
    drums.add(K, at, 0.8)
    sfx.add(bell(1760.0, 1.2), at, 0.08, 0.1)
    send.add(bell(1760.0, 1.2), at, 0.12)
    music.add(bass(55.0, 0.9), at, 0.4)
sfx.add(swell(1.6, 800, 10000), FINALE - 1.6, 0.22)

# Cinematic moments.
sfx.add(swell(2.6, 300, 6000), REVEAL - 2.6, 0.16)
sfx.add(boom(), REVEAL, 0.6)
send.add(boom(), REVEAL, 0.15)
for f in [880.0, 1318.5]:
    sfx.add(bell(f, 2.5), SCREEN_ON, 0.09)
    send.add(bell(f, 2.5), SCREEN_ON, 0.18)
sfx.add(swell(1.0, 1000, 12000), RESULT - 1.0, 0.18)
sfx.add(boom(2.0), RESULT, 0.3)
for f in [1108.73, 1318.5, 1760.0]:
    sfx.add(bell(f, 2.5), RESULT + 0.02, 0.07)
    send.add(bell(f, 2.5), RESULT + 0.02, 0.15)
sfx.add(boom(2.5), FINALE, 0.4)
sfx.add(boom(3.0), LOGO, 0.35)
for f in [880.0, 1108.73, 1318.5]:
    sfx.add(bell(f, 3.0), LOGO + 0.01, 0.06)
    send.add(bell(f, 3.0), LOGO + 0.01, 0.15)

# UI: taps and screen pushes.
for at in TAPS:
    sfx.add(ui_tap(), at, 0.22, 0.1)
for at in PUSHES:
    sfx.add(swish(0.32), at - 0.04, 0.07, -0.2)

# ---------------------------------------------------------------- mix
mix = music.x + drums.x * 0.9 + sfx.x
mix += reverb(send.x) * 0.22 * np.max(np.abs(send.x))
mix = np.stack([filt(ch, 30, 16500, 2) for ch in mix])
fade = np.ones(N)
fade[int(29.2 * SR) :] = np.linspace(1, 0, N - int(29.2 * SR)) ** 1.5
mix *= fade
drive = 1.15
mix = np.tanh(drive * mix / np.max(np.abs(mix))) / np.tanh(drive)
mix *= 10 ** (-1.5 / 20) / np.max(np.abs(mix))

here = os.path.dirname(os.path.abspath(__file__))
out = os.path.join(here, "..", "assets", "audio", "score.wav")
pcm = (mix.T * 32767).astype("<i2")
with wave.open(out, "wb") as w:
    w.setnchannels(2)
    w.setsampwidth(2)
    w.setframerate(SR)
    w.writeframes(pcm.tobytes())
print("wrote", os.path.normpath(out))
