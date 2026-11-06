from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, RGBColor
from docx.text.paragraph import Paragraph


SOURCE = Path(r"C:\Users\zloy27\OneDrive\Рабочий стол\сайт исфнимк\требования-обновлено-со-скриншотами.docx")
OUTPUT = Path(r"C:\Users\zloy27\OneDrive\Рабочий стол\сайт исфнимк\требования-ИТОГ-с-подтверждениями.docx")
EVIDENCE = Path(r"C:\Users\zloy27\.codex\visualizations\2026\11\05\tarhan-requirements-evidence-all")


TASKS = {
    1: ("home-hero.png", 5.7, "Выполнено и проверено 05.11.2026 на Local: по адресу сайта открывается актуальная главная страница.", "Актуальная главная страница сайта."),
    2: ("home-hero.png", 5.7, "Выполнено и проверено 05.11.2026 на Local: согласованный текст размещён в первом блоке.", "Первый информационный блок с согласованным текстом."),
    4: ("home-prize.png", 5.7, "Выполнено и проверено 05.11.2026 на Local: текст главного приза и полное название института размещены на главной.", "Блок главного приза после изменения."),
    5: ("information-organizers.png", 5.7, "Выполнено и проверено 05.11.2026 на Local: карточки расположены в согласованном порядке.", "Карточки Минкультуры РТ, КФУ и оргкомитета в итоговом порядке."),
    6: ("participant-lectures.png", 4.2, "Выполнено и проверено 05.11.2026 на Local: карточка лекций ведёт на согласованный плейлист VK.", "Карточка видеолекций после исправления ссылки."),
    8: ("season-links-1-5.png", 5.7, "Выполнено и проверено 05.11.2026 на Local: записи сезонов 1–5 привязаны к соответствующим разделам.", "Фактические блоки просмотра всех пяти сезонов."),
    9: ("participant-preparation-links.png", 5.7, "Выполнено и проверено 05.11.2026 на Local: карточки подготовки и ссылки отображаются в разделе участника.", "Карточки подготовки участника после изменения ссылок."),
    10: ("header-logos.png", 5.7, "Выполнено и проверено 05.11.2026 на Local: все четыре логотипа организаторов видны в шапке.", "Ряд логотипов организаторов на сайте."),
    13: ("home-audience.png", 5.7, "Выполнено и проверено 05.11.2026 на Local: карточка условий участия отображается в согласованном виде.", "Карточка «Кто может участвовать?» после изменения."),
    14: ("jury-fakhrutdinov.png", 2.35, "Выполнено и проверено 05.11.2026 на Local: подпись заменена на «профессор, ведущий проекта».", "Карточка Раиля Равиловича Фахрутдинова с новой подписью."),
    15: ("jury-faizrakhmanov.png", 2.35, "Выполнено и проверено 05.11.2026 на Local: указана должность директора Национального музея Республики Татарстан.", "Карточка Айрата Шамилевича Файзрахманова с исправленной должностью."),
    16: ("jury-natfullin.png", 2.35, "Выполнено и проверено 05.11.2026 на Local: указана должность директора Музея изобразительных искусств Республики Татарстан.", "Карточка Дамира Даниловича Натфуллина с исправленной должностью."),
    17: ("jury-shchelkunov.png", 2.35, "Выполнено и проверено 05.11.2026 на Local: прежняя должность заменена на «профессор».", "Карточка Михаила Дмитриевича Щелкунова с новой подписью."),
    18: ("jury-khuzin.png", 2.35, "Выполнено и проверено 05.11.2026 на Local: установлен памятный портрет в траурной рамке.", "Карточка Фаяза Шариповича Хузина с памятным портретом."),
    19: ("information-documents.png", 5.2, "Выполнено и проверено 05.11.2026 на Local: публичная ссылка «Лист согласования» скрыта, положение доступно.", "Раздел документов без публичной ссылки «Лист согласования»."),
    20: ("home-statistics.png", 5.7, "Выполнено и проверено 05.11.2026 на Local: отображаются значения 40 / 4 / 6 / 3 / 1.", "Итоговые числовые показатели конкурса."),
    21: ("season-links-1-5.png", 5.7, "Выполнено и проверено 05.11.2026 на Local: плейлисты VK явно подписаны как сезоны 1–5.", "Подписанные ссылки на плейлисты всех пяти сезонов."),
    22: ("home-prize.png", 5.7, "Выполнено и проверено 05.11.2026 на Local: полное название института выведено в блоке главного приза.", "Полное название института в блоке главного приза."),
    23: ("archive-season-2025-2026.png", 4.8, "Выполнено и проверено 05.11.2026 на Local: пятый сезон обозначен как 2025–2026.", "Карточка пятого сезона 2025–2026 в архиве."),
    24: ("registration-mobile-address.png", 2.8, "Выполнено и проверено 05.11.2026 на мобильной ширине Local: регион выбирается, список населённых пунктов загружается.", "Мобильная форма после выбора региона: доступны населённые пункты."),
    25: ("organizer-export-verification.png", 5.7, "Выполнено и повторно проверено 05.11.2026: таблица участников, фильтры и XLSX-выгрузка прошли автоматизированную проверку.", "Фактический результат проверки таблицы участников и XLSX-выгрузки."),
}


