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
    'mocha' => 'tonalità marrone caffè',
    'truffle' => 'tonalità marrone intenso',
    'cinnamon' => 'tonalità cannella calda',
    'cannella' => 'tonalità cannella calda',
    'chacolit' => 'tonalità cioccolato',
    'melon' => 'richiamo melone',
    'cucum' => 'richiamo cetriolo',
    'pineapple' => 'richiamo ananas',
    'ananas' => 'richiamo ananas',
    'matcha' => 'richiamo tè matcha',
    'taro' => 'richiamo taro',
    'candy' => 'nota dolce zuccherata',
    'gummy' => 'nota dolce gommosa',
    'cookie' => 'nota dolce da biscotto',
    'cake' => 'nota dolce da forno',
    'waffle' => 'nota dolce da waffle',
    'honey' => 'richiamo miele',
    'miele' => 'richiamo miele',
    'guava' => 'richiamo guava',
    'lychee' => 'richiamo litchi',
    'mango' => 'richiamo mango',
    'apricot' => 'richiamo albicocca',
    'albicocca' => 'richiamo albicocca',
    'fig' => 'richiamo fico',
    'rosewood' => 'tonalità legno di rosa',
    'rose' => 'tonalità rosa',
    'mauve' => 'tonalità malva',
    'malva' => 'tonalità malva',
    'tea rose' => 'tonalità rosa tè',
    'tawny' => 'tonalità fulva calda',
    'copper' => 'tonalità rame',
    'rame' => 'tonalità rame',
    'champagne' => 'tonalità champagne dorato',
    'quartz' => 'tonalità chiara cristallina',
    'crystal' => 'tonalità cristallina trasparente',
    'clear' => 'trasparente',
    'trasparente' => 'trasparente',
    'sand' => 'tonalità sabbia',
    'sabbia' => 'tonalità sabbia',
    'porcelain' => 'tonalità molto chiara',
    'porcellana' => 'tonalità molto chiara',
    'olive' => 'sottotono olivastro',
    'oliva' => 'sottotono olivastro',
    'birch' => 'tonalità chiara naturale',
    'cedar' => 'tonalità chiara naturale',
    'fawn' => 'tonalità chiara naturale',
    'cotton' => 'tonalità molto chiara',
    'creme' => 'tonalità chiara neutra',
    'oat' => 'tonalità chiara naturale',
    'silk' => 'tonalità chiara naturale',
    'ethereal' => 'tonalità eterea luminosa',
    'sublime' => 'tonalità luminosa elegante',
    'luminous' => 'tonalità luminosa',
    'radiant' => 'tonalità luminosa',
    'glow' => 'effetto luminoso',
    'sunset' => 'richiamo tramonto caldo',
    'sunrise' => 'richiamo alba delicata',
    'dream' => 'tonalità delicata',
    'romance' => 'tonalità romantica rosata',
    'passion' => 'tonalità intensa',
    'petal' => 'tonalità floreale delicata',
    'peony' => 'tonalità floreale rosata',
    'daisy' => 'tonalità floreale chiara',
    'bloom' => 'tonalità floreale',
    'orchid' => 'tonalità floreale intensa',
    'lily' => 'tonalità floreale chiara',
    'toffee' => 'tonalità calda ambrata',
    'java' => 'tonalità marrone caffè',
    'fudge' => 'tonalità cioccolato intenso',
    'citrine' => 'tonalità dorata cristallina',
    'opal' => 'tonalità chiara iridescente',
    'wine' => 'tonalità vinaccia',
    'grape' => 'richiamo uva',
    'lemon' => 'richiamo limone',
    'limone' => 'richiamo limone',
    'fuchsia' => 'tonalità fucsia',
    'taupe' => 'tonalità taupe (grigio-marrone)',
    'warm' => 'tonalità calda',
    'intense' => 'tonalità intensa',
    'neutral' => 'tonalità neutra',
    'neutra' => 'tonalità neutra',
    'pine apple' => 'richiamo ananas',
    'heat' => 'finish caldo',
    'cold' => 'nota fresca',
    'chocolit' => 'tonalità cioccolato',
    'sugar' => 'nota dolce zuccherata',
    'soft' => 'tonalità delicata',
    'coco' => 'tonalità cacao chiaro',
];

