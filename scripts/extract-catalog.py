from __future__ import annotations

import argparse
import json
import re
import unicodedata
from pathlib import Path

import pymupdf


CODE_RE = re.compile(
    r"^(?:Ph|Rh|Su|La|Ki|Ra|El|Fe|Gi|Sa|Ny|NY|Sh|Sol|Ta|Ct|Hu|To|Pa|os|Di|YSL|Ch|Hou)-\d+$"
)

# Coordinate delle colonne nei diversi fogli del PDF (punti PDF).
LAYOUTS = [
    (range(1, 6), (84.0, 210.2, 308.4, 398.3, 503.8, 534.4)),
    (range(6, 9), (91.4, 203.4, 269.2, 393.4, 502.6, 540.0)),
    (range(9, 12), (91.4, 203.4, 279.0, 368.5, 467.9, 505.5)),
    (range(12, 17), (53.9, 169.4, 246.6, 382.2, 471.7, 509.6)),
    (range(17, 20), (81.5, 193.4, 274.7, 357.5, 443.9, 481.5)),
    (range(20, 22), (91.4, 192.0, 273.2, 365.9, 447.7, 485.0)),
    (range(22, 28), (91.4, 203.4, 285.7, 383.0, 455.5, 538.0)),
    (range(28, 34), (127.7, 221.7, 310.4, 429.1, 502.5, 534.0)),
    (range(34, 41), (91.2, 195.8, 283.3, 401.3, 489.4, 528.0)),
]

BRANDS_BY_PREFIX = {
    "Ph": "Ninja Labs",
    "Rh": "Rhode",
    "Su": "Summer Fridays",
    "La": "Laneige",
    "Ki": "Kiko Milano",
    "Ra": "Rare Beauty",
    "El": "E.L.F.",
    "Fe": "Fenty Beauty",
    "Gi": "Gisou",
    "Sa": "Saie",
    "Ny": "NYX",
    "NY": "NYX",
    "Sh": "Sheglam",
    "Sol": "Sol de Janeiro",
    "Ta": "Tarte",
    "Ct": "Charlotte Tilbury",
    "Hu": "Huda Beauty",
    "Pa": "Patrick Ta",
    "os": "One/Size",
    "Di": "Dior",
    "YSL": "YSL",
    "Ch": "Chanel",
    "Hou": "Hourglass",
}

MANUAL_NAMES = {
    (1, "Ph-1"): "Pouch Phone Case",
    (1, "Rh-1"): "Pimple Patches",
    (1, "Rh-2"): "Caffeine Reset Mask",
    (1, "Rh-3"): "Phone Case Set",
    (1, "Rh-4"): "New Phone Case Set",
    (1, "Rh-5"): "Magnetic Phone Case with Gloss",
    (2, "Rh-6"): "Rhode Lip Boost",
    (2, "Rh-7"): "Rhode Gloss 10ml",
    (2, "Rh-8"): "Birthday Limited Gloss",
    (2, "Rh-9"): "Rhode Blush",
    (3, "Rh-13"): "Gloss Set (4 pieces)",
    (3, "Rh-14"): "Peptide Glazing Fluid 50ml",
    (3, "Rh-16"): "Caffeine Reset Mask 50ml",
    (3, "Rh-17"): "Pineapple Face Wash 150ml",
    (3, "Rh-18"): "Glazing Milk 140ml",
    (3, "Rh-19"): "Glazing Mist 80ml",
    (3, "Rh-20"): "Eye Patches Set (6 pieces)",
    (5, "To-1"): "Lip Set",
    (7, "La-1"): "Lip Balm 12g – Glaze Collection",
    (8, "La-2"): "Lip Balm 12g – Classic Collection",
    (12, "El-1"): "E.L.F. Makeup Setting Spray",
    (15, "El-23"): "Foundation / Flawless Satin Foundation",
    (18, "Sh-1"): "Wave Tube Set (4 pieces)",
    (19, "Sh-2"): "Eyeshadow Stick",
    (19, "Sh-3"): "Brow Gel",
    (19, "Sh-4"): "Lip Oil",
    (19, "Sh-5"): "Blush",
    (21, "Ta-1"): "Concealer",
    (22, "Ct-1"): "Unreal Lips Healthy Glow Nectar Oil 14ml",
    (23, "Ct-7"): "Pillow Talk Collagen Lip Bath 7.6ml",
    (23, "Ct-9"): "Airbrush Flawless Setting Spray 100ml",
    (23, "Ct-10"): "Airbrush Flawless Setting Spray Matte Blur 34ml",
    (23, "Ct-11"): "Airbrush Flawless Setting Spray Matte Blur 100ml",
    (23, "Ct-13"): "Airbrush Flawless Mini Setting Spray Kit 34ml × 2",
    (24, "Ct-14"): "Mini Setting Spray Kit 34ml × 2",
    (24, "Ct-16"): "Mini Pillow Talk Lip Kit",
    (25, "Ct-27"): "Beautiful Skin Foundation 30ml",
    (26, "Ct-30"): "Beautifying Face Palette",
    (26, "Ct-32"): "Skin Glow Bronzer",
    (26, "Ct-33"): "Lip Plumper 5.5ml",
    (26, "Ct-34"): "Magic Cleanser 120ml",
    (27, "Ct-35"): "SPF50+ UV Primer 60ml",
    (27, "Ct-36"): "Magic Water Cream 50ml",
    (27, "Ct-37"): "Magic Water Cream",
    (28, "Hu-1"): "Easy Bake Setting Powder 20g",
    (28, "Hu-2"): "Baby Bake Mini Setting Powder with Puff 6g",
    (28, "Hu-6"): "High-Shine Lip Gloss 3.9ml",
    (29, "Hu-8"): "Easy Bake Setting Spray 100ml",
    (29, "Hu-11"): "Color Corrector 9ml",
    (32, "os-1"): "Waterproof Setting Spray 143ml",
    (32, "os-2"): "Mini Setting Spray 46ml",
    (32, "os-3"): "Setting Powder 34.5g",
    (34, "Di-2"): "Lip Gloss 6ml",
    (35, "Di-8"): "Lip Gloss & Lip Oil Set (2 pieces)",
    (35, "Di-9"): "Lip Oil Set (2 pieces)",
    (35, "Di-10"): "Lip Gloss Set (3 pieces)",
    (35, "Di-11"): "Lip Care Set (3 pieces)",
    (36, "Di-14"): "Diorshow 5 Couleurs Eyeshadow Palette",
    (39, "Hou-3"): "2025 Limited Edition Ambient Lighting Palette",
    (39, "Hou-4"): "No. 15 Blush Brush",
}

