"""Original 30s soundtrack + sound design for the MakanApa premium ad.

Everything is synthesized here (no samples), so the track is ours to use anywhere.
120 BPM, F minor. One beat = 0.5s, one bar = 2s. Event times below are the same
numbers the composition (index.html + compositions/*.html) animates on, so cuts,
slams and hits land together. Change a time here, change it there too.

    python3 audio/make_soundtrack.py   ->  assets/audio/soundtrack.wav
"""

import os
import wave

import numpy as np

SR = 44100
DUR = 30.0
N = int(SR * DUR)
BEAT = 0.5
rng = np.random.default_rng(20261005)

# ---------------------------------------------------------------- event map
HOOK_SLAMS = [0.0, 0.5, 1.0, 1.5]
CHAT_POPS = [  # (time, sent_by_you)
    (2.25, True), (2.75, False), (3.25, False), (3.75, True), (4.0, False), (4.25, False),
    (4.5, False), (4.75, True), (4.875, False), (5.0, False), (5.125, False), (5.25, True),
    (5.375, False), (5.5, False), (5.625, False),
]
LATER_HIT, STILL_HIT = 6.0, 7.0
TAPE_STOP = (7.6, 8.0)
MASCOT_BOING = 8.55
BUILD_WORDS = [10.0, 10.5, 11.0, 11.25, 11.5]
DROP = 12.0
TAP_PANELS = [14.0, 16.0, 18.0]
TAP_FLIPS = [14.5, 14.75, 15.0, 15.25, 16.5, 16.75, 17.0, 17.25, 18.5, 18.75, 19.0]
TAP_LOCKS = [15.5, 17.5, 19.5]
BUTTON_IN, BUTTON_PRESS = 20.0, 20.5
WHEEL_SPIN = (20.5, 22.0)  # power4.out, 0 -> 1552.5 deg (69 faces of 22.5 deg)
WHEEL_DEG, WHEEL_STEP = 1552.5, 22.5
LAND = 22.0
BURST = 22.2
CONFETTI = 22.6
CALLBACK_WORDS = [26.0, 26.5, 26.75]
STRIKE = 27.25
END_HIT = 28.0
CUT = 29.75


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
        self.l = np.zeros(N)
        self.r = np.zeros(N)

    def add(self, sig, at, gain=1.0, pan=0.0):
        i = int(round(at * SR))
        if i >= N or i + len(sig) <= 0:
            return
        s = sig * gain
        if i < 0:
            s, i = s[-i:], 0
        j = min(N, i + len(s))
        s = s[: j - i]
        a = (pan + 1) * np.pi / 4
        self.l[i:j] += s * np.cos(a) * np.sqrt(2)
        self.r[i:j] += s * np.sin(a) * np.sqrt(2)

    def add_st(self, l, r, at, gain=1.0):
        i = int(round(at * SR))
        j = min(N, i + len(l))
        self.l[i:j] += l[: j - i] * gain
        self.r[i:j] += r[: j - i] * gain

    def stereo(self):
        return np.stack([self.l, self.r])


# ---------------------------------------------------------------- instruments
def kick(dur=0.42, f0=165, f1=44, drive=2.4):
    t = t_of(dur)
    f = f1 + (f0 - f1) * np.exp(-t / 0.032)
    body = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / 0.2)
    x = np.tanh(drive * body) / np.tanh(drive)
    click = filt(noise(0.008), 1500, 9000) * np.exp(-t_of(0.008) / 0.0015)
    x[: len(click)] += 0.35 * norm(click)
    fade = np.minimum(1, (dur - t) / 0.02)
    return x * fade


def clap():
    out = np.zeros(int(0.35 * SR))
    for k, off in enumerate([0.0, 0.011, 0.023]):
        b = filt(noise(0.03), 900, 4500) * np.exp(-t_of(0.03) / 0.006)
        i = int(off * SR)
        out[i : i + len(b)] += norm(b) * (0.8 if k < 2 else 1.0)
    tail = filt(noise(0.3), 1000, 5000) * np.exp(-t_of(0.3) / 0.09)
    i = int(0.03 * SR)
    out[i : i + len(tail)] += 0.6 * norm(tail)
    return norm(out)


def hat(open_=False):
    d = 0.32 if open_ else 0.06
    x = filt(noise(d), 7000, None, 6) * np.exp(-t_of(d) / (0.11 if open_ else 0.016))
    return norm(x)


def snare():
    d = 0.22
    t = t_of(d)
    n = filt(noise(d), 1400, 8000) * np.exp(-t / 0.075)
    tone = np.sin(2 * np.pi * 190 * t) * np.exp(-t / 0.05)
    return norm(norm(n) + 0.6 * tone)


