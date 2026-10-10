"""Polished synth score engine shared by the MakanApa ad films.

Modern product-launch style, 80 BPM in A major (one beat = 0.75 s, one bar = 3 s):
felt piano, warm sidechained pads, sub bass, tight drums, risers and impacts, plus the UI
sound design. Everything is synthesized from scratch (no samples), so it is ours to use anywhere.

Each film's audio/make_score.py describes its bars (one level per bar) and its event map, then
calls render(). Levels: intro, calm, full, lift, end.
"""

import wave

import numpy as np

SR = 44100
BEAT = 0.75
BAR = 3.0
rng = np.random.default_rng(80)

# vi IV I V with added ninths: warm, modern, resolves home on A.
CHORDS = {
    "Asus": ([220.0, 246.94, 329.63, 493.88], 55.0),
    "F#m": ([185.0, 277.18, 329.63, 415.30], 46.25),
    "D": ([146.83, 220.0, 277.18, 329.63], 36.71),
    "A": ([164.81, 220.0, 277.18, 493.88], 55.0),
    "E": ([164.81, 246.94, 311.13, 369.99], 41.20),
}
CYCLE = ["F#m", "D", "A", "E"]


# ---------------------------------------------------------------- helpers
def t_of(d):
    return np.arange(int(d * SR)) / SR


def filt(x, lo=None, hi=None, order=4):
    f = np.fft.rfftfreq(len(x), 1 / SR)
    m = np.ones_like(f)
    if lo:
        m *= 1 / np.sqrt(1 + (lo / np.maximum(f, 1e-6)) ** (2 * order))
    if hi:
        m *= 1 / np.sqrt(1 + (f / hi) ** (2 * order))
    return np.fft.irfft(np.fft.rfft(x) * m, len(x))


def noise(d):
    return rng.standard_normal(int(d * SR))


def norm(x):
    p = np.max(np.abs(x))
    return x / p if p > 0 else x


class Bus:
    def __init__(self, n):
        self.x = np.zeros((2, n))

    def add(self, sig, at, gain=1.0, pan=0.0):
        n = self.x.shape[1]
        i = int(round(at * SR))
        if i >= n or i < 0:
            return
        j = min(n, i + len(sig))
        s = sig[: j - i] * gain
        a = (pan + 1) * np.pi / 4
        self.x[0, i:j] += s * np.cos(a) * np.sqrt(2)
        self.x[1, i:j] += s * np.sin(a) * np.sqrt(2)

    def add_st(self, l, r, at, gain=1.0):
        n = self.x.shape[1]
        i = int(round(at * SR))
        j = min(n, i + len(l))
        self.x[0, i:j] += l[: j - i] * gain
        self.x[1, i:j] += r[: j - i] * gain


# ---------------------------------------------------------------- instruments
def kick():
    t = t_of(0.55)
    f = 44 + 120 * np.exp(-t / 0.032)
    body = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / 0.17)
    click = filt(noise(0.012), 2500, 10000) * np.exp(-t_of(0.012) / 0.0025)
    body[: len(click)] += 0.22 * norm(click)
    return norm(np.tanh(2.0 * body))


def snare():
    t = t_of(0.4)
    tone = 0.55 * np.sin(2 * np.pi * 185 * t) * np.exp(-t / 0.05) + 0.25 * np.sin(2 * np.pi * 330 * t) * np.exp(-t / 0.035)
    body = filt(noise(0.4), 1500, 9500) * np.exp(-t / 0.12)
    return norm(tone + 0.9 * norm(body))


def clap():
    out = np.zeros(int(0.45 * SR))
    for off in [0.0, 0.011, 0.023]:
        b = filt(noise(0.03), 1000, 6000) * np.exp(-t_of(0.03) / 0.006)
        i = int(off * SR)
        out[i : i + len(b)] += norm(b)
    tail = filt(noise(0.4), 1000, 7000) * np.exp(-t_of(0.4) / 0.13)
    i = int(0.03 * SR)
    out[i : i + len(tail)] += 0.45 * norm(tail)
    return norm(out)