TECH_CODES = {"Ph-1", "Rh-3", "Rh-4", "Rh-5"}
ACCESSORY_CODES = {
    *(f"Rh-{number}" for number in range(21, 34)),
    "Rh-35",
    "Rh-36",
    "Hou-4",
    "Hou-5",
    "Hou-6",
    "Hou-7",
    "Hou-8",
}


def layout_for(page_number: int) -> tuple[float, float, float, float, float, float]:
    for pages, layout in LAYOUTS:
        if page_number in pages:
            return layout
    raise ValueError(f"Layout non definito per pagina {page_number}")


def brand_for(page_number: int, code: str) -> str:
    prefix = code.split("-", 1)[0]
    if prefix == "To":
        return "Tower 28" if page_number == 5 else "Too Faced"
    return BRANDS_BY_PREFIX[prefix]


def slugify(value: str) -> str:
    normalized = unicodedata.normalize("NFKD", value)
    ascii_value = normalized.encode("ascii", "ignore").decode("ascii")
    return re.sub(r"[^a-z0-9]+", "-", ascii_value.lower()).strip("-")


def clean_lines(raw: str) -> list[str]:
    lines: list[str] = []
    for source_line in raw.splitlines():
        had_cjk = bool(re.search(r"[\u3400-\u9fff\uf900-\ufaff]", source_line))
        line = re.sub(r"[\u3400-\u9fff\uf900-\ufaff]+", "", source_line)
        line = re.sub(r"\s+", " ", line).strip(" /\t")
        line = re.sub(r"[（）]", "", line)
        line = re.sub(r"\(\s*\)", "", line).strip()
        if had_cjk and not re.search(r"[A-Za-z]", line):
            continue
        if not line:
            continue
        replacements = (
            (r"\bphoen\b", "phone"),
            (r"\bfuuid\b", "fluid"),
            (r"\betting\b", "setting"),
            (r"\bConcealar\b", "Concealer"),
            (r"\bpalatte\b", "palette"),
            (r"\bstraberry\b", "strawberry"),
        )
        for pattern, correct in replacements:
            line = re.sub(pattern, correct, line, flags=re.IGNORECASE)
        lines.append(line)
    return lines


def horizontal_boundaries(page: pymupdf.Page) -> list[float]:
    candidates: list[float] = []
    for drawing in page.get_drawings():
        rect = drawing["rect"]
        if rect.height <= 1.2 and rect.width >= 20:
            candidates.append((rect.y0 + rect.y1) / 2)
        elif rect.width <= 1.2 and rect.height >= 20:
            candidates.extend((rect.y0, rect.y1))

    boundaries: list[float] = []
    for value in sorted(candidates):
        if not boundaries or abs(value - boundaries[-1]) > 1.25:
            boundaries.append(value)
    return boundaries