def impact(size=1.0):
    d = 2.2
    t = t_of(d)
    f = 30 + 34 * np.exp(-t / 0.25)
    boom = np.tanh(2.0 * np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / (0.55 * size)))
    rumble = filt(noise(d), 40, 2500) * np.exp(-t / (0.45 * size))
    crack = filt(noise(0.06), 1800, 9000) * np.exp(-t_of(0.06) / 0.012)
    x = boom + 0.35 * norm(rumble)
    x[: len(crack)] += 0.55 * norm(crack)
    return norm(x) * np.minimum(1, (d - t) / 0.1)


def crash(d=2.6):
    t = t_of(d)
    x = filt(noise(d), 3500, 16000) * np.exp(-t / 0.8)
    ring = sum(np.sin(2 * np.pi * f * t + p) for f, p in [(3920, 0.1), (5311, 1.3), (6750, 2.2), (8123, 0.7)])
    x = norm(x) + 0.05 * ring * np.exp(-t / 0.5)
    return norm(x) * np.minimum(1, (d - t) / 0.2)


def sweep_noise(d, f_lo, f_hi, width=1.6, rise=True):
    """Noise through a band that glides from f_lo to f_hi (log), via STFT overlap-add."""
    n_fft, hop = 4096, 1024
    src = noise(d + n_fft / SR)
    out = np.zeros(len(src))
    norm_w = np.zeros(len(src))
    win = np.hanning(n_fft)
    freqs = np.fft.rfftfreq(n_fft, 1 / SR)
    for i in range(0, int(d * SR), hop):
        p = i / (d * SR)
        fc = f_lo * (f_hi / f_lo) ** p
        m = np.exp(-0.5 * (np.log(np.maximum(freqs, 1) / fc) / np.log(width)) ** 2)
        frame = np.fft.irfft(np.fft.rfft(src[i : i + n_fft] * win) * m, n_fft) * win
        out[i : i + n_fft] += frame
        norm_w[i : i + n_fft] += win**2
    out = (out / np.maximum(norm_w, 1e-3))[: int(d * SR)]
    t = t_of(d)
    env = (t / d) ** 2.2 if rise else np.ones_like(t)
    return norm(out) * env


def whoosh(d=0.42):
    t = t_of(d)
    up = sweep_noise(d, 350, 4200, 1.5, rise=False)
    env = np.sin(np.pi * np.clip(t / d, 0, 1)) ** 1.6
    return norm(up * env)


def pop(sent):
    d = 0.09
    t = t_of(d)
    f0, f1 = (1150, 1650) if sent else (720, 1050)
    f = f0 + (f1 - f0) * (1 - np.exp(-t / 0.012))
    x = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / 0.022)
    x[:40] *= np.linspace(0, 1, 40)
    return x


def tick():
    d = 0.02
    t = t_of(d)
    x = np.sin(2 * np.pi * 2600 * t) * np.exp(-t / 0.0035) + 0.5 * norm(filt(noise(d), 2500, 9000)) * np.exp(-t / 0.002)
    return norm(x)


def bell(f=1046.5, d=2.0):
    t = t_of(d)
    parts = [(1, 1.0, 1.4), (2.0, 0.5, 0.9), (2.76, 0.35, 0.6), (4.07, 0.2, 0.4), (5.4, 0.12, 0.25)]
    x = sum(a * np.sin(2 * np.pi * f * r * t) * np.exp(-t / dd) for r, a, dd in parts)
    x[:60] *= np.linspace(0, 1, 60)
    return norm(x)


def boing(d=0.35):
    t = t_of(d)
    f = 240 + 420 * (t / d) ** 0.6 + 18 * np.sin(2 * np.pi * 26 * t)
    return np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / 0.14)


def saw(freq, t):
    nh = int(min(48, 11000 / freq))
    k = np.arange(1, nh + 1)[:, None]
    return (np.sin(2 * np.pi * freq * k * t[None, :]) / k).sum(0) * (2 / np.pi)


def supersaw_chord(freqs, d, detune=0.006, voices=5):
    t = t_of(d)
    l = np.zeros_like(t)
    r = np.zeros_like(t)
    for f in freqs:
        for v in range(voices):
            dv = (v - (voices - 1) / 2) / ((voices - 1) / 2)
            s = saw(f * (1 + detune * dv), t + rng.random() * 0.01)
            a = (dv + 1) * np.pi / 4
            l += s * np.cos(a)
            r += s * np.sin(a)
    return l / (len(freqs) * voices), r / (len(freqs) * voices)


