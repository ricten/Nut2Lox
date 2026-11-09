"""Erzeugt das Logo und die LoxBerry-Plugin-Icons im Stil von Senec2Lox.

Aufruf (aus dem Repo-Ordner):  python tools/make_logo.py
Benoetigt Pillow. Schrift: Arial Bold (Windows) bzw. DejaVu Sans Bold.

Ausgabe:
  docs/logo/logo_loxberry.png                 gruener, runder LoxBerry-Stil
  nut2lox/icons/icon_{64,128,256,512}.png     dasselbe als Plugin-Icons

Links eine USV (Batterie mit Blitz, Steckdosen), rechts der Miniserver (eigene, vereinfachte Darstellung
ohne Herstellerlogo, wie bei Senec2Lox), dazwischen ein Pfeil, darunter der Name "Nut2Lox".
"""
import os
from PIL import Image, ImageDraw, ImageFont, ImageOps

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SS = 4  # Supersampling

G_LIGHT, G_DARK = (141, 198, 63), (63, 125, 16)        # Gruenverlauf
G_TEXT = "#3f7d10"
LABEL = "Nut2Lox"
LABEL_WIDTH = 300      # Breite der Beschriftung im 512er-Raster
LABEL_BASE = 352       # Grundlinie

USV = (112, 122, 200, 262)                 # x1, y1, x2, y2 (USV-Gehaeuse)
MINISERVER = (270, 152, 426, 240)          # x1, y1, x2, y2 (gruenes Gehaeuse, Draufsicht)
LINK_Y = 196                               # Verbindungslinie (Mitte beider Geraete)
LINK_X = (208, 258)


def fit_bolt(points, center, scale):
    """Blitz um seine Bounding-Box-Mitte skalieren und auf `center` setzen."""
    xs, ys = [p[0] for p in points], [p[1] for p in points]
    ox, oy = (min(xs) + max(xs)) / 2, (min(ys) + max(ys)) / 2
    return [(round(center[0] + (x - ox) * scale, 1), round(center[1] + (y - oy) * scale, 1)) for x, y in points]


BOLT = fit_bolt([(222, 168), (184, 214), (208, 214), (196, 254), (244, 200), (219, 200)], (156, 176), 0.42)


def miniserver_shapes():
    """Miniserver als gruenes Gehaeuse (Draufsicht), identisch zu Senec2Lox.

    Formen: ("rrect", x1, y1, x2, y2, radius, rgba) | ("circle", cx, cy, r, rgba) | ("line", x1, y1, x2, y2, breite, rgba)
    """
    x1, y1, x2, y2 = MINISERVER
    white = (255, 255, 255, 255)
    green = (104, 190, 64, 255)
    dark = (38, 48, 38, 255)
    teal, orange, blue = (38, 172, 172, 255), (242, 140, 28, 255), (40, 120, 200, 255)
    red = (226, 42, 72, 255)
    sh = []
    sh.append(("rrect", x1 + 70, y1 - 15, x1 + 98, y1 + 6, 0, white))
    sh.append(("rrect", x1 + 72, y1 - 13, x1 + 96, y1 + 6, 0, teal))
    sh.append(("rrect", x1 + 116, y1 - 15, x1 + 134, y1 + 6, 0, white))
    sh.append(("rrect", x1 + 118, y1 - 13, x1 + 132, y1 + 6, 0, orange))
    for i, c in enumerate((orange, blue, white)):
        tx = x1 + 12 + i * 15
        sh.append(("rrect", tx - 2, y2 - 4, tx + 10, y2 + 16, 0, white))
        sh.append(("rrect", tx, y2 - 4, tx + 8, y2 + 14, 0, c))
    sh.append(("rrect", x1 + 78, y2 - 6, x2 - 6, y2 + 12, 0, white))
    sh.append(("rrect", x1 + 80, y2 - 4, x2 - 8, y2 + 10, 0, red))
    for i in range(12):
        sh.append(("circle", x1 + 88 + i * 5.6, y2 + 3, 1.6, white))
    sh.append(("rrect", x1 - 3, y1 - 3, x2 + 3, y2 + 3, 2, white))
    sh.append(("rrect", x1, y1, x2, y2, 0, green))
    sh.append(("rrect", x1 + 52, y1 + 36, x2 - 14, y1 + 46, 0, dark))
    sh.append(("rrect", x1 + 60, y1 + 39, x1 + 92, y1 + 43, 0, (200, 210, 200, 255)))
    sh.append(("rrect", x2 - 50, y1 + 39, x2 - 22, y1 + 43, 0, (120, 135, 120, 255)))
    return sh


