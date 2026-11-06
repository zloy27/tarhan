from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


SOURCE_DOCX = Path(r"C:\Users\zloy27\OneDrive\Рабочий стол\сайт исфнимк\требования-ИТОГ-с-подтверждениями.docx")
OUTPUT_DOCX = Path(r"C:\Users\zloy27\OneDrive\Рабочий стол\сайт исфнимк\требования-ИТОГ-с-подтверждениями-и-фотоальбомами.docx")
SCREENSHOT = Path(r"C:\Users\zloy27\.codex\visualizations\2026\11\05\tarhan-season-gallery-links\all-season-gallery-buttons.png")
TITLE = "Дополнительная правка. Фотоальбомы пяти сезонов"


def set_font(run, size=11, bold=False, italic=False, color="263342"):
    run.font.name = "Georgia"
    run._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), "Georgia")
    run._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), "Georgia")
    run.font.size = Pt(size)
    run.bold = bold
    run.italic = italic
    run.font.color.rgb = RGBColor.from_string(color)


def add_labeled(document, label, text):
    paragraph = document.add_paragraph()
    paragraph.paragraph_format.space_after = Pt(4)
    set_font(paragraph.add_run(label), bold=True)
    set_font(paragraph.add_run(text))


document = Document(SOURCE_DOCX)
if any(paragraph.text.strip() == TITLE for paragraph in document.paragraphs):
    raise SystemExit("Evidence section already exists")

document.add_page_break()
heading = document.add_paragraph()
heading.paragraph_format.space_after = Pt(9)
set_font(heading.add_run(TITLE), size=14, bold=True, color="9A6721")

add_labeled(document, "Раздел: ", "Архив сезонов / страницы сезонов")
add_labeled(document, "ТЗ: ", "Добавить под фотографиями кнопку перехода в полный фотоальбом VK; для каждого сезона использовать отдельную ссылку заказчика.")
add_labeled(document, "Проверка: ", "На страницах сезонов 2021–2022, 2022–2023, 2023–2024, 2024–2025 и 2025–2026 отображается кнопка «Смотреть все фотографии в VK». Все пять адресов сверены автоматически.")
add_labeled(document, "Статус: ", "Выполнено и проверено 05.11.2026 на WP Local.")
add_labeled(document, "Редактирование: ", "Ссылка доступна в редакторе страницы сезона WordPress в поле «Ссылка на полный фотоальбом сезона».")

picture = document.add_paragraph()
picture.alignment = WD_ALIGN_PARAGRAPH.CENTER
picture.paragraph_format.space_before = Pt(7)
picture.paragraph_format.space_after = Pt(5)
picture.add_run().add_picture(str(SCREENSHOT), width=Inches(6.25))

caption = document.add_paragraph()
caption.alignment = WD_ALIGN_PARAGRAPH.CENTER
caption.paragraph_format.space_after = Pt(0)
set_font(caption.add_run("Результат выполнения: кнопки перехода в фотоальбомы на всех пяти страницах сезонов WP Local."), size=10, italic=True, color="777777")

document.save(OUTPUT_DOCX)
print(OUTPUT_DOCX)