def clear_paragraph(paragraph):
    for child in list(paragraph._p):
        if child.tag != qn("w:pPr"):
            paragraph._p.remove(child)


def section_bounds(document, number):
    heading = f"Правка № {number}."
    start = next(i for i, p in enumerate(document.paragraphs) if p.text.startswith(heading))
    end = next(
        (i for i in range(start + 1, len(document.paragraphs)) if document.paragraphs[i].text.startswith("Правка №")),
        len(document.paragraphs),
    )
    return start, end


def set_status(paragraph, value):
    clear_paragraph(paragraph)
    lead = paragraph.add_run("Статус: ")
    lead.bold = True
    paragraph.add_run(value)


def set_picture(paragraph, image_path, width, alt_text):
    clear_paragraph(paragraph)
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    paragraph.paragraph_format.keep_with_next = True
    inline = paragraph.add_run().add_picture(str(image_path), width=Inches(width))
    inline._inline.docPr.set("descr", alt_text)
    inline._inline.docPr.set("title", alt_text)


def insert_after(paragraph):
    new_p = OxmlElement("w:p")
    paragraph._p.addnext(new_p)
    return Paragraph(new_p, paragraph._parent)


def set_caption(paragraph, text):
    clear_paragraph(paragraph)
    run = paragraph.add_run("Результат выполнения: " + text)
    run.italic = True
    run.font.color.rgb = RGBColor(0x72, 0x72, 0x72)


document = Document(SOURCE)

for number, (filename, width, status, caption_text) in TASKS.items():
    start, end = section_bounds(document, number)
    section = document.paragraphs[start:end]
    status_paragraph = next(p for p in section if p.text.startswith("Статус:"))
    set_status(status_paragraph, status)

    picture_paragraph = next((p for p in section if p._p.xpath(".//w:drawing")), None)
    if picture_paragraph is None:
        picture_paragraph = next((p for p in section if p.text.startswith("Скриншот")), None)
    if picture_paragraph is None:
        picture_paragraph = insert_after(section[-1])
    set_picture(picture_paragraph, EVIDENCE / filename, width, caption_text)

    start, end = section_bounds(document, number)
    section = document.paragraphs[start:end]
    picture_index = next(i for i, p in enumerate(section) if p._p.xpath(".//w:drawing"))
    caption = next(
        (p for p in section[picture_index + 1:] if p.text.strip() and (
            p.text.startswith("Место правки")
            or p.text.startswith("Иллюстрация")
            or p.text.startswith("Результат выполнения")
            or p.text.startswith("Скриншот")
        )),
        None,
    )
    if caption is None:
        caption = insert_after(section[picture_index])
    set_caption(caption, caption_text)

document.save(OUTPUT)
print(OUTPUT)
