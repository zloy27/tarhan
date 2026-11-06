from pathlib import Path
import argparse

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, RGBColor
from docx.text.paragraph import Paragraph


SOURCE = Path(r"C:\Users\zloy27\OneDrive\Рабочий стол\сайт исфнимк\требования")
OUTPUT = Path(r"C:\Users\zloy27\OneDrive\Рабочий стол\сайт исфнимк\требования-обновлено.docx")
SCREENSHOT_OUTPUT = Path(r"C:\Users\zloy27\OneDrive\Рабочий стол\сайт исфнимк\требования-обновлено-со-скриншотами.docx")
EVIDENCE = Path(r"C:\Users\zloy27\.codex\visualizations\2026\11\05\tarhan-requirements-evidence")


def replace_after_heading(document, heading, replacements):
    start = next(i for i, paragraph in enumerate(document.paragraphs) if paragraph.text.startswith(heading))
    end = next(
        (i for i in range(start + 1, len(document.paragraphs)) if document.paragraphs[i].text.startswith("Правка №")),
        len(document.paragraphs),
    )
    for prefix, value in replacements.items():
        paragraph = next(
            paragraph
            for paragraph in document.paragraphs[start:end]
            if paragraph.text.startswith(prefix)
        )
        paragraph.text = value


def clear_paragraph(paragraph):
    for child in list(paragraph._p):
        if child.tag != qn("w:pPr"):
            paragraph._p.remove(child)


def set_picture(paragraph, image_path, alt_text, width=4.8):
    clear_paragraph(paragraph)
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    inline_shape = paragraph.add_run().add_picture(str(image_path), width=Inches(width))
    doc_pr = inline_shape._inline.docPr
    doc_pr.set("descr", alt_text)
    doc_pr.set("title", alt_text)


def insert_caption_after(paragraph, text):
    new_p = OxmlElement("w:p")
    paragraph._p.addnext(new_p)
    caption = Paragraph(new_p, paragraph._parent)
    run = caption.add_run(text)
    run.italic = True
    run.font.color.rgb = RGBColor(0x72, 0x72, 0x72)
    return caption


def section_bounds(document, heading):
    start = next(i for i, paragraph in enumerate(document.paragraphs) if paragraph.text.startswith(heading))
    end = next(
        (i for i in range(start + 1, len(document.paragraphs)) if document.paragraphs[i].text.startswith("Правка №")),
        len(document.paragraphs),
    )
    return start, end


parser = argparse.ArgumentParser()
parser.add_argument("stage", choices=("season", "portrait", "screenshots"))
args = parser.parse_args()

document = Document(OUTPUT if OUTPUT.exists() else SOURCE)

if args.stage == "season":
    replace_after_heading(
        document,
        "Правка № 23.",
        {
            "Статус:": "Статус: Выполнено 05.11.2026. Пятый сезон обозначен как 2025–2026; первый сезон — 2021–2022.",
            "Основание:": "Основание: ответ заказчика от 05.11.2026 и проверка опубликованной страницы архива.",
            "Скриншот": "Скриншот выполнен 05.11.2026: в архиве показаны сезоны 2025–2026 и 2024–2025.",
        },
    )
elif args.stage == "portrait":
    replace_after_heading(
        document,
        "Правка № 18.",
        {
            "Статус:": "Статус: Выполнено 05.11.2026. Использован ранее согласованный памятный портрет в траурной рамке.",
            "Основание:": "Основание: ответ заказчика от 05.11.2026 и ранее созданный файл «Памятный портрет в траурной рамке.png».",
            "Место правки": "Место правки на сайте: карточка Фаяза Шариповича Хузина в блоке жюри.",
        },
    )
elif args.stage == "screenshots":
    start, end = section_bounds(document, "Правка № 18.")
    picture_paragraph = next(
        paragraph
        for paragraph in document.paragraphs[start:end]
        if paragraph._p.xpath(".//w:drawing")
    )
    set_picture(
        picture_paragraph,
        EVIDENCE / "khuzin-memorial.png",
        "Карточка Фаяза Шариповича Хузина с памятным портретом",
    )
    caption = next(
        paragraph
        for paragraph in document.paragraphs[start:end]
        if paragraph.text.startswith("Место правки на сайте:")
    )
    caption.text = "Результат выполнения: карточка Фаяза Шариповича Хузина с памятным портретом."
    for run in caption.runs:
        run.italic = True
        run.font.color.rgb = RGBColor(0x72, 0x72, 0x72)

    start, end = section_bounds(document, "Правка № 23.")
    screenshot_note = next(
        paragraph
        for paragraph in document.paragraphs[start:end]
        if paragraph.text.startswith("Скриншот выполнен")
    )
    set_picture(
        screenshot_note,
        EVIDENCE / "season-2025-2026.png",
        "Карточка сезона 2025–2026 в архиве",
    )
    insert_caption_after(
        screenshot_note,
        "Результат выполнения: в архиве опубликована карточка сезона 2025–2026.",
    )

save_path = SCREENSHOT_OUTPUT if args.stage == "screenshots" else OUTPUT
document.save(save_path)
print(save_path)