def row_bounds(boundaries: list[float], center_y: float) -> tuple[float, float]:
    above = [value for value in boundaries if value < center_y - 1]
    below = [value for value in boundaries if value > center_y + 1]
    if not above or not below:
        raise ValueError(f"Impossibile trovare i bordi della riga a y={center_y:.1f}")
    return max(above), min(below)


def text_in_cell(page: pymupdf.Page, left: float, top: float, right: float, bottom: float) -> list[str]:
    rect = pymupdf.Rect(left + 1.5, top + 1.0, right - 1.5, bottom - 1.0)
    return clean_lines(page.get_textbox(rect))


def normalize_measure(value: str) -> str:
    return re.sub(r"\s+", " ", value.replace(",", ".")).strip()


def format_details(name: str, variants: list[str], weight_lines: list[str]) -> str | None:
    format_matches = re.findall(
        r"\b\d+(?:[.,]\d+)?\s*(?:ml|g|pcs)\b",
        " ".join([name, *variants]),
        flags=re.IGNORECASE,
    )
    formats = list(dict.fromkeys(normalize_measure(value) for value in format_matches))

    gross_weight = ""
    for line in weight_lines:
        match = re.search(r"\b\d+(?:[.,]\d+)?\s*g\b", line, flags=re.IGNORECASE)
        if match:
            gross_weight = normalize_measure(match.group(0))
            break
        if re.fullmatch(r"\d+(?:[.,]\d+)?", line):
            gross_weight = normalize_measure(line) + " g"
            break

    details: list[str] = []
    if formats:
        details.append("Formato: " + " / ".join(formats))
    if gross_weight and gross_weight.casefold() not in {value.casefold() for value in formats}:
        details.append("Peso lordo: " + gross_weight)
    return " · ".join(details) or None


def save_gallery_images(
    document: pymupdf.Document,
    page: pymupdf.Page,
    image_infos: list[dict[str, object]],
    image_left: float,
    image_right: float,
    top: float,
    bottom: float,
    destination: Path,
    filename_stem: str,
) -> list[Path]:
    stale_pattern = re.compile(rf"^{re.escape(filename_stem)}-\d+\.jpg$")
    for stale_file in destination.iterdir():
        if stale_file.is_file() and stale_pattern.fullmatch(stale_file.name):
            stale_file.unlink()

    matching_images: list[dict[str, object]] = []
    for info in image_infos:
        bbox = pymupdf.Rect(info["bbox"])
        center_x = (bbox.x0 + bbox.x1) / 2
        center_y = (bbox.y0 + bbox.y1) / 2
        if image_left - 2 <= center_x <= image_right + 2 and top <= center_y <= bottom:
            matching_images.append(info)

    visible_images: list[dict[str, object]] = []
    for info in matching_images:
        candidate = pymupdf.Rect(info["bbox"])
        replaced = False
        for index, existing_info in enumerate(visible_images):
            existing = pymupdf.Rect(existing_info["bbox"])
            intersection = candidate & existing
            smaller_area = min(candidate.get_area(), existing.get_area())
            overlap = intersection.get_area() / smaller_area if smaller_area else 0
            if overlap >= 0.8:
                # L'ultimo oggetto disegnato è quello visibile nel PDF.
                visible_images[index] = info
                replaced = True
                break
        if not replaced:
            visible_images.append(info)

    matching_images = visible_images
    matching_images.sort(key=lambda info: (info["bbox"][1], info["bbox"][0]))
    saved: list[Path] = []
    seen_positions: set[tuple[int, tuple[int, int, int, int]]] = set()

    for info in matching_images:
        xref = int(info["xref"])
        rounded_bbox = tuple(int(round(value)) for value in info["bbox"])
        position_key = (xref, rounded_bbox)
        if xref <= 0 or position_key in seen_positions:
            continue
        seen_positions.add(position_key)

        number = len(saved) + 1
        suffix = "" if number == 1 else f"-{number}"
        output_path = destination / f"{filename_stem}{suffix}.jpg"
        pixmap = pymupdf.Pixmap(document, xref)
        if pixmap.colorspace is not None and pixmap.colorspace.n > 3:
            pixmap = pymupdf.Pixmap(pymupdf.csRGB, pixmap)
        if pixmap.alpha:
            pixmap = pymupdf.Pixmap(pixmap, 0)
        output_path.write_bytes(pixmap.tobytes("jpeg", jpg_quality=88))
        saved.append(output_path)

    if not saved:
        output_path = destination / f"{filename_stem}.jpg"
        clip = pymupdf.Rect(image_left + 1.0, top + 1.0, image_right - 1.0, bottom - 1.0)
        pixmap = page.get_pixmap(matrix=pymupdf.Matrix(3, 3), clip=clip, alpha=False)
        pixmap.save(output_path, jpg_quality=86)
        saved.append(output_path)

    return saved


