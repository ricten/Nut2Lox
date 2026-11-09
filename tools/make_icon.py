"""Erzeugt die runden LoxBerry-Plugin-Icons nut2lox/icons/icon_{64,128,256,512}.png und docs/logo/logo_loxberry.png.

Aufruf (aus dem Repo-Ordner):  python tools/make_icon.py
Benoetigt Pillow. Gruener LoxBerry-Stil: runde Flaeche, Batterie mit Blitz und Schriftzug NUT.
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


# Quadratische Zeichnung, danach auf einen Kreis zugeschnitten
img = Image.new("RGBA", (S, S), GREEN)
d = ImageDraw.Draw(img)
d.rectangle((0, S // 2, S, S), fill=DARK)

# Batterie
bx0, by0, bx1, by1 = 190, 215, 740, 575
d.rounded_rectangle((bx0, by0, bx1, by1), radius=60, outline=WHITE, width=34)
d.rounded_rectangle((bx1 + 8, 330, bx1 + 78, 460), radius=26, fill=WHITE)
# Blitz
bolt = [(485, 255), (375, 415), (447, 415), (410, 535), (575, 350), (493, 350), (530, 255)]
d.polygon(bolt, fill=YELLOW)

# Schriftzug
f = font(250)
text = "NUT"
w = d.textlength(text, font=f)
d.text(((S - w) / 2, 650), text, font=f, fill=WHITE)

# Kreis-Maske (hochaufgeloest gezeichnet, damit der Rand glatt wird)
mask = Image.new("L", (S, S), 0)
ImageDraw.Draw(mask).ellipse((0, 0, S - 1, S - 1), fill=255)
img.putalpha(mask)
ring = ImageDraw.Draw(img)
ring.ellipse((6, 6, S - 7, S - 7), outline=DARK, width=12)

out = os.path.join(ROOT, "nut2lox", "icons")
os.makedirs(out, exist_ok=True)
for size in (64, 128, 256, 512):
    img.resize((size, size), Image.LANCZOS).save(os.path.join(out, "icon_%d.png" % size))
logo = os.path.join(ROOT, "docs", "logo")
os.makedirs(logo, exist_ok=True)
img.resize((512, 512), Image.LANCZOS).save(os.path.join(logo, "logo_loxberry.png"))
print("Icons geschrieben:", out)
