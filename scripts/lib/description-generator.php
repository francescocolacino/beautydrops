<?php
declare(strict_types=1);

require_once __DIR__ . '/product-classification.php';

/**
 * Intro brevissima per tipo prodotto: una frase su cos'è/a che serve, una
 * frase (spesso solo una clausola) su come si usa. Chiave "_default" usata
 * quando classify_product_type() non riconosce il nome.
 */
const PRODUCT_TYPE_INTROS = [
    'fondotinta' => "Fondotinta per uniformare l'incarnato. Applica poche gocce con spugna o pennello, dal centro del viso verso l'esterno.",
    'correttore' => 'Correttore per coprire occhiaie e imperfezioni. Applica poco prodotto e sfuma con il dito o una spugnetta.',
    'cipria' => 'Cipria per opacizzare la pelle e fissare il trucco. Applica con un pennello soffice sulle zone più lucide.',
    'blush' => 'Blush per dare colore alle guance. Applica con un pennello sugli zigomi e sfuma verso le tempie.',
    'bronzer' => 'Bronzer/contouring per scolpire e scaldare il viso. Applica su zigomi e mascella e sfuma bene i bordi.',
    'illuminante' => "Illuminante per un effetto luminoso mirato. Applica su zigomi e arco di Cupido con pennello o dita.",
    'primer' => 'Primer per preparare la pelle al trucco. Applica un velo sottile dopo la skincare, prima del fondotinta.',
    'spray_fissante' => 'Spray fissante per far durare il trucco più a lungo. Vaporizza a 20-30 cm dal viso, a occhi chiusi.',
    'mascara' => 'Mascara per volumizzare e allungare le ciglia. Applica dalla base alle punte con movimenti a zig-zag.',
    'ombretto' => 'Ombretto per definire lo sguardo. Applica con un pennello, sfumando dal colore più chiaro al più scuro.',
    'rossetto' => 'Rossetto per colorare le labbra. Applica direttamente o con un pennellino, dal centro verso i bordi.',
    'gloss' => 'Gloss/olio labbra per lucentezza e idratazione. Applica da solo o sopra il rossetto.',
    'matita_labbra' => 'Matita labbra per definire il contorno. Disegna il bordo prima di rossetto o gloss.',
    'detergente' => 'Detergente per rimuovere trucco e impurità. Massaggia su pelle umida ed elimina con acqua.',
    'maschera' => 'Maschera viso per un trattamento intensivo. Applica su pelle pulita e lascia in posa per il tempo indicato.',
    'siero' => 'Siero per idratare e trattare la pelle in profondità. Applica poche gocce su viso e collo prima della crema.',
    'crema' => 'Crema viso per idratare quotidianamente. Applica su viso e collo puliti, mattina e/o sera.',
    'olio_capelli' => 'Olio per capelli per nutrire e dare lucentezza. Applica poche gocce su lunghezze e punte.',
    'profumo_capelli' => 'Profumo per capelli per una fragranza leggera. Vaporizza a distanza sulle lunghezze.',
    'profumo' => 'Profumo per il corpo. Vaporizza su polsi e collo, a qualche centimetro dalla pelle.',
    'set' => 'Set di più pezzi pensato per completare una routine con un unico acquisto.',
    'accessorio' => 'Accessorio pratico per la routine beauty o per la vita di tutti i giorni. Usalo secondo la sua funzione.',
    '_default' => 'Prodotto beauty pensato per la routine quotidiana. Usa secondo le indicazioni riportate sulla confezione.',
];

/**
 * Parole chiave riconosciute nel nome di una variante, cercate come
 * substring case-insensitive: la prima che corrisponde vince. Copre colori
 * e note fruttate/aromatiche più comuni nei nomi reali del catalogo; per
 * tutto il resto si usa una descrizione generica (vedi variant_descriptor).
 */