def extract(pdf_path: Path, project_root: Path) -> list[dict[str, object]]:
    document = pymupdf.open(pdf_path)
    image_root = project_root / "assets" / "images" / "catalog"
    data_path = project_root / "data" / "catalog-products.json"
    image_root.mkdir(parents=True, exist_ok=True)
    data_path.parent.mkdir(parents=True, exist_ok=True)

    products: list[dict[str, object]] = []
    duplicate_counters: dict[tuple[str, str], int] = {}

    for page_index, page in enumerate(document):
        page_number = page_index + 1
        image_left, image_right, name_right, variants_right, price_right, table_right = layout_for(page_number)
        boundaries = horizontal_boundaries(page)
        image_infos = page.get_image_info(xrefs=True)

        code_words = []
        for word in page.get_text("words"):
            text = word[4].strip()
            if CODE_RE.fullmatch(text):
                code_words.append(word)
        code_words.sort(key=lambda word: (word[1], word[0]))

        previous_name = ""
        previous_variants: list[str] = []

        for word in code_words:
            code = word[4]
            center_y = (word[1] + word[3]) / 2
            top, bottom = row_bounds(boundaries, center_y)

            # El-23 racchiude due fotografie e due nomi nello stesso codice.
            if page_number == 15 and code == "El-23":
                following = [value for value in boundaries if value > bottom + 1]
                if following:
                    bottom = min(following)

            name_lines = text_in_cell(page, image_right, top, name_right, bottom)
            variants = text_in_cell(page, name_right, top, variants_right, bottom)
            weight_lines = text_in_cell(page, price_right, top, table_right, bottom)
            brand = brand_for(page_number, code)
            brand_aliases = {brand.casefold(), "ct", brand.replace(".", "").casefold()}
            name_lines = [line for line in name_lines if line.casefold() not in brand_aliases]
            name = " ".join(name_lines)

            if (page_number, code) in MANUAL_NAMES:
                name = MANUAL_NAMES[(page_number, code)]
            elif not name and previous_name:
                name = previous_name

            if not variants and not name_lines and previous_variants:
                variants = previous_variants.copy()

            key = (brand, code.lower())
            duplicate_counters[key] = duplicate_counters.get(key, 0) + 1
            duplicate_number = duplicate_counters[key]
            unique_code = code if duplicate_number == 1 else f"{code}-{chr(64 + duplicate_number)}"

            brand_directory = image_root / slugify(brand)
            brand_directory.mkdir(parents=True, exist_ok=True)
            filename_stem = slugify(unique_code)
            gallery_files = save_gallery_images(
                document,
                page,
                image_infos,
                image_left,
                image_right,
                top,
                bottom,
                brand_directory,
                filename_stem,
            )
            gallery_urls = [
                "/assets/images/catalog/" + slugify(brand) + "/" + image_file.name
                for image_file in gallery_files
            ]

            if code in TECH_CODES:
                category = "elettronica"
            elif code in ACCESSORY_CODES:
                category = "abbigliamento"
            else:
                category = "cosmetici"

            products.append(
                {
                    "code": unique_code,
                    "category": category,
                    "brand": brand,
                    "name": name or f"Prodotto {unique_code}",
                    "variants": variants,
                    "image_path": gallery_urls[0],
                    "images": gallery_urls,
                    "format_details": format_details(name, variants, weight_lines),
                    "description": (
                        f"{name} di {brand}. Consulta le varianti disponibili "
                        "e scegli quella più adatta alle tue esigenze."
                    ),
                }
            )

            previous_name = name or previous_name
            previous_variants = variants or previous_variants

    data_path.write_text(
        json.dumps(products, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    return products


def main() -> None:
    parser = argparse.ArgumentParser(description="Estrae prodotti e immagini dal catalogo PDF BeautyDrops.")
    parser.add_argument("pdf", type=Path, help="Percorso del catalogo PDF")
    parser.add_argument(
        "--project-root",
        type=Path,
        default=Path(__file__).resolve().parents[1],
        help="Root del progetto (default: cartella superiore a scripts)",
    )
    args = parser.parse_args()

    products = extract(args.pdf.resolve(), args.project_root.resolve())
    counts: dict[str, int] = {}
    for product in products:
        brand = str(product["brand"])
        counts[brand] = counts.get(brand, 0) + 1

    print(f"Estratti {len(products)} prodotti in {len(counts)} brand.")
    for brand, count in sorted(counts.items()):
        print(f"- {brand}: {count}")


if __name__ == "__main__":
    main()
