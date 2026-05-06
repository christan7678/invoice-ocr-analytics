import json
import os
import sys
from pathlib import Path

import pytesseract
from pdf2image import convert_from_path
from PIL import Image, ImageOps


def ocr_image(image):
    image = ImageOps.grayscale(image)
    image = ImageOps.autocontrast(image)
    image = image.resize((image.width * 2, image.height * 2))
    text = pytesseract.image_to_string(image)
    data = pytesseract.image_to_data(image, output_type=pytesseract.Output.DICT)
    confidences = []

    for value in data.get("conf", []):
        try:
            confidence = float(value)
        except ValueError:
            continue

        if confidence >= 0:
            confidences.append(confidence)

    average_confidence = sum(confidences) / len(confidences) if confidences else None

    return text, average_confidence


def main():
    if len(sys.argv) < 2:
        raise SystemExit("Usage: python ocr_invoice.py <invoice-file>")

    file_path = Path(sys.argv[1])

    if not file_path.exists():
        raise SystemExit(f"File not found: {file_path}")

    tesseract_cmd = os.environ.get("TESSERACT_CMD")
    if not tesseract_cmd:
        default_tesseract = Path("C:/Program Files/Tesseract-OCR/tesseract.exe")
        tesseract_cmd = str(default_tesseract) if default_tesseract.exists() else None

    if tesseract_cmd and Path(tesseract_cmd).exists():
        pytesseract.pytesseract.tesseract_cmd = tesseract_cmd

    suffix = file_path.suffix.lower()
    page_texts = []
    confidences = []

    if suffix == ".pdf":
        poppler_path = os.environ.get("POPPLER_PATH") or None
        pages = convert_from_path(str(file_path), dpi=220, poppler_path=poppler_path)

        for page in pages[:5]:
            text, confidence = ocr_image(page)
            page_texts.append(text)
            if confidence is not None:
                confidences.append(confidence)
    else:
        with Image.open(file_path) as image:
            text, confidence = ocr_image(image)
            page_texts.append(text)
            if confidence is not None:
                confidences.append(confidence)

    average = round(sum(confidences) / len(confidences), 2) if confidences else None

    print(json.dumps({
        "text": "\n\n".join(page_texts).strip(),
        "confidence": average,
    }))


if __name__ == "__main__":
    main()