def keys_chord(freqs, d):
    t = t_of(d)
    x = sum(
        (np.sin(2 * np.pi * f * t) + 0.25 * np.sin(4 * np.pi * f * t) + 0.08 * np.sin(6 * np.pi * f * t))
        * (1 + 0.15 * np.sin(2 * np.pi * 4.5 * t))
        for f in freqs
    )
    env = np.minimum(1, t / 0.01) * np.exp(-t / 1.6)
    return norm(x) * env


def bass808(f, d, glide=True, drive=2.6):
    t = t_of(d)
    ff = f * (1 + (0.12 * np.exp(-t / 0.05) if glide else 0))
    x = np.sin(2 * np.pi * np.cumsum(ff) / SR)
    env = np.minimum(1, t / 0.004) * np.exp(-t / max(0.25, d * 0.7)) * np.minimum(1, (d - t) / 0.03)
    return np.tanh(drive * x * env) / np.tanh(drive)


def tape_stop(x, start, end):
    """Slow the audio from `start` to a halt at `end` (seconds), silence after."""
    i0, i1 = int(start * SR), int(end * SR)
    n = i1 - i0
    speed = (1 - np.linspace(0, 1, n)) ** 1.4
    pos = i0 + np.cumsum(speed)
    for ch in range(x.shape[0]):
        seg = np.interp(pos, np.arange(x.shape[1]), x[ch])
        x[ch, i0:i1] = seg * np.linspace(1, 0.6, n)
        x[ch, i1:] = 0
    return x


def reverb(st, seconds=2.2, tone=6000):
    n_ir = int(seconds * SR)
    t = t_of(seconds)
    out = []
    for ch in range(2):
        ir = filt(noise(seconds), 200, tone) * np.exp(-t / (seconds / 5.5))
        ir[: int(0.012 * SR)] = 0  # pre-delay
        ir = norm(ir)
        size = 1 << int(np.ceil(np.log2(st.shape[1] + n_ir)))
        y = np.fft.irfft(np.fft.rfft(st[ch], size) * np.fft.rfft(ir, size), size)[: st.shape[1]]
        out.append(y)
    y = np.stack(out)
    return y / (np.max(np.abs(y)) + 1e-9)


def sidechain(kicks, depth=0.65, release=0.16):
    env = np.ones(N)
    t = t_of(0.5)
    shape = 1 - depth * np.exp(-t / release)
    for k in kicks:
        i = int(k * SR)
        j = min(N, i + len(shape))
        env[i:j] = np.minimum(env[i:j], shape[: j - i])
    return env


# ---------------------------------------------------------------- arrangement
F1, DB2, AB1, EB2 = 43.65, 69.30, 51.91, 77.78
CHORDS = [  # F minor: i - VI - III - VII
    [349.23, 415.30, 523.25],
    [277.18, 349.23, 415.30],
    [311.13, 415.30, 523.25],
    [311.13, 392.00, 466.16],
]
ROOTS = [F1, DB2, AB1, EB2]

pre_drums, pre_music, pre_sfx, pre_send = Bus(), Bus(), Bus(), Bus()
drums, music, synth, sfx, send = Bus(), Bus(), Bus(), Bus(), Bus()
K, CL, HC, HO, SN = kick(), clap(), hat(), hat(True), snare()
IMP, CR = impact(), crash()

# Hook: four slams, the last one biggest.
for i, at in enumerate(HOOK_SLAMS):
    big = i == 3
    pre_sfx.add(impact(1.3 if big else 0.8), at, 0.9 if big else 0.7)
    pre_drums.add(K, at, 0.9)
    pre_send.add(impact(), at, 0.25)
pre_music.add(bass808(F1, 1.9, glide=False), 0.0, 0.5)
pre_sfx.add(whoosh(0.4), 1.68, 0.45)

# Chat groove (2.0 - 7.6): half-time trap, keys pad, bubble pops.
for bar in range(3):
    b = 2.0 + bar * 2.0
    for k in [0.0, 0.75, 1.25]:
        pre_drums.add(K, b + k, 0.75)
        pre_music.add(bass808([F1, DB2, AB1][bar], 0.45), b + k, 0.45)
    pre_drums.add(CL, b + 1.0, 0.45, 0.05)
    pre_send.add(CL, b + 1.0, 0.25)
    for h in np.arange(0, 2.0, 0.25):
        pre_drums.add(HC, b + h, 0.12 + 0.05 * ((h * 4) % 2), 0.3)
    if bar < 2:
        for h in [1.625, 1.6875, 1.75, 1.8125]:
            pre_drums.add(HC, b + h, 0.09, 0.3)
    keys = keys_chord([c / 2 for c in CHORDS[bar]] + [CHORDS[bar][0]], 2.0)
    pre_music.add(keys, b, 0.16, -0.15)
    pre_send.add(keys, b, 0.12)
