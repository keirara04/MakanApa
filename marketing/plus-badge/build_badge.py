"""Build the Plus membership badge from the existing Nasi vector artwork."""
from pathlib import Path
import math
import re
import zipfile
import xml.etree.ElementTree as ET

import cairosvg
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from PIL import Image

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
FONT = TTFont('/System/Library/Fonts/Supplemental/Arial Rounded Bold.ttf')
GLYPHS = FONT.getGlyphSet()
CMAP = FONT.getBestCmap()
UNITS = FONT['head'].unitsPerEm


def lettering(label, cx, baseline, size, fill, stroke=None, stroke_width=0):
    scale = size / UNITS
    width = sum(GLYPHS[CMAP[ord(c)]].width for c in label) * scale
    x = cx - width / 2
    strokes = []
    paths = []
    for character in label:
        glyph = GLYPHS[CMAP[ord(character)]]
        pen = SVGPathPen(GLYPHS)
        glyph.draw(pen)
        shape = f'd="{pen.getCommands()}" transform="translate({x:.3f} {baseline}) scale({scale:.6f} {-scale:.6f})"'
        if stroke:
            strokes.append(f'<path {shape} fill="none" stroke="{stroke}" stroke-width="{stroke_width / scale}" stroke-linejoin="round"/>')
        paths.append(f'<path {shape} fill="{fill}"/>')
        x += glyph.width * scale
    return ''.join(strokes + paths)


def rosette(radius):
    points = []
    for i in range(240):
        angle = 2 * math.pi * i / 240 - math.pi / 2
        r = radius + 8 * math.cos(20 * angle)
        points.append(f'{512 + r * math.cos(angle):.2f},{470 + r * math.sin(angle):.2f}')
    return 'M' + ' L'.join(points) + ' Z'


def sparkle(x, y, size, fill='#F4B942'):
    return f'<path d="M{x} {y-size} Q{x+size*.2} {y-size*.2} {x+size} {y} Q{x+size*.2} {y+size*.2} {x} {y+size} Q{x-size*.2} {y+size*.2} {x-size} {y} Q{x-size*.2} {y-size*.2} {x} {y-size}Z" fill="{fill}" stroke="#29201D" stroke-width="5" stroke-linejoin="round"/>'


mascot = ET.parse(ROOT / 'MakanApa_Mascot_SVG_Set/MakanApa_Mascot_Celebrate.svg').getroot()
NS = '{http://www.w3.org/2000/svg}'
# Keep the original character paths, omitting tiny cap lettering and its shadow.
for element in list(mascot.iter()):
    element.attrib.pop('filter', None)
    for child in list(element):
        if child.tag in (NS + 'text', NS + 'defs', NS + 'title', NS + 'desc'):
            element.remove(child)
character = ''.join(ET.tostring(child, encoding='unicode') for child in mascot)
character = re.sub(r'ns\d+:', '', character)
character = re.sub(r' xmlns:ns\d+="[^"]+"', '', character)

svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024" role="img" aria-labelledby="badge-title badge-desc">
<title id="badge-title">MakanApa Plus membership badge</title>
<desc id="badge-desc">A gold collectible medallion with the cheerful Nasi rice mascot, red cap, cream MakanApa lettering and a bold red PLUS ribbon. Transparent background. All lettering is vector paths.</desc>
<defs>
  <linearGradient id="gold" x1=".15" y1="0" x2=".85" y2="1"><stop stop-color="#FFF3B8"/><stop offset=".28" stop-color="#F4B942"/><stop offset=".52" stop-color="#FFE9A0"/><stop offset="1" stop-color="#C98B29"/></linearGradient>
  <linearGradient id="red" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#FA6A4E"/><stop offset=".45" stop-color="#E94B35"/><stop offset="1" stop-color="#C83728"/></linearGradient>
  <radialGradient id="paper" cx=".45" cy=".3" r=".8"><stop stop-color="#FFFAEA"/><stop offset="1" stop-color="#F8E5B7"/></radialGradient>
  <clipPath id="inner"><circle cx="512" cy="470" r="328"/></clipPath>
