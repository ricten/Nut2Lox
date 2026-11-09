"""Erzeugt die LoxBerry-Plugin-Icons nut/icons/icon_{64,128,256,512}.png.

Aufruf (aus dem Repo-Ordner):  python tools/make_icon.py
Benoetigt Pillow. Gruener LoxBerry-Stil: Batterie mit Blitz und Schriftzug NUT.
"""
import os
from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
S = 1024    # Zeichengroesse (wird verkleinert = Kantenglaettung)
GREEN, DARK, WHITE, YELLOW = "#6dac20", "#4a7c12", "#ffffff", "#ffd23f"


def font(size):
    for f in ("C:/Windows/Fonts/arialbd.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"):
        if os.path.isfile(f):
            return ImageFont.truetype(f, size)
    return ImageFont.load_default()


img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
d = ImageDraw.Draw(img)
d.rounded_rectangle((0, 0, S - 1, S - 1), radius=190, fill=GREEN)
d.rounded_rectangle((0, S // 2, S - 1, S - 1), radius=190, fill=DARK)
d.rectangle((0, S // 2, S - 1, S // 2 + 200), fill=DARK)
d.rounded_rectangle((0, 0, S - 1, S - 1), radius=190, outline=DARK, width=10)

# Batterie
bx0, by0, bx1, by1 = 200, 190, 760, 560
d.rounded_rectangle((bx0, by0, bx1, by1), radius=60, outline=WHITE, width=34)
d.rounded_rectangle((bx1 + 8, 305, bx1 + 78, 445), radius=26, fill=WHITE)
# Blitz
bolt = [(500, 230), (385, 400), (460, 400), (420, 525), (590, 335), (505, 335), (545, 230)]
d.polygon(bolt, fill=YELLOW)

# Schriftzug
f = font(260)
text = "NUT"
w = d.textlength(text, font=f)
d.text(((S - w) / 2, 640), text, font=f, fill=WHITE)

out = os.path.join(ROOT, "nut", "icons")
os.makedirs(out, exist_ok=True)
for size in (64, 128, 256, 512):
    img.resize((size, size), Image.LANCZOS).save(os.path.join(out, "icon_%d.png" % size))
print("Icons geschrieben:", out)
