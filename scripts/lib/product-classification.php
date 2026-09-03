<?php
declare(strict_types=1);

/**
 * Deduce lo slug di product_type dal nome prodotto. Gli slug devono
 * corrispondere alle chiavi di PRODUCT_TYPES in includes/functions.php.
 * Condivisa tra scripts/classify-product-types.php e
 * scripts/lib/description-generator.php, così la classificazione usata per
 * il filtro sul sito e quella usata per generare le descrizioni non entrano
 * mai in contraddizione tra loro.
 *
 * Ritorna null se nessuna parola chiave corrisponde.
 */
function classify_product_type(string $name): ?string
{
    $searchable = mb_strtolower($name, 'UTF-8');

    if (str_contains($searchable, 'foundation') || str_contains($searchable, 'flawless filter') || str_contains($searchable, 'cushion')) {
        return 'fondotinta';
    }
    if (str_contains($searchable, 'concealer') || str_contains($searchable, 'corrector')) {
        return 'correttore';
    }
    if (str_contains($searchable, 'powder') || str_contains($searchable, 'bake')) {
        return 'cipria';
    }
    if (str_contains($searchable, 'blush') || str_contains($searchable, 'cheek')) {
        return 'blush';
    }
    if (str_contains($searchable, 'bronzer') || str_contains($searchable, 'contour')) {
        return 'bronzer';
    }
    if (str_contains($searchable, 'highlighter') || str_contains($searchable, 'highlight') || str_contains($searchable, 'glow')) {
        return 'illuminante';
    }
    if (str_contains($searchable, 'primer') || str_contains($searchable, 'uv primer')) {
        return 'primer';
    }
    if (str_contains($searchable, 'setting spray') || str_contains($searchable, 'fixing')) {
        return 'spray_fissante';
    }
    if (str_contains($searchable, 'mascara')) {
        return 'mascara';
    }
    if (str_contains($searchable, 'eyeshadow') || str_contains($searchable, 'palette') || str_contains($searchable, 'eye palette')) {
        return 'ombretto';
    }
    if (str_contains($searchable, 'lipstick') || str_contains($searchable, 'lip stick') || str_contains($searchable, 'lip balm') || str_contains($searchable, 'lip glow')) {
        return 'rossetto';
    }
    if (str_contains($searchable, 'gloss') || str_contains($searchable, 'lip oil') || str_contains($searchable, 'jelly oil') || str_contains($searchable, 'lip bath')) {
        return 'gloss';
    }
    if (str_contains($searchable, 'liner')) {
        return 'matita_labbra';
    }
    if (str_contains($searchable, 'cleanser') || str_contains($searchable, 'face wash') || str_contains($searchable, 'makeup remover')) {
        return 'detergente';
    }
    if (str_contains($searchable, 'mask')) {
        return 'maschera';
    }
    if (str_contains($searchable, 'serum') || str_contains($searchable, 'fluid') || str_contains($searchable, 'glazing milk') || str_contains($searchable, 'mist')) {
        return 'siero';
    }
    if (str_contains($searchable, 'cream') || str_contains($searchable, 'water cream') || str_contains($searchable, 'barrier')) {
        return 'crema';
    }
    if (str_contains($searchable, 'hair oil')) {
        return 'olio_capelli';
    }
    if (str_contains($searchable, 'hair perfume')) {
        return 'profumo_capelli';
    }
    if (str_contains($searchable, 'perfume mist') || str_contains($searchable, 'perfume set')) {
        return 'profumo';
    }
    if (str_contains($searchable, 'set') || str_contains($searchable, 'duo') || str_contains($searchable, 'trio') || str_contains($searchable, 'kit') || str_contains($searchable, '3 in 1') || str_contains($searchable, '2 in 1')) {
        return 'set';
    }
    if (str_contains($searchable, 'phone case') || str_contains($searchable, 'pouch') || str_contains($searchable, 'sticker') || str_contains($searchable, 'makeup bag') || str_contains($searchable, 'towel') || str_contains($searchable, 'hairband') || $searchable === 'mirror') {
        return 'accessorio';
    }

    return null;
}
