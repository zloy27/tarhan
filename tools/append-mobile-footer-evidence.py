from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


DOCX = Path(r"C:\Users\zloy27\OneDrive\Рабочий стол\сайт исфнимк\требования-ИТОГ-с-подтверждениями.docx")
SCREENSHOT = Path(r"C:\Users\zloy27\.codex\visualizations\2026\11\05\tarhan-mobile-footer\footer-mobile-wp-local.png")
TITLE = "Дополнительная правка. Цвет подвала на мобильной версии"


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
    paragraph.paragraph_format.space_after = Pt(5)
    set_font(paragraph.add_run(label), bold=True)
    set_font(paragraph.add_run(text))


document = Document(DOCX)
if any(paragraph.text.strip() == TITLE for paragraph in document.paragraphs):
    raise SystemExit("Evidence section already exists")

document.add_page_break()
heading = document.add_paragraph()
heading.paragraph_format.space_after = Pt(10)
set_font(heading.add_run(TITLE), size=14, bold=True, color="9A6721")

add_labeled(document, "Раздел: ", "Подвал сайта / мобильная версия")
add_labeled(document, "ТЗ: ", "Применить выбранный цвет фона только на ширине до 576 пикселей и перенести изменение в активную тему WordPress на Local.")
add_labeled(document, "Проверка: ", "На ширине 390 пикселей подвал использует цвет #5B452C; на большей ширине правило не применяется.")
add_labeled(document, "Статус: ", "Выполнено и проверено 05.11.2026 на странице жюри в WP Local.")
add_labeled(document, "Основание: ", "Фактическая браузерная проверка подключённого мобильного стиля и вычисленного цвета rgb(91, 69, 44).")

picture = document.add_paragraph()
picture.alignment = WD_ALIGN_PARAGRAPH.CENTER
picture.paragraph_format.space_before = Pt(8)
picture.paragraph_format.space_after = Pt(6)
picture.add_run().add_picture(str(SCREENSHOT), width=Inches(2.8))

caption = document.add_paragraph()
caption.alignment = WD_ALIGN_PARAGRAPH.CENTER
caption.paragraph_format.space_after = Pt(0)
set_font(caption.add_run("Результат выполнения: мобильный подвал WP Local с новым цветом фона."), size=10, italic=True, color="777777")

document.save(DOCX)
print(DOCX)