def hat(open_=False):
    d = 0.32 if open_ else 0.07
    t = t_of(d)
    return norm(filt(noise(d), 7500, 17000) * np.exp(-t / (0.1 if open_ else 0.014)))


def crash(d=2.4):
    t = t_of(d)
    return norm(filt(noise(d), 4000, 16000) * np.exp(-t / 0.7))


def felt_piano(f, d=2.6):
    """Soft, slightly dark piano: inharmonic partials, gentle hammer, two detuned strings."""
    t = t_of(d)
    x = np.zeros_like(t)
    for k, a in enumerate([1.0, 0.38, 0.16, 0.08, 0.04, 0.02], start=1):
        fk = f * k * np.sqrt(1 + 0.0003 * k * k)
        dec = np.exp(-t / (2.4 / k**0.8))
        x += a * (np.sin(2 * np.pi * fk * t) + np.sin(2 * np.pi * fk * 1.0012 * t)) * 0.5 * dec
    hammer = filt(noise(0.015), 400, 3000) * np.exp(-t_of(0.015) / 0.004)
    x[: len(hammer)] += 0.04 * hammer
    x *= np.minimum(1, t / 0.008)
    x *= np.minimum(1, (d - t) / 0.08)
    return norm(filt(x, 60, 6500, 2))


def saw(freq, t):
    nh = int(min(30, 7000 / freq))
    k = np.arange(1, nh + 1)[:, None]
    return (np.sin(2 * np.pi * freq * k * t[None, :]) / k).sum(0)


def pad(freqs, d, cutoff=1600, attack=0.5, release=0.9):
    t = t_of(d)
    l = np.zeros_like(t)
    r = np.zeros_like(t)
    for f in freqs:
        for v, det in enumerate([-0.006, -0.002, 0.002, 0.006]):
            s = 0.6 * saw(f * (1 + det), t + v * 0.011) + 0.8 * np.sin(2 * np.pi * f * (1 + det) * t)
            if v % 2:
                l += s
            else:
                r += s
    env = np.minimum(1, t / attack) * np.minimum(1, (d - t) / release)
    l = filt(l, 120, cutoff, 2) * env
    r = filt(r, 120, cutoff, 2) * env
    g = max(np.max(np.abs(l)), np.max(np.abs(r)))
    return l / g, r / g


def sub(f, d):
    t = t_of(d)
    x = np.sin(2 * np.pi * f * t) + 0.18 * np.sin(4 * np.pi * f * t)
    env = np.minimum(1, t / 0.01) * np.minimum(1, (d - t) / 0.06)
    return np.tanh(1.3 * x * env)


def pluck(f, d=0.6, bright=0.6, decay=0.995):
    """Karplus-Strong plucked string."""
    p = max(2, int(round(SR / f)))
    n = int(d * SR)
    y = np.zeros(n + p + 1)
    y[:p] = filt(rng.uniform(-1, 1, p * 8), None, 1500 + 6000 * bright)[:p]
    for k in range(p, n + p, p):
        a = y[k - p : k]
        b = np.concatenate(([y[k - p - 1] if k - p - 1 >= 0 else 0.0], y[k - p : k - 1]))
        y[k : k + p] = (0.5 * (a + b) * decay)[: len(y[k : k + p])]
    out = y[p : p + n]
    out *= np.minimum(1, (d - t_of(d)[: len(out)]) / 0.05)
    return norm(filt(out, 300, None, 2))


def bell(f, d=3.0):
    t = t_of(d)
    parts = [(1, 1.0, 1.8), (2.0, 0.35, 1.1), (3.0, 0.15, 0.6), (4.2, 0.08, 0.35)]
    x = sum(a * np.sin(2 * np.pi * f * r * t) * np.exp(-t / dd) for r, a, dd in parts)
    return norm(x * np.minimum(1, t / 0.002))