for at, sent in CHAT_POPS:
    pre_sfx.add(pop(sent), at, 0.42, 0.25 if sent else -0.25)
    pre_send.add(pop(sent), at, 0.08)
pre_sfx.add(IMP, LATER_HIT, 0.75)
pre_send.add(IMP, LATER_HIT, 0.25)
for j, at in enumerate(np.cumsum(np.linspace(0.05, 0.02, 22)) + LATER_HIT):  # clock spinning forward
    pre_sfx.add(tick(), at, 0.18, 0.2 * ((-1) ** j))
pre_sfx.add(impact(0.7), STILL_HIT, 0.55)

# Sound familiar? Near silence, a little vinyl crackle, the mascot pops up.
crackle = np.zeros(int(2.0 * SR))
for i in rng.integers(0, len(crackle) - 200, 70):
    crackle[i : i + 40] += rng.standard_normal(40) * np.exp(-np.arange(40) / 6)
sfx.add(filt(crackle, 800, 9000), 8.0, 0.18)
synth.add(np.sin(2 * np.pi * F1 * t_of(2.0)) * np.minimum(1, t_of(2.0) / 0.5) * np.minimum(1, (2.0 - t_of(2.0)) / 0.3), 8.0, 0.07)
sfx.add(boing(), MASCOT_BOING, 0.4)

# Build (10 - 11.75): accelerating snare roll, riser, kicks under the words, then a gap.
roll = []
roll += list(np.arange(10.0, 11.0, 0.25))
roll += list(np.arange(11.0, 11.5, 0.125))
roll += list(np.arange(11.5, 11.75, 0.0625))
for i, at in enumerate(roll):
    drums.add(SN, at, 0.18 + 0.5 * (i / len(roll)) ** 1.5, 0.1 * ((-1) ** i))
    send.add(SN, at, 0.15)
for at in BUILD_WORDS:
    drums.add(K, at, 0.8)
riser = sweep_noise(2.25, 300, 9000)
sfx.add(riser, 9.5, 0.38)
send.add(riser, 9.5, 0.2)
t = t_of(1.75)
pitch = np.sin(2 * np.pi * np.cumsum(220 * (8 ** (t / 1.75))) / SR) * (t / 1.75) ** 2
synth.add(pitch, 10.0, 0.12)
rev_crash = crash(1.0)[::-1]
sfx.add(rev_crash, 10.75, 0.35)

# Drop A (12 - 20) and Drop B (22 - 28): four on the floor, supersaw stabs, 808.
def drop_section(start, bars, bright=False, chords=True):
    kicks = []
    for bar in range(bars):
        b = start + bar * 2.0
        ci = bar % 4
        for q in range(4):
            drums.add(K, b + q * BEAT, 0.95)
            kicks.append(b + q * BEAT)
            drums.add(HO, b + q * BEAT + 0.25, 0.16, 0.25)
        for q in [1, 3]:
            drums.add(CL, b + q * BEAT, 0.55, -0.05)
            send.add(CL, b + q * BEAT, 0.3)
        for h in np.arange(0, 2.0, 0.125):
            drums.add(HC, b + h, 0.06 + 0.04 * ((h * 8) % 2), -0.3)
        for k, dur in [(0.0, 0.7), (0.75, 0.7), (1.5, 0.45)]:
            music.add(bass808(ROOTS[ci], dur), b + k, 0.5)
        if chords:
            for k in [0.0, 0.75, 1.5]:
                freqs = CHORDS[ci] + ([f * 2 for f in CHORDS[ci][:2]] if bright else [])
                l, r = supersaw_chord(freqs, 0.6)
                env = np.minimum(1, t_of(0.6) / 0.004) * np.exp(-t_of(0.6) / 0.22)
                synth.add_st(l * env, r * env, b + k, 0.55)
                send.add((l + r) * env, b + k, 0.18)
            pad_l, pad_r = supersaw_chord([f / 2 for f in CHORDS[ci]], 2.0, 0.01, 3)
            env = np.minimum(1, t_of(2.0) / 0.05)
            synth.add_st(pad_l * env, pad_r * env, b, 0.2)
    return kicks


