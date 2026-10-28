import argparse
import json
import re
from pathlib import Path

from docx import Document


QUESTION_RE = re.compile(r"^(\d{1,2})\.\s*(.*)$")
OPTION_RE = re.compile(r"^[АБВГA-D]\)\s*", re.IGNORECASE)


def is_correct(paragraph):
    for run in paragraph.runs:
        if not run.text.strip():
            continue
        color = run.font.color.rgb
        if run.bold is True or (color is not None and str(color).upper() == "FF0000"):
            return True
    return False


def extract_questions(path):
    questions = []
    current = None

    for paragraph in Document(path).paragraphs:
        text = paragraph.text.strip()
        question_match = QUESTION_RE.match(text)
        if question_match:
            if current:
                questions.append(current)
            current = {
                "number": int(question_match.group(1)),
                "text": question_match.group(2).strip(),
                "options": [],
                "correct": [],
            }
            continue

        if current and OPTION_RE.match(text):
            current["options"].append(OPTION_RE.sub("", text).strip())
            if is_correct(paragraph):
                current["correct"].append(len(current["options"]))

    if current:
        questions.append(current)

    return questions


def validate_questions(questions):
    errors = []
    numbers = [question["number"] for question in questions]
    expected = list(range(1, 51))
    if numbers != expected:
        errors.append(f"Expected question numbers 1-50, got {numbers}")

    for question in questions:
        if not question["text"]:
            errors.append(f"Question {question['number']} has no text")
        if len(question["options"]) != 4:
            errors.append(
                f"Question {question['number']} has {len(question['options'])} options"
            )
        if len(question["correct"]) != 1:
            errors.append(
                f"Question {question['number']} has correct markers {question['correct']}"
            )

    if errors:
        raise ValueError("\n".join(errors))


def find_duplicate_texts(questions):
    grouped = {}
    for question in questions:
        normalized = re.sub(r"\W+", "", question["text"].lower())
        grouped.setdefault(normalized, []).append(question["number"])
    return [numbers for numbers in grouped.values() if len(numbers) > 1]


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("source", type=Path)
    parser.add_argument("--output", type=Path)
    args = parser.parse_args()

    questions = extract_questions(args.source)
    validate_questions(questions)

    payload = {
        "source": args.source.name,
        "questions": questions,
    }
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(
            json.dumps(payload, ensure_ascii=False, indent=2) + "\n",
            encoding="utf-8",
        )

    print(
        json.dumps(
            {
                "questions": len(questions),
                "duplicate_texts": find_duplicate_texts(questions),
                "answer_key": [question["correct"][0] for question in questions],
            },
            ensure_ascii=False,
        )
    )


if __name__ == "__main__":
    main()