def usv_shapes():
    """USV: weisses Gehaeuse, oben Statusleiste, Batterie mit Blitz, unten zwei Steckdosen und Lueftungsschlitze."""
    x1, y1, x2, y2 = USV
    white = (255, 255, 255, 255)
    g = (63, 125, 16, 255)
    gl = (63, 125, 16, 140)
    sh = []
    sh.append(("rrect", x1, y1, x2, y2, 14, white))                                   # Gehaeuse
    sh.append(("rrect", x1 + 14, y1 + 12, x2 - 14, y1 + 20, 4, g))                   # Statusleiste
    sh.append(("circle", x2 - 22, y1 + 31, 3.2, (104, 190, 64, 255)))                # Status-LED (an)
    # Batterie (Umriss mit Pol), darin der Blitz
    bx1, by1, bx2, by2 = x1 + 16, y1 + 38, x2 - 22, y1 + 84
    sh.append(("rrect", bx1, by1, bx2, by2, 6, g))
    sh.append(("rrect", bx1 + 5, by1 + 5, bx2 - 5, by2 - 5, 3, white))
    sh.append(("rrect", bx2, by1 + 14, bx2 + 7, by2 - 14, 2, g))
    # Steckdosen: zwei Kreise mit je zwei Kontakten
    for cx in (x1 + 29, x2 - 29):
        sh.append(("circle", cx, y2 - 34, 14, g))
        sh.append(("circle", cx, y2 - 34, 11, white))
        sh.append(("rrect", cx - 6, y2 - 38, cx - 3, y2 - 30, 0, g))
        sh.append(("rrect", cx + 3, y2 - 38, cx + 6, y2 - 30, 0, g))
    for i in range(3):                                                                # Lueftungsschlitze
        yy = y2 - 14 + i * 4
        sh.append(("rrect", x1 + 22, yy, x2 - 22, yy + 2, 1, gl))
    return sh


def _font(px: int) -> ImageFont.FreeTypeFont:
    for path in (r"C:\Windows\Fonts\arialbd.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
                 "/Library/Fonts/Arial Bold.ttf"):
        if os.path.exists(path):
            return ImageFont.truetype(path, px)
    return ImageFont.load_default()


def label_px() -> float:
    """Schriftgroesse, bei der LABEL genau LABEL_WIDTH breit ist."""
    f = _font(200)
    try:
        w = ImageDraw.Draw(Image.new("L", (10, 10))).textlength(LABEL, font=f)
    except Exception:
        return 60.0
    return 200 * LABEL_WIDTH / w if w else 60.0


def render(size: int) -> Image.Image:
    k = size * SS / 512.0
    S = size * SS
    D = int(480 * k)
    off = int(16 * k)

    # Kreis mit Radialverlauf (Lichtpunkt oben links)
    g = Image.radial_gradient("L").resize((int(1.8 * D), int(1.8 * D)), Image.BICUBIC)
    left, top = int(0.9 * D - 0.40 * D), int(0.9 * D - 0.34 * D)
    g = g.crop((left, top, left + D, top + D))
    disc = ImageOps.colorize(g, G_LIGHT, G_DARK).convert("RGBA")
    mask = Image.new("L", (D, D), 0)
    ImageDraw.Draw(mask).ellipse((0, 0, D - 1, D - 1), fill=255)
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    img.paste(disc, (off, off), mask)

    ov = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(ov)

    def r(x1, y1, x2, y2):
        return [x1 * k, y1 * k, x2 * k, y2 * k]

    # dezenter Glanz oben und feiner Innenring
    d.ellipse(r(60, 24, 452, 250), fill=(255, 255, 255, 34))
    d.ellipse(r(34, 34, 478, 478), outline=(255, 255, 255, 70), width=max(1, int(3 * k)))

    def draw(shapes):
        for sh in shapes:
            if sh[0] == "rrect":
                _, x1, y1, x2, y2, rad, c = sh
                d.rounded_rectangle(r(x1, y1, x2, y2), radius=rad * k, fill=c)
            elif sh[0] == "circle":
                _, cx, cy, rr, c = sh
                d.ellipse(r(cx - rr, cy - rr, cx + rr, cy + rr), fill=c)
            else:
                _, x1, y1, x2, y2, w, c = sh
                d.line([(x1 * k, y1 * k), (x2 * k, y2 * k)], fill=c, width=max(1, int(w * k)))

    draw(usv_shapes())
    # Blitz in der Batterie der USV
    d.polygon([(x * k, y * k) for x, y in BOLT], fill=G_TEXT)
    draw(miniserver_shapes())
    # Verbindung mit Pfeilspitze
    d.line([(LINK_X[0] * k, LINK_Y * k), ((LINK_X[1] - 4) * k, LINK_Y * k)], fill="white", width=int(7 * k))
    d.polygon([((LINK_X[1] + 8) * k, LINK_Y * k), ((LINK_X[1] - 6) * k, (LINK_Y - 11) * k),
               ((LINK_X[1] - 6) * k, (LINK_Y + 11) * k)], fill="white")
    # Beschriftung: "Nut2Lox" in einer Zeile
    font = _font(int(label_px() * k))
    d.text((256 * k, LABEL_BASE * k), LABEL, font=font, fill="white", anchor="ms")
    img = Image.alpha_composite(img, ov)
    return img.resize((size, size), Image.LANCZOS)


if __name__ == "__main__":
    logo_dir = os.path.join(ROOT, "docs", "logo")
    icon_dir = os.path.join(ROOT, "nut2lox", "icons")
    os.makedirs(logo_dir, exist_ok=True)
    os.makedirs(icon_dir, exist_ok=True)
    render(512).save(os.path.join(logo_dir, "logo_loxberry.png"))
    for s in (64, 128, 256, 512):
        render(s).save(os.path.join(icon_dir, "icon_%d.png" % s))
    print("Logo und Icons geschrieben")