</defs>
<g stroke-linejoin="round">
  <path d="{rosette(398)}" fill="url(#gold)" stroke="#29201D" stroke-width="15"/>
  <path d="{rosette(383)}" fill="none" stroke="#FFF3BB" stroke-width="5"/>
  <circle cx="512" cy="470" r="364" fill="#CE8D2B" stroke="#29201D" stroke-width="8"/>
  <circle cx="512" cy="470" r="350" fill="url(#red)" stroke="#FFEBAC" stroke-width="6"/>
  <circle cx="512" cy="470" r="329" fill="url(#paper)" stroke="#29201D" stroke-width="8"/>
  <g clip-path="url(#inner)" fill="#EBCF87" opacity=".28">
    <path d="M512 495L100 210L244 125Z M512 495L435 100L588 100Z M512 495L800 125L950 255Z M512 495L950 410L950 555Z M512 495L80 425L80 570Z"/>
  </g>
  <path d="M208 281Q512 126 816 281L798 346Q512 219 226 346Z" fill="url(#red)" stroke="#29201D" stroke-width="9"/>
  <path d="M229 277Q512 142 795 277" fill="none" stroke="#FFAE88" stroke-width="6" stroke-linecap="round"/>
  {lettering('MakanApa', 512, 282, 78, '#FFF4DD', '#29201D', 8)}
  <g clip-path="url(#inner)"><g transform="translate(88 138) scale(.80)">{character}</g></g>
  {sparkle(252, 413, 26)}
  {sparkle(784, 431, 28)}
  {sparkle(237, 556, 15)}
  <circle cx="794" cy="554" r="9" fill="#E94B35"/>
  <circle cx="274" cy="354" r="7" fill="#F4B942"/>
  <g transform="translate(512 107)">
    <rect x="-44" y="-44" width="88" height="88" rx="25" transform="rotate(45)" fill="url(#red)" stroke="#29201D" stroke-width="9"/>
    <path d="M-23 0H23 M0-23V23" stroke="#FFF4DD" stroke-width="14" stroke-linecap="round"/>
  </g>
  <path d="M194 698L69 734L119 799L78 870L221 841Z" fill="url(#red)" stroke="#29201D" stroke-width="12"/>
  <path d="M830 698L955 734L905 799L946 870L803 841Z" fill="url(#red)" stroke="#29201D" stroke-width="12"/>
  <path d="M178 723L94 746L140 799L103 848L209 824 M846 723L930 746L884 799L921 848L815 824" fill="none" stroke="#F4B942" stroke-width="7"/>
  <path d="M171 698L215 754L171 865L147 787Z M853 698L809 754L853 865L877 787Z" fill="#9E2B22" stroke="#29201D" stroke-width="8"/>
  <path d="M171 697Q512 651 853 697L873 873Q512 833 151 873Z" fill="url(#gold)" stroke="#29201D" stroke-width="13"/>
  <path d="M185 715Q512 675 839 715L855 851Q512 814 169 851Z" fill="url(#red)" stroke="#29201D" stroke-width="5"/>
  <path d="M207 722Q512 688 817 722" fill="none" stroke="#FFB08C" stroke-width="6" stroke-linecap="round"/>
  {lettering('PLUS', 512, 820, 126, '#FFF4DD', '#29201D', 10)}
  {sparkle(266, 776, 23, '#FFE397')}
  {sparkle(758, 776, 23, '#FFE397')}
</g>
</svg>'''

destination = HERE / 'makanapa-plus-badge.svg'
destination.write_text(svg)
for size in (2048, 512):
    cairosvg.svg2png(bytestring=svg.encode(), write_to=str(HERE / f'makanapa-plus-badge-{size}.png'), output_width=size, output_height=size)
preview = Image.new('RGBA', (1200, 1200), '#FFF4DD')
badge = Image.open(HERE / 'makanapa-plus-badge-2048.png').resize((1040, 1040), Image.Resampling.LANCZOS)
preview.alpha_composite(badge, (80, 80))
preview.convert('RGB').save(HERE / 'preview.jpg', quality=94)
(HERE / 'README.md').write_text('''# MakanApa Plus badge

- `makanapa-plus-badge.svg`: self-contained vector with outlined lettering; no fonts or embedded bitmaps required.
- `makanapa-plus-badge-2048.png`: transparent high-resolution export.
- `makanapa-plus-badge-512.png`: transparent app-size export.
- `preview.jpg`: cream presentation background, not a transparent asset.

Design: gold membership medallion, sambal-red PLUS ribbon, existing Celebrate Nasi vector mascot. Separate from the university ambassador crest. The source app and existing assets were not modified.

Rebuild: `DYLD_FALLBACK_LIBRARY_PATH=/opt/homebrew/lib python3 marketing/plus-badge/build_badge.py` (CairoSVG, Pillow and fontTools; macOS Arial Rounded Bold font).
''')
with zipfile.ZipFile(HERE / 'makanapa-plus-badge.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
    for name in ('makanapa-plus-badge.svg', 'makanapa-plus-badge-2048.png', 'makanapa-plus-badge-512.png', 'README.md'):
        archive.write(HERE / name, name)
print(f'Created {destination}')