/**
 * Parole di profondità/sottotono (anche in francese, usate da alcuni brand
 * per le taglie fondotinta/concealer: "Fair/Claira", "Moyen-Foncé"...).
 * Controllate per ultime, dopo colori/note specifiche: sono generiche e
 * altrimenti rischierebbero di intercettare varianti più descrivibili.
 */
const DEPTH_KEYWORDS = [
    'fair' => 'profondità chiara',
    'clair' => 'profondità chiara',
    'pale' => 'profondità molto chiara',
    'medium' => 'profondità media',
    'moyen' => 'profondità media',
    'deep' => 'profondità scura',
    'dark' => 'profondità scura',
    'fonce' => 'profondità scura',
    'naturel' => 'sottotono naturale',
    'light' => 'profondità chiara',
];

/**
 * Descrittori specifici per linee prodotto ricercate online (colore/finish
 * reale della tonalità, non genericamente dedotto dal nome): la chiave
 * esterna è un frammento (minuscolo) del nome prodotto, quella interna un
 * frammento del nome variante. Controllato prima del dizionario generico
 * qui sopra. Fonti: schede prodotto ufficiali/rivenditori, verificate via
 * ricerca web (non garantite identiche a collezioni future/regionali).
 */
const PRODUCT_LINE_VARIANT_OVERRIDES = [
    'soft pinch liquid blush' => [
        'lucky' => 'tonalità rosa acceso',
        'happy' => 'tonalità rosa freddo',
        'joy' => 'tonalità pesca tenue',
        'hope' => 'tonalità malva nude',
        'love' => 'tonalità terracotta',
        'believe' => 'tonalità malva vero',
        'grateful' => 'tonalità rosso vero',
        'virtue' => 'tonalità pesca beige',
        'encourage' => 'tonalità rosa neutro tenue',
        'bliss' => 'tonalità rosa nude',
        'worth' => 'tonalità rosa vero',
        'faith' => 'tonalità bordeaux intenso',
        'grace' => 'tonalità malva rosa acceso',
    ],
    'soft pinch matte bouncy blush' => [
        'worth' => 'tonalità rosa vero',
        'alive' => 'tonalità corallo-arancio acceso',
        'thriving' => 'tonalità lampone acceso',
        'hope' => 'tonalità malva nude',
        'happy' => 'tonalità rosa freddo',
        'grateful' => 'tonalità rosso tenue',
        'truth' => 'tonalità prugna tenue',
        'divine' => 'tonalità tea rose vera',
        'spirited' => 'tonalità viola media',
        'soulful' => 'tonalità bordeaux intenso',
    ],
    'positive light liquid luminizer' => [
        'enlighten' => 'tonalità champagne freddo',
        'enchant' => 'tonalità rosa tenue',
        'mesmerize' => 'tonalità bronzo rosato',
        'outshine' => 'tonalità oro vero',
        'transcend' => 'tonalità oro rosato',
        'flaunt' => 'tonalità oro vero',
        'captivate' => 'tonalità rame',
        'reflect' => 'tonalità bronzo intenso',
        'exhilarate' => 'tonalità champagne dorato',
        'reveal' => 'tonalità rame caldo',
    ],
    'soft pinch tinted lip oil' => [
        'serenity' => 'tonalità rosa caldo',
        'affection' => 'tonalità bacca tenue',
        'happy' => 'tonalità rosa freddo',
        'joy' => 'tonalità pesca tenue',
        'delight' => 'tonalità marrone rosato',
        'hope' => 'tonalità malva nude',
        'wonder' => 'tonalità malva rosato',
        'honesty' => 'tonalità nude marrone',
    ],
    'cheek to chic' => [
        'sex on fire' => 'tonalità rosa fulvo (tawny rose)',
        'first love' => 'tonalità pesca',
        'love glow' => 'tonalità rosa perlato',
        'ecstasy' => 'tonalità pesca rosato',
        'pillow talk' => 'tonalità nude-rosa iconica',
    ],
    'pillow talk' => [
        'pillowtalk fair' => 'tonalità nude-rosa chiara',
        'pillowtalk medium' => 'tonalità nude-rosa media',
        'pillowtalk' => 'tonalità nude-rosa iconica',
        'pillow talk' => 'tonalità nude-rosa iconica',
        'rosy glow' => 'tonalità rosa acceso',
        'refresh rose' => 'tonalità rosa fresco',
        'walk of no shame' => 'tonalità rosa intenso',
    ],
    'easy bake pressed powder' => [
        'sugar cookie' => 'profondità chiara-media, translucida per ogni sottotono',
        'cinnamon bun' => 'profondità ricca, sottotono caldo-neutro',
        'coco truffle' => 'profondità molto ricca, sottotono neutro',
        'cherry blossom cake' => 'profondità chiara, sottotono rosato',
        'pound cake' => 'profondità media',
        'peach cupcake' => 'profondità medio-chiara, sottotono pesca',
        'banana bread' => 'profondità media, sottotono giallo caldo',
        'kunafa blondie' => 'profondità medio-scura, sottotono dorato',
    ],
    'blush filter' => [
        'strawberry cream' => 'tonalità rosa antico',
        'ube cream' => 'tonalità lilla acceso',
    ],
    'faux filler' => [
        'pink lady' => 'tonalità rosa trasparente',
        'juicy peach' => 'tonalità pesca trasparente',
        'juicy goji' => 'tonalità fucsia elettrico trasparente',
    ],
    'halo glow' => [
        'pink-me-up' => 'tonalità rosa acceso',
        'pink me up' => 'tonalità rosa acceso',
        'rose you slay' => 'tonalità rosa chiaro-medio',
        'candlelit' => 'tonalità pesca chiara',
        'berry radiant' => 'tonalità bacca',
    ],
    'glow reviver' => [
        'pink quartz' => 'tonalità rosa chiaro trasparente',
        'coral fixation' => 'tonalità corallo trasparente',
        'money mauve' => 'tonalità malva',
        'jam session' => 'tonalità ciliegia scura',
    ],
    'major glow' => [
        'my love' => 'finish sparkle diamantato',
        'baby' => 'shimmer rosa tenue',
        'sugar' => 'shimmer champagne',
        'daddy' => 'shimmer oro rosato',
        'honey' => 'shimmer bronzo',
    ],
    'dew blush' => [
        'baby' => 'tonalità rosa baby freddo',
        'rosy' => 'tonalità rosa tenue',
        'chilly' => 'tonalità malva',
    ],
    'vanish' => [
        'silk' => 'tonalità chiara naturale, per pelle chiara',
        'pearl' => 'tonalità chiara naturale, per pelle chiara-media',
    ],
    'putty blush' => [
        'fiji' => 'tonalità nella gamma sabbia-rosa, ispirata a mete tropicali',
        'bora bora' => 'tonalità nella gamma sabbia-rosa, ispirata a mete tropicali',
        'bahamas' => 'tonalità nella gamma sabbia-rosa, ispirata a mete tropicali',
        'turks and caicos' => 'tonalità nella gamma sabbia-rosa, ispirata a mete tropicali',
        'caribbean' => 'tonalità nella gamma sabbia-rosa, ispirata a mete tropicali',
        'bali' => 'tonalità nella gamma sabbia-rosa, ispirata a mete tropicali',
        'maldives' => 'tonalità nella gamma sabbia-rosa, ispirata a mete tropicali',
        'tahiti' => 'tonalità nella gamma sabbia-rosa, ispirata a mete tropicali',
    ],
    'rhode blush' => [
        'juice box piggy' => 'tonalità rosa acceso (hot pink/baby pink)',
        'juice box' => 'tonalità rosa acceso (hot pink)',
        'piggy' => 'tonalità rosa baby',
        'spicy marg' => 'tonalità corallo acceso',
        'sprinkle' => 'tonalità rosa perlato caldo',
        'tan line sun' => 'tonalità rosa abbronzato/arancio speziato',
        'tan line' => 'tonalità rosa abbronzato',
        'soak' => 'tonalità arancio speziato',
    ],
    'fenty beauty lip gloss' => [
        'riri' => 'tonalità firma del brand (nude-rosa)',
        'fruit snackz' => 'tonalità rosso bacca intenso',
    ],
    'fenty beauty ice' => [
        'pine apple' => 'richiamo ananas',
        "cold heart" => 'nota fresca mentolata',
    ],
    'ambient lighting edit flushed' => [
        'mood exposure' => 'tonalità prugna tenue',
    ],
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

function product_line_variant_override(string $productName, string $rawVariant): ?string
{
    $productLower = mb_strtolower($productName, 'UTF-8');
    $variantLower = mb_strtolower($rawVariant, 'UTF-8');

    foreach (PRODUCT_LINE_VARIANT_OVERRIDES as $productKey => $variantMap) {
        if (!str_contains($productLower, $productKey)) {
            continue;
        }
        foreach ($variantMap as $variantKey => $descriptor) {
            if (str_contains($variantLower, $variantKey)) {
                return $descriptor;
            }
        }
    }

    return null;
}

/**
 * Vero se la variante è, di fatto, solo un codice (numeri, #, sigle di
 * sottotono tipo N/C/W/O attaccate a un numero) senza nessuna parola reale
 * — cioè nessuna sequenza di 3+ lettere consecutive. In quel caso non ha
 * senso descrivere un "colore": si segnala solo che è un codice tonalità.
 */
function looks_like_pure_shade_code(string $rawVariant): bool
{
    // "NEW" è un prefisso ricorrente nel dato grezzo (es. "NEW 01"), non una
    // parola descrittiva: va ignorato prima di cercare una sequenza di 3+
    // lettere reali, altrimenti "NEW 01" non verrebbe mai trattato come codice.
    $withoutNoise = preg_replace('/\bnew\b/i', '', $rawVariant) ?? $rawVariant;
    return preg_match('/[a-zA-Z]{3,}/', $withoutNoise) !== 1;
}

/**
 * Vero se la stringa non è affatto una variante reale ma un'etichetta
 * residua dell'estrazione dal PDF fornitore (es. "total 28 colors" che
 * indicava il conteggio delle tonalità, finito per errore nell'elenco).
 */
function is_metadata_variant(string $rawVariant): bool
{
    return preg_match('/^\s*total\s+\d+\s+colou?rs?\s*$/i', $rawVariant) === 1;
}

function variant_descriptor(string $rawVariant, ?string $type, string $productName = ''): string
{
    $displayName = clean_variant_label($rawVariant);
    $lower = mb_strtolower($rawVariant, 'UTF-8');

    $override = product_line_variant_override($productName, $rawVariant);
    if ($override !== null) {
        return "{$displayName} — {$override}.";
    }

    if (str_contains($lower, 'iphone') || str_contains($lower, 'samsung') || preg_match('/pro\s?max/', $lower) === 1) {
        return "{$displayName} — formato compatibile.";
    }

    foreach (VARIANT_KEYWORDS as $keyword => $descriptor) {
        if (str_contains($lower, $keyword)) {
            return "{$displayName} — {$descriptor}.";
        }
    }

    foreach (DEPTH_KEYWORDS as $keyword => $descriptor) {
        if (str_contains($lower, $keyword)) {
            return "{$displayName} — {$descriptor}.";
        }
    }

    if (looks_like_pure_shade_code($rawVariant)) {
        return "{$displayName} — codice tonalità nella gamma del brand.";
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
    // Prodotti a varianti raggruppate (es. Colore + Modello iPhone: `variants`
    // è un oggetto {"Colore": [...], "Modello iPhone": [...]} invece di un
    // semplice array) usano solo il primo gruppo per le frasi descrittive: il
    // secondo (es. il modello del telefono) non è materiale da descrizione.
    if (!array_is_list($variants)) {
        $variants = array_values($variants)[0] ?? [];
    }

    $variants = array_values(array_filter($variants, fn($v) => !is_metadata_variant($v)));

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
        $bullets = array_map(fn($v) => '• ' . variant_descriptor($v, $type, $name), $variants);
        $parts[] = "Varianti disponibili:\n" . implode("\n", $bullets);
    }

    return implode("\n\n", array_filter($parts, fn($p) => $p !== ''));
}