def boom(d=3.0):
    t = t_of(d)
    f = 30 + 55 * np.exp(-t / 0.35)
    x = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / 1.0)
    rumble = filt(noise(d), 30, 300) * np.exp(-t / 0.8)
    return norm(x + 0.25 * norm(rumble)) * np.minimum(1, (d - t) / 0.3)


def riser(d=2.0, lo=800, hi=12000):
    """Reverse-cymbal style rise into a hit."""
    t = t_of(d)
    return norm(filt(noise(d), lo, hi) * (t / d) ** 2.6)


def swish(d=0.3):
    t = t_of(d)
    return norm(filt(noise(d), 1500, 9000) * np.sin(np.pi * t / d) ** 2)


def ui_tap():
    t = t_of(0.05)
    x = np.sin(2 * np.pi * 1900 * t) * np.exp(-t / 0.008) + 0.3 * np.sin(2 * np.pi * 3800 * t) * np.exp(-t / 0.004)
    return norm(x)


def flick(d=0.04, lo=3000):
    t = t_of(d)
    return norm(filt(noise(d), lo, 14000) * np.exp(-t / (d / 5)))


def reverb(st, seconds=3.0, tone=7000):
    t = t_of(seconds)
    out = []
    for ch in range(2):
        ir = filt(noise(seconds), 200, tone) * np.exp(-t / (seconds / 5))
        ir[: int(0.025 * SR)] = 0
        ir = norm(ir)
        size = 1 << int(np.ceil(np.log2(st.shape[1] + len(ir))))
        out.append(np.fft.irfft(np.fft.rfft(st[ch], size) * np.fft.rfft(ir, size), size)[: st.shape[1]])
    y = np.stack(out)
    return y / (np.max(np.abs(y)) + 1e-9)


# ---------------------------------------------------------------- arrangement
PAD = {"intro": (0.26, 1000), "calm": (0.22, 1400), "full": (0.24, 2200), "lift": (0.27, 3000), "end": (0.28, 2200)}
# Piano motif: chord tones in eighths (indices into the voicing; 4 = the top note an octave up).
MOTIF = [1, 2, 3, 2, 4, 3, 2, 3]


