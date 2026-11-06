from datetime import datetime
from pathlib import Path
import subprocess

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(r"C:\Users\zloy27\OneDrive\Рабочий стол\сайт исфнимк\tarhan - workplace\tarhan")
OUT = Path(r"C:\Users\zloy27\.codex\visualizations\2026\11\05\tarhan-requirements-evidence-all")


def font(size, bold=False):
    name = "arialbd.ttf" if bold else "arial.ttf"
    return ImageFont.truetype(str(Path(r"C:\Windows\Fonts") / name), size)


def season_collage():
    files = [OUT / f"season-watch-{year}.png" for year in (
        "2021-2022", "2022-2023", "2023-2024", "2024-2025", "2025-2026"
    )]
    images = [Image.open(path).convert("RGB") for path in files]
    width = max(image.width for image in images)
    gap = 14
    header = 58
    height = header + sum(image.height for image in images) + gap * (len(images) - 1)
    canvas = Image.new("RGB", (width, height), "white")
    draw = ImageDraw.Draw(canvas)
    draw.text((20, 14), "Фактические блоки «Где посмотреть» для сезонов 1–5", fill="#202020", font=font(26, True))
    y = header
    for image in images:
        canvas.paste(image, (0, y))
        y += image.height + gap
    canvas.save(OUT / "season-links-1-5.png")


def organizer_test_evidence():
    command = [
        "powershell.exe", "-NoProfile", "-ExecutionPolicy", "Bypass", "-File",
        str(ROOT / "tests" / "invoke-local-php.ps1"),
        "-Script", "tests/verify-organizers.php",
    ]
    completed = subprocess.run(command, cwd=ROOT, text=True, capture_output=True, encoding="utf-8")
    output = (completed.stdout + completed.stderr).strip()
    if completed.returncode != 0:
        raise RuntimeError(output)
    lines = [line.strip() for line in output.splitlines() if line.strip()]
    title = "Проверка таблицы участников и XLSX-выгрузки"
    timestamp = datetime.now().strftime("%d.%m.%Y %H:%M")
    width = 1500
    padding = 38
    line_height = 42
    height = padding * 2 + 58 + 34 + line_height * len(lines)
    canvas = Image.new("RGB", (width, height), "#111827")
    draw = ImageDraw.Draw(canvas)
    draw.text((padding, padding), title, fill="#ffffff", font=font(34, True))
    draw.text((padding, padding + 50), f"Фактический запуск: {timestamp}", fill="#9ca3af", font=font(22))
    y = padding + 100
    for line in lines:
        color = "#86efac" if line.startswith("PASS:") else "#f8fafc"
        draw.text((padding, y), line, fill=color, font=font(24))
        y += line_height
    canvas.save(OUT / "organizer-export-verification.png")


season_collage()
organizer_test_evidence()