kicks_all = list(BUILD_WORDS)
sfx.add(IMP, DROP, 1.0)
sfx.add(CR, DROP, 0.5)
send.add(IMP, DROP, 0.35)
kicks_all += drop_section(12.0, 4)
for at in TAP_PANELS:
    sfx.add(whoosh(0.36), at - 0.2, 0.42, 0.3)
    sfx.add(impact(0.5), at, 0.35)
for at in TAP_FLIPS:
    sfx.add(tick(), at, 0.32)
for at in TAP_LOCKS:
    sfx.add(bell(1396.9, 1.0), at, 0.2, 0.15)
    send.add(bell(1396.9, 1.0), at, 0.15)

# Wheel (20 - 22): drums keep going, chords drop out, tension riser, ticks per face.
kicks_all += drop_section(20.0, 1, chords=False)
sfx.add(whoosh(0.3), BUTTON_IN - 0.15, 0.3)
sfx.add(impact(0.6), BUTTON_PRESS, 0.5)
sfx.add(sweep_noise(1.5, 500, 7000), 20.5, 0.25)
last = -1.0
for k in range(1, int(WHEEL_DEG / WHEEL_STEP) + 1):
    p = 1 - (1 - k * WHEEL_STEP / WHEEL_DEG) ** (1 / 5)  # inverse of power4.out
    at = WHEEL_SPIN[0] + p * (WHEEL_SPIN[1] - WHEEL_SPIN[0])
    if at - last >= 0.022:
        sfx.add(tick(), at, 0.26, 0.15 * ((-1) ** k))
        last = at

# Land, burst, confetti.
sfx.add(impact(1.4), LAND, 1.0)
sfx.add(CR, LAND, 0.55)
send.add(IMP, LAND, 0.35)
for f in [1046.5, 1318.5, 1568.0]:
    sfx.add(bell(f, 2.2), LAND + 0.02, 0.16)
    send.add(bell(f, 2.2), LAND + 0.02, 0.2)
shimmer = sweep_noise(0.7, 2000, 12000) * np.exp(-((t_of(0.7) - 0.45) ** 2) / 0.02)
sfx.add(shimmer, BURST, 0.35)
sfx.add(whoosh(0.5), BURST, 0.35)
for j in range(14):
    sfx.add(pop(j % 2 == 0), CONFETTI + j * 0.035 + 0.01 * (j % 3), 0.08, ((j * 37) % 11) / 5.5 - 1)

kicks_all += drop_section(22.0, 3, bright=True)

# Callback slams + strike.
for at in CALLBACK_WORDS:
    sfx.add(impact(0.6), at, 0.45)
sfx.add(whoosh(0.25), STRIKE - 0.05, 0.4)
sfx.add(impact(0.5), STRIKE, 0.35)

# End card: big hit, one more bar, hard stop for a clean loop.
sfx.add(impact(1.5), END_HIT, 1.0)
sfx.add(CR, END_HIT, 0.6)
send.add(IMP, END_HIT, 0.35)
kicks_all += drop_section(28.0, 1, bright=True)
sfx.add(bell(1046.5, 1.6), END_HIT + 0.5, 0.18)

# ---------------------------------------------------------------- mix
sc = sidechain(kicks_all)
synth.l *= sc
synth.r *= sc

pre = pre_drums.stereo() * 0.9 + pre_music.stereo() + pre_sfx.stereo()
pre += reverb(pre_send.stereo()) * 0.16 * np.max(np.abs(pre_send.stereo()))
pre = tape_stop(pre, *TAPE_STOP)

post = drums.stereo() * 0.9 + music.stereo() + synth.stereo() + sfx.stereo()
post += reverb(send.stereo()) * 0.2 * np.max(np.abs(send.stereo()))

mix = pre + post
mix = np.stack([filt(ch, 28, 17000, 2) for ch in mix])
cut = int(CUT * SR)
mix[:, cut:] *= np.exp(-np.arange(N - cut) / (0.03 * SR))
drive = 1.6
mix = np.tanh(drive * mix / np.max(np.abs(mix))) / np.tanh(drive)
mix *= 10 ** (-1 / 20) / np.max(np.abs(mix))

here = os.path.dirname(os.path.abspath(__file__))
out = os.path.join(here, "..", "assets", "audio", "soundtrack.wav")
pcm = (mix.T * 32767).astype("<i2")
with wave.open(out, "wb") as w:
    w.setnchannels(2)
    w.setsampwidth(2)
    w.setframerate(SR)
    w.writeframes(pcm.tobytes())
print("wrote", os.path.normpath(out))