const VARIANT_KEYWORDS = [
    'sugarmint' => 'nota fresca menta-zucchero',
    'unscented' => 'senza profumo',
    'mint' => 'nota fresca alla menta',
    'menta' => 'nota fresca alla menta',
    'vanilla' => 'richiamo vaniglia',
    'vaniglia' => 'richiamo vaniglia',
    'coconut' => 'richiamo cocco',
    'cocco' => 'richiamo cocco',
    'caramel' => 'richiamo caramello',
    'caramello' => 'richiamo caramello',
    'raspberry' => 'richiamo lampone',
    'lampone' => 'richiamo lampone',
    'strawberry' => 'richiamo fragola',
    'fragola' => 'richiamo fragola',
    'watermelon' => 'richiamo anguria',
    'anguria' => 'richiamo anguria',
    'cherry' => 'richiamo ciliegia',
    'ciliegia' => 'richiamo ciliegia',
    'citrus' => 'nota agrumata',
    'agrumat' => 'nota agrumata',
    'sunlight' => 'tonalità dorata luminosa',
    'espresso' => 'tonalità marrone caldo',
    'toast' => 'tonalità calda ambrata',
    'rosa' => 'tonalità rosa',
    'pink' => 'tonalità rosa',
    'rosso' => 'tonalità rossa',
    'red' => 'tonalità rossa',
    'corallo' => 'tonalità corallo',
    'coral' => 'tonalità corallo',
    'pesca' => 'tonalità pesca',
    'peach' => 'tonalità pesca',
    'nude' => 'tonalità nude',
    'beige' => 'tonalità beige',
    'marrone' => 'tonalità marrone',
    'brown' => 'tonalità marrone',
    'cocoa' => 'tonalità cacao',
    'chocolate' => 'tonalità cioccolato',
    'bronzo' => 'tonalità bronzo',
    'bronze' => 'tonalità bronzo',
    'oro' => 'tonalità dorata',
    'gold' => 'tonalità dorata',
    'argento' => 'tonalità argento',
    'silver' => 'tonalità argento',
    'viola' => 'tonalità viola',
    'purple' => 'tonalità viola',
    'plum' => 'tonalità prugna',
    'prugna' => 'tonalità prugna',
    'lavanda' => 'tonalità lavanda',
    'lavender' => 'tonalità lavanda',
    'blu' => 'tonalità blu',
    'blue' => 'tonalità blu',
    'verde' => 'tonalità verde',
    'green' => 'tonalità verde',
    'giallo' => 'tonalità gialla',
    'yellow' => 'tonalità gialla',
    'arancio' => 'tonalità arancione',
    'orange' => 'tonalità arancione',
    'pearl' => 'tonalità perlata',
    'perla' => 'tonalità perlata',
    'white' => 'tonalità chiara',
    'bianco' => 'tonalità chiara',
    'black' => 'tonalità nera',
    'nero' => 'tonalità nera',
    'grigio' => 'tonalità grigia',
    'gray' => 'tonalità grigia',
    'grey' => 'tonalità grigia',
    'amber' => 'tonalità ambrata',
    'ambra' => 'tonalità ambrata',
    'terracotta' => 'tonalità terracotta',
    'berry' => 'richiamo ai frutti di bosco',
];

function is_set_product(string $name): bool
{
    $searchable = mb_strtolower($name, 'UTF-8');
    foreach (['set', 'duo', 'trio', 'kit', '3 in 1', '2 in 1'] as $keyword) {
        if (str_contains($searchable, $keyword)) {
            return true;
        }
    }
    return false;
}

/**
 * Ripulisce un nome di variante grezzo (numerazioni tipo "01-", "+" residui
 * da liste separate da virgole, spazi doppi) e lo rende presentabile.
 * Non corregge refusi presenti nel dato originale (es. "promax" scritto
 * attaccato): resta comunque leggibile, solo non "corretto" nel merito.
 */
function clean_variant_label(string $raw): string
{
    $s = trim($raw);
    $s = preg_replace('/^\s*\d+\s*[-.]\s*/', '', $s) ?? $s;
    $s = str_replace('+', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;
    $s = trim($s, " -");
    if ($s === '') {
        $s = trim($raw);
    }
    $titled = mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    $titled = preg_replace('/(\d)([A-Za-z])/', '$1 $2', $titled) ?? $titled;
    $titled = preg_replace('/\bIphone\b/i', 'iPhone', $titled) ?? $titled;
    $titled = preg_replace('/\bpro\s?max\b/i', 'Pro Max', $titled) ?? $titled;
    return $titled;
}

function variant_descriptor(string $rawVariant, ?string $type): string
{
    $displayName = clean_variant_label($rawVariant);
    $lower = mb_strtolower($rawVariant, 'UTF-8');

    foreach (VARIANT_KEYWORDS as $keyword => $descriptor) {
        if (str_contains($lower, $keyword)) {
            return "{$displayName} — {$descriptor}.";
        }
    }

    if (str_contains($lower, 'iphone') || str_contains($lower, 'samsung') || preg_match('/pro\s?max/', $lower) === 1) {
        return "{$displayName} — formato compatibile.";
    }

    $fallback = $type === 'accessorio' ? 'opzione disponibile' : 'una delle tonalità disponibili';

    return "{$displayName} — {$fallback}.";
}

function set_composition_line(array $variants): string
{
    if (empty($variants)) {
        return '';
    }
    $cleaned = array_map('clean_variant_label', $variants);
    return 'Composto da: ' . implode(', ', $cleaned) . '.';
}

/**
 * Genera la descrizione completa (intro brevissima + elenco varianti o
 * composizione set) per un prodotto, a partire da nome e varianti già
 * decodificate. Non dipende da un product_type salvato a parte: classifica
 * dal nome ogni volta, così resta coerente anche se qualcuno lo modifica.
 */
function generate_rich_description(string $name, array $variants): string
{
    $type = classify_product_type($name);
    $intro = PRODUCT_TYPE_INTROS[$type ?? '_default'] ?? PRODUCT_TYPE_INTROS['_default'];
    $isSet = is_set_product($name);

    $parts = [$intro];

    if ($isSet && !empty($variants)) {
        $composition = set_composition_line($variants);
        if ($composition !== '') {
            $parts[] = $composition;
        }
    } elseif (!$isSet && count($variants) >= 2) {
        $bullets = array_map(fn($v) => '• ' . variant_descriptor($v, $type), $variants);
        $parts[] = "Varianti disponibili:\n" . implode("\n", $bullets);
    }

    return implode("\n\n", array_filter($parts, fn($p) => $p !== ''));
}
