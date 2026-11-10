"""Erzeugt das Logo und die LoxBerry-Plugin-Icons im Stil von Senec2Lox.

Aufruf (aus dem Repo-Ordner):  python tools/make_logo.py
Benoetigt Pillow. Schrift: Arial Bold (Windows) bzw. DejaVu Sans Bold.

Ausgabe:
  docs/logo/nut2lox.png                 gruener, runder LoxBerry-Stil
  nut2lox/icons/icon_{64,128,256,512}.png     dasselbe als Plugin-Icons

Links eine Rack-USV (19 Zoll, Display mit Blitz), rechts der Miniserver (eigene, vereinfachte Darstellung
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
OX, OY = -6, 11        # Verschiebung der ganzen Gruppe (Geraete + Name), damit sie im Kreis mittig sitzt

USV = (98, 154, 216, 238)                  # x1, y1, x2, y2 (Rack-USV: zwei Hoeheneinheiten)
MINISERVER = (270, 152, 426, 240)          # x1, y1, x2, y2 (gruenes Gehaeuse, Draufsicht)
LINK_Y = 196                               # Verbindungslinie (Mitte beider Geraete)
LINK_X = (226, 258)


def fit_bolt(points, center, scale):
    """Blitz um seine Bounding-Box-Mitte skalieren und auf `center` setzen."""
    xs, ys = [p[0] for p in points], [p[1] for p in points]
    ox, oy = (min(xs) + max(xs)) / 2, (min(ys) + max(ys)) / 2
    return [(round(center[0] + (x - ox) * scale, 1), round(center[1] + (y - oy) * scale, 1)) for x, y in points]


BOLT = fit_bolt([(222, 168), (184, 214), (208, 214), (196, 254), (244, 200), (219, 200)], (150, 174), 0.30)


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
    """Rack-USV (19-Zoll, zwei Einheiten): oben die USV mit Display, Einschalter und Lueftungsschlitzen,
    unten ein Batteriemodul mit Griff und Lueftungsschlitzen. Befestigungsohren mit Schraubenloechern an den Seiten."""
    x1, y1, x2, y2 = USV
    white = (255, 255, 255, 255)
    g = (63, 125, 16, 255)
    gl = (63, 125, 16, 150)
    lcd = (38, 70, 20, 255)
    sh = []
    h = 38                                   # Hoehe einer Einheit
    units = [(y1, y1 + h), (y2 - h, y2)]
    for (ya, yb) in units:
        sh.append(("rrect", x1, ya, x2, yb, 5, white))                               # Frontblende
        sh.append(("rrect", x1, ya, x1 + 12, yb, 4, (214, 226, 205, 255)))           # Ohr links
        sh.append(("rrect", x2 - 12, ya, x2, yb, 4, (214, 226, 205, 255)))           # Ohr rechts
        for yy in (ya + 9, yb - 9):                                                  # Schraubenloecher
            sh.append(("circle", x1 + 6, yy, 2.6, g))
            sh.append(("circle", x2 - 6, yy, 2.6, g))
    # obere Einheit: Einschalter, Display, Lueftungsschlitze
    ya, yb = units[0]
    sh.append(("circle", x1 + 24, (ya + yb) / 2, 7, g))
    sh.append(("circle", x1 + 24, (ya + yb) / 2, 4.5, white))
    sh.append(("rrect", x1 + 22, (ya + yb) / 2 - 7, x1 + 26, (ya + yb) / 2 - 1, 0, g))
    sh.append(("rrect", x1 + 38, ya + 8, x1 + 66, yb - 8, 3, lcd))                   # Display (Blitz folgt in render)
    for i in range(5):
        yy = ya + 9 + i * 5
        sh.append(("rrect", x1 + 76, yy, x2 - 18, yy + 2, 1, gl))
    # untere Einheit: Griff und Lueftungsschlitze
    ya, yb = units[1]
    sh.append(("rrect", x1 + 26, ya + 7, x2 - 26, ya + 12, 2.5, g))                  # Griff
    for i in range(4):
        yy = ya + 18 + i * 4.5
        sh.append(("rrect", x1 + 26, yy, x2 - 26, yy + 2, 1, gl))
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
                d.rounded_rectangle(r(x1 + OX, y1 + OY, x2 + OX, y2 + OY), radius=rad * k, fill=c)
            elif sh[0] == "circle":
                _, cx, cy, rr, c = sh
                d.ellipse(r(cx - rr + OX, cy - rr + OY, cx + rr + OX, cy + rr + OY), fill=c)
            else:
                _, x1, y1, x2, y2, w, c = sh
                d.line([((x1 + OX) * k, (y1 + OY) * k), ((x2 + OX) * k, (y2 + OY) * k)], fill=c, width=max(1, int(w * k)))

    draw(usv_shapes())
    # Blitz im Display der USV
    d.polygon([((x + OX) * k, (y + OY) * k) for x, y in BOLT], fill=(255, 214, 64, 255))
    draw(miniserver_shapes())
    # Verbindung mit Pfeilspitze
    ly = (LINK_Y + OY) * k
    d.line([((LINK_X[0] + OX) * k, ly), ((LINK_X[1] - 4 + OX) * k, ly)], fill="white", width=int(7 * k))
    d.polygon([((LINK_X[1] + 8 + OX) * k, ly), ((LINK_X[1] - 6 + OX) * k, ly - 11 * k),
               ((LINK_X[1] - 6 + OX) * k, ly + 11 * k)], fill="white")
    # Beschriftung: "Nut2Lox" in einer Zeile
    font = _font(int(label_px() * k))
    d.text((256 * k, (LABEL_BASE + OY) * k), LABEL, font=font, fill="white", anchor="ms")
    img = Image.alpha_composite(img, ov)
    return img.resize((size, size), Image.LANCZOS)


if __name__ == "__main__":
    logo_dir = os.path.join(ROOT, "docs", "logo")
    icon_dir = os.path.join(ROOT, "nut2lox", "icons")
    os.makedirs(logo_dir, exist_ok=True)
    os.makedirs(icon_dir, exist_ok=True)
    render(512).save(os.path.join(logo_dir, "nut2lox.png"))
    for s in (64, 128, 256, 512):
        render(s).save(os.path.join(icon_dir, "icon_%d.png" % s))
    print("Logo und Icons geschrieben")
