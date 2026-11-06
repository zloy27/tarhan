from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(r"C:\Users\zloy27\.codex\visualizations\2026\11\05\tarhan-season-gallery-links")
periods = ["2021-2022", "2022-2023", "2023-2024", "2024-2025", "2025-2026"]
font_path = r"C:\Windows\Fonts\arial.ttf"
bold_path = r"C:\Windows\Fonts\arialbd.ttf"
font = ImageFont.truetype(font_path, 22)
bold = ImageFont.truetype(bold_path, 27)

cards = []
for period in periods:
    image = Image.open(ROOT / f"season-{period}.png").convert("RGB")
    image.thumbnail((900, 305), Image.Resampling.LANCZOS)
    card = Image.new("RGB", (940, 365), "white")
    draw = ImageDraw.Draw(card)
    draw.text((20, 14), f"Сезон {period.replace('-', '–')}", font=bold, fill="#263342")
    card.paste(image, (20, 54))
    draw.rectangle((0, 0, 939, 364), outline="#D8C4B3", width=2)
    cards.append(card)

canvas = Image.new("RGB", (1900, 1200), "#F7F3EA")
draw = ImageDraw.Draw(canvas)
draw.text((30, 20), "Кнопки фотоальбомов пяти сезонов на WP Local", font=bold, fill="#263342")
positions = [(10, 70), (950, 70), (10, 445), (950, 445), (480, 820)]
for card, position in zip(cards, positions):
    canvas.paste(card, position)
canvas.save(ROOT / "all-season-gallery-buttons.png", quality=94)
print(ROOT / "all-season-gallery-buttons.png")
