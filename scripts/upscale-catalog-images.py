"""Migliora le foto del catalogo con risoluzione nativa molto bassa.

Molte immagini in assets/images/catalog/ sono state estratte da un PDF
fornitore (vedi extract-catalog.py) alla loro risoluzione nativa incorporata,
spesso solo 60-200px di lato: quella e' la qualita' massima disponibile nella
fonte originale, non un difetto di estrazione.

Questo script NON e' un vero upscaler "AI" e non inventa dettaglio reale:
applica un ridimensionamento Lanczos (il miglior interpolatore disponibile in
Pillow per l'ingrandimento) seguito da un unsharp mask leggero per ridurre la
morbidezza introdotta dal ridimensionamento. Il risultato e' piu' gradevole a
schermo nelle dimensioni tipiche di una card prodotto, ma sulle immagini di
partenza piu' piccole (~60-150px) resta visibilmente morbido da vicino: non
diventa una vera foto HD/4K, perche' quel dettaglio non esiste nella fonte.

Il fattore di ingrandimento e' limitato (MAX_SCALE) apposta: oltre quella
soglia lo sharpening produce aloni/artefatti piu' brutti dell'originale
piccolo, quindi non vale la pena spingersi oltre.

Uso:
    python scripts/upscale-catalog-images.py [--dry-run]
"""

from __future__ import annotations

import argparse
from pathlib import Path

from PIL import Image, ImageFilter

TARGET_LONG_SIDE = 1200
MAX_SCALE = 4.0
JPEG_QUALITY = 92


def process_image(path: Path, dry_run: bool) -> str:
    with Image.open(path) as im:
        im = im.convert("RGB")
        width, height = im.size
        long_side = max(width, height)

        if long_side >= TARGET_LONG_SIDE:
            return f"skip (già {width}x{height})"

        scale = min(MAX_SCALE, TARGET_LONG_SIDE / long_side)
        if scale <= 1.0:
            return f"skip (già {width}x{height})"

        new_size = (round(width * scale), round(height * scale))

        if dry_run:
            return f"{width}x{height} -> {new_size[0]}x{new_size[1]} (scala {scale:.2f}x)"

        resized = im.resize(new_size, Image.LANCZOS)
        sharpened = resized.filter(ImageFilter.UnsharpMask(radius=1.2, percent=70, threshold=3))
        sharpened.save(path, "JPEG", quality=JPEG_QUALITY, optimize=True)

        return f"{width}x{height} -> {new_size[0]}x{new_size[1]} (scala {scale:.2f}x)"


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", help="Mostra cosa verrebbe fatto senza scrivere i file")
    args = parser.parse_args()

    project_root = Path(__file__).resolve().parent.parent
    catalog_dir = project_root / "assets" / "images" / "catalog"

    paths = sorted(p for p in catalog_dir.rglob("*") if p.suffix.lower() in {".jpg", ".jpeg", ".png", ".webp"})

    processed = 0
    skipped = 0
    for path in paths:
        result = process_image(path, args.dry_run)
        relative = path.relative_to(project_root)
        if result.startswith("skip"):
            skipped += 1
        else:
            processed += 1
            print(f"{relative}: {result}")

    print(f"\nTotale immagini: {len(paths)}")
    print(f"Migliorate: {processed}")
    print(f"Già a risoluzione sufficiente (invariate): {skipped}")


if __name__ == "__main__":
    main()