def render(levels, events, out, fade_at):
    dur = len(levels) * BAR
    n = int(SR * dur)
    music, bass, drums, sfx, send = Bus(n), Bus(n), Bus(n), Bus(n), Bus(n)
    K, SN, CL, HC, HO = kick(), snare(), clap(), hat(), hat(True)
    kicks = []

    for b, lvl in enumerate(levels):
        t0 = b * BAR
        name = "Asus" if lvl == "intro" else ("A" if lvl == "end" else CYCLE[(b - 1) % 4])
        freqs, root = CHORDS[name]
        g, cut = PAD[lvl]

        # Pads.
        if lvl == "end":
            l, r = pad(freqs, BAR, cutoff=cut, attack=0.1, release=2.4)
        else:
            l, r = pad(freqs, BAR + 0.9, cutoff=cut, attack=2.2 if lvl == "intro" else 0.3)
        music.add_st(l, r, t0, g)
        send.add_st(l, r, t0, 0.2)

        # Felt piano: the chord on the one, then the motif in eighths.
        for i, f in enumerate(freqs):
            p = felt_piano(f, 2.8)
            music.add(p, t0 + i * 0.012, 0.11, -0.3 + i * 0.2)
            send.add(p, t0, 0.06)
        if lvl in ("calm", "full", "lift"):
            top = freqs + [freqs[0] * 2]
            for k, idx in enumerate(MOTIF):
                f = top[idx] * 2
                p = felt_piano(f, 1.4)
                at = t0 + k * BEAT / 2
                music.add(p, at, (0.15 if lvl == "lift" else 0.13) * (1.0 if k % 2 == 0 else 0.75), 0.25 if k % 2 else -0.25)
                send.add(p, at, 0.07)

        # Plucked sixteenths shimmer on the lifts.
        if lvl == "lift":
            notes = [f * 4 for f in freqs]
            for k in range(16):
                pl = pluck(notes[[0, 2, 1, 3][k % 4]], 0.5, bright=0.7)
                music.add(pl, t0 + k * BEAT / 4, 0.08 * (1.0 if k % 4 == 0 else 0.6), 0.5 if k % 2 else -0.5)
                send.add(pl, t0 + k * BEAT / 4, 0.04)

        # Sub bass.
        if lvl == "calm":
            bass.add(sub(root * 2, BAR), t0, 0.16)
        elif lvl in ("full", "lift"):
            for pos, length in [(0, 1.5), (1.5, 0.5), (2.0, 1.0), (3.0, 0.5), (3.5, 0.5)]:
                bass.add(sub(root * 2, length * BEAT - 0.02), t0 + pos * BEAT, 0.2)
        elif lvl == "end":
            bass.add(sub(root * 2, BAR), t0, 0.18)

        # Drums.
        if lvl == "calm":
            for pos in [0, 2.5]:
                drums.add(K, t0 + pos * BEAT, 0.3)
                kicks.append(t0 + pos * BEAT)
            for pos in [1, 3]:
                s = filt(SN, 1500, None, 2)
                drums.add(s, t0 + pos * BEAT, 0.14, 0.05)
                send.add(s, t0 + pos * BEAT, 0.1)
            for e in range(8):
                drums.add(HC, t0 + e * BEAT / 2 + 0.005, 0.05 if e % 2 else 0.035, 0.3)
        elif lvl in ("full", "lift"):
            ks = [0, 1.5, 2.0] + ([3.5] if lvl == "lift" else [])
            for pos in ks:
                drums.add(K, t0 + pos * BEAT, 0.48)
                kicks.append(t0 + pos * BEAT)
            for pos in [1, 3]:
                drums.add(SN, t0 + pos * BEAT, 0.3, 0.05)
                send.add(SN, t0 + pos * BEAT, 0.16)
                if lvl == "lift":
                    drums.add(CL, t0 + pos * BEAT + 0.004, 0.22, -0.1)
            vel = [1.0, 0.45, 0.7, 0.45]
            for e in range(16):
                drums.add(HC, t0 + e * BEAT / 4 + 0.004, 0.13 * vel[e % 4], 0.3)
            if lvl == "lift":
                for q in range(4):
                    drums.add(HO, t0 + (q + 0.5) * BEAT, 0.09, -0.3)
        elif lvl == "end":
            drums.add(K, t0, 0.5)
            kicks.append(t0)
            drums.add(crash(), t0, 0.12)
            send.add(crash(), t0, 0.1)

        # A riser into every lift, and a crash on its downbeat.
        nxt = levels[b + 1] if b + 1 < len(levels) else None
        if nxt == "lift" and lvl != "lift":
            drums.add(riser(1.5), t0 + BAR - 1.5, 0.16)
        if lvl == "lift" and (b == 0 or levels[b - 1] != "lift"):
            drums.add(crash(), t0, 0.1)
            send.add(crash(), t0, 0.08)

    # ---- events: UI sound design and cinematic hits
    for ev in events:
        kind, at = ev[0], ev[1]
        if kind == "note":
            p = felt_piano(ev[2], 3.0)
            music.add(p, at, 0.3, 0.1)
            send.add(p, at, 0.4)
        elif kind == "tap":
            sfx.add(ui_tap(), at, 0.2, 0.1)
        elif kind == "swish":
            sfx.add(swish(0.32), at - 0.04, 0.07, -0.2)
        elif kind == "reveal":
            sfx.add(riser(2.4, 300, 8000), at - 2.4, 0.14)
            sfx.add(boom(), at, 0.25)
            send.add(boom(), at, 0.12)
        elif kind == "thud":
            sfx.add(riser(0.5, 400, 5000), at - 0.5, 0.16)
            sfx.add(kick(), at, 0.5)
            sfx.add(boom(1.2), at, 0.16)
        elif kind == "pop":
            sfx.add(flick(0.05, 900), at, 0.45)
            for f in [1318.5, 1760.0, 2217.46]:
                sfx.add(bell(f, 2.0), at + 0.03, 0.05)
                send.add(bell(f, 2.0), at + 0.03, 0.14)
        elif kind == "fan":
            sfx.add(swish(0.45), at - 0.05, 0.11, 0.1)
        elif kind == "flips":
            for k in range(5):
                sfx.add(flick(0.04, 3000), at + k * 0.04 + 0.2, 0.2, -0.4 + k * 0.2)
        elif kind == "riffle":
            for k in range(5):
                sfx.add(flick(0.03, 4000), at + k * 0.1 + 0.14, 0.28, -0.5 if k % 2 == 0 else 0.5)
            sfx.add(flick(0.06, 1200), at + 0.7, 0.32)
        elif kind == "impact":
            sfx.add(riser(1.0, 1000, 12000), at - 1.0, 0.16)
            sfx.add(boom(2.0), at, 0.16)
            for f in [1108.73, 1318.5, 1760.0]:
                sfx.add(bell(f, 2.5), at + 0.02, 0.06)
                send.add(bell(f, 2.5), at + 0.02, 0.14)
        elif kind == "tick":
            sfx.add(bell(2217.46, 0.8), at, 0.045, 0.2)
            send.add(bell(2217.46, 0.8), at, 0.07)
        elif kind == "chime":
            for f in [880.0, 1108.73, 1318.5]:
                sfx.add(bell(f, 1.6), at + 0.02, 0.055)
        elif kind == "soft":
            sfx.add(bell(1760.0, 1.4), at, 0.06, 0.1)
            send.add(bell(1760.0, 1.4), at, 0.11)
        elif kind == "crest":
            sfx.add(riser(1.4, 600, 10000), at - 1.4, 0.18)
            sfx.add(boom(2.5), at, 0.2)
            for f in [880.0, 1318.5, 1760.0]:
                sfx.add(bell(f, 3.0), at, 0.06)
                send.add(bell(f, 3.0), at, 0.17)
        elif kind == "logo":
            sfx.add(riser(1.6, 800, 10000), at - 1.6, 0.18)
            sfx.add(boom(3.0), at, 0.18)
            for f in [220.0, 329.63, 440.0, 554.37, 659.25]:
                p = felt_piano(f, 3.0)
                music.add(p, at, 0.14)
                send.add(p, at, 0.25)
            for f in [880.0, 1108.73, 1318.5]:
                sfx.add(bell(f, 3.0), at + 0.01, 0.05)
                send.add(bell(f, 3.0), at + 0.01, 0.14)

    # ---- sidechain: pads, piano and bass breathe around every kick
    env = np.ones(n)
    tail = np.exp(-t_of(0.32) / 0.11)
    for tk in kicks:
        i = int(round(tk * SR))
        j = min(n, i + len(tail))
        env[i:j] = np.minimum(env[i:j], 1 - 0.5 * tail[: j - i])

    mix = (music.x + bass.x) * env + drums.x + sfx.x
    mix += reverb(send.x) * 0.2 * np.max(np.abs(send.x))
    mix = np.stack([filt(ch, 35, 17000, 2) for ch in mix])

    # Master: level to a steady loudness, glue with a soft clip, leave 1 dB of headroom.
    rms = np.sqrt(np.mean(mix**2))
    mix *= 10 ** (-16 / 20) / (rms + 1e-9)
    mix = np.tanh(mix * 1.1) / np.tanh(1.1)
    fade = np.ones(n)
    k = int(fade_at * SR)
    fade[k:] = np.linspace(1, 0, n - k) ** 1.5
    mix *= fade
    mix *= 10 ** (-1 / 20) / np.max(np.abs(mix))

    pcm = (mix.T * 32767).astype("<i2")
    with wave.open(out, "wb") as w:
        w.setnchannels(2)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes(pcm.tobytes())
    print("wrote", out)
