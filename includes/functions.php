<?php
require_once __DIR__ . '/auth.php';

const CATEGORIES = [
    'cosmetici' => 'Beauty e Cosmetici',
    'elettronica' => 'Tech e Audio',
    'abbigliamento' => 'Abbigliamento, Borse e Scarpe',
];

/**
 * Tipo di prodotto (es. "blush", "mascara"): campo opzionale, usato per il
 * filtro a tendina nelle pagine categoria. Non è una categoria a sé (resta
 * dentro `category`), solo un'etichetta più specifica facoltativa.
 */
const PRODUCT_TYPES = [
    'fondotinta' => 'Fondotinta',
    'correttore' => 'Correttore',
    'cipria' => 'Cipria',
    'blush' => 'Blush',
    'bronzer' => 'Bronzer e contouring',
    'illuminante' => 'Illuminante',
    'primer' => 'Primer',
    'spray_fissante' => 'Spray fissante',
    'mascara' => 'Mascara',
    'ombretto' => 'Ombretto',
    'rossetto' => 'Rossetto',
    'gloss' => 'Gloss e olio labbra',
    'matita_labbra' => 'Matita labbra',
    'detergente' => 'Detergente',
    'maschera' => 'Maschera viso',
    'siero' => 'Sérum e trattamento',
    'crema' => 'Crema viso',
    'olio_capelli' => 'Olio per capelli',
    'profumo_capelli' => 'Profumo per capelli',
    'profumo' => 'Profumo',
    'set' => 'Set e kit',
    'accessorio' => 'Accessorio',
];

function product_type_label(?string $slug): string
{
    return $slug !== null && isset(PRODUCT_TYPES[$slug]) ? PRODUCT_TYPES[$slug] : '';
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function category_label(string $slug): string
{
    return CATEGORIES[$slug] ?? $slug;
}

function brand_anchor(string $brand): string
{
    return 'brand-' . substr(hash('sha256', $brand), 0, 12);
}

const FEATURED_BRANDS = [
    'Huda Beauty',
    'Rhode',
    'Charlotte Tilbury',
    'Rare Beauty',
    'Summer Fridays',
    'Sol de Janeiro',
    'Laneige',
    'Dior',
    'E.L.F.',
    'Tarte',
];

/**
 * Ordina i brand: prima quelli in FEATURED_BRANDS (nell'ordine indicato),
 * poi gli altri dal più al meno rifornito.
 *
 * @param array<string, array> $byBrand prodotti raggruppati per brand
 * @return array<string, array>
 */
function order_brands_by_priority(array $byBrand): array
{
    $priority = array_flip(array_map('mb_strtolower', FEATURED_BRANDS));

    $brands = array_keys($byBrand);
    usort($brands, function (string $a, string $b) use ($priority, $byBrand) {
        $pa = $priority[mb_strtolower($a)] ?? PHP_INT_MAX;
        $pb = $priority[mb_strtolower($b)] ?? PHP_INT_MAX;
        if ($pa !== $pb) {
            return $pa <=> $pb;
        }
        $countDiff = count($byBrand[$b]) - count($byBrand[$a]);
        if ($countDiff !== 0) {
            return $countDiff;
        }
        return strnatcasecmp($a, $b);
    });

    $ordered = [];
    foreach ($brands as $brand) {
        $ordered[$brand] = $byBrand[$brand];
    }
    return $ordered;
}

function extract_product_size(string $name): ?string
{
    if (preg_match('/(\d+(?:[.,]\d+)?\s?(?:ml|g|kg|l))\b/i', $name, $matches)) {
        return str_replace(',', '.', strtolower($matches[1]));
    }
    return null;
}

function format_price(?float $price): string
{
    if ($price === null) {
        return '';
    }
    return '€ ' . number_format($price, 2, ',', '.');
}

function decode_variants(?string $json): array
{
    if (empty($json)) {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Nome da mostrare al pubblico: se il prodotto ha una sola variante, viene
 * assorbita nel titolo (es. "Lip Sleeping Mask — Berry") invece di essere
 * proposta come scelta. Con 0 o 2+ varianti il nome resta invariato.
 */
function display_product_name(string $name, array $variants): string
{
    if (count($variants) === 1) {
        return $name . ' — ' . $variants[0];
    }
    return $name;
}

/**
 * Varianti da proporre come scelta al cliente: vuoto se il prodotto ne ha
 * 0 (nessuna scelta necessaria) o 1 sola (già assorbita nel titolo).
 */
function selectable_variants(array $variants): array
{
    return count($variants) >= 2 ? $variants : [];
}

/**
 * Sconto quantità per riga di carrello: 2 pezzi -5%, 3-4 pezzi -10%, 5+ pezzi -15%.
 */
function quantity_discount_percent(int $quantity): int
{
    if ($quantity >= 5) {
        return 15;
    }
    if ($quantity >= 3) {
        return 10;
    }
    if ($quantity >= 2) {
        return 5;
    }
    return 0;
}

function generate_order_token(): string
{
    return bin2hex(random_bytes(20));
}

function encode_variants_from_input(string $commaSeparated): ?string
{
    $parts = array_filter(array_map('trim', explode(',', $commaSeparated)), fn($v) => $v !== '');
    $parts = array_values($parts);
    if (empty($parts)) {
        return null;
    }
    return json_encode($parts, JSON_UNESCAPED_UNICODE);
}

function get_active_offer_product_ids(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT DISTINCT op.product_id
         FROM offer_products op
         INNER JOIN offers o ON o.id = op.offer_id
         WHERE o.active = TRUE'
    )->fetchAll(PDO::FETCH_COLUMN);

    return array_map('intval', $rows);
}

function render_product_badges(array $product, array $activeOfferProductIds): string
{
    $badges = [];

    if ((int)$product['stock_quantity'] >= 1) {
        $badges[] = '<span class="badge badge-available">Disponibile</span>';
    }
    if ((int)$product['orderable_quantity'] >= 1) {
        $badges[] = '<span class="badge badge-orderable">Ordinabile</span>';
    }
    if (in_array((int)$product['id'], $activeOfferProductIds, true)) {
        $badges[] = '<span class="badge badge-offer">In offerta</span>';
    }

    if (empty($badges)) {
        return '';
    }

    return '<div class="badges">' . implode('', $badges) . '</div>';
}

/**
 * @param array $products elenco prodotti dell'offerta (con campo 'price')
 * @return array{sum: ?float, discount_percent: ?int}
 */
function calculate_offer_savings(float $offerPrice, array $products): array
{
    $sum = 0.0;
    foreach ($products as $product) {
        if ($product['price'] === null) {
            return ['sum' => null, 'discount_percent' => null];
        }
        $sum += (float)$product['price'];
    }

    if ($sum <= 0) {
        return ['sum' => null, 'discount_percent' => null];
    }

    $discount = (int)round((1 - ($offerPrice / $sum)) * 100);

    return ['sum' => $sum, 'discount_percent' => max(0, $discount)];
}

function get_offer_products(PDO $pdo, int $offerId): array
{
    $stmt = $pdo->prepare(
        'SELECT p.* FROM offer_products op
         INNER JOIN products p ON p.id = op.product_id
         WHERE op.offer_id = :offer_id
         ORDER BY p.name ASC'
    );
    $stmt->execute(['offer_id' => $offerId]);
    return $stmt->fetchAll();
}

/**
 * Estensioni eseguibili come PHP da negare tassativamente, a prescindere
 * dal risultato dei controlli MIME/immagine: il server PHP integrato
 * (`php -S`, usato in produzione su Railway) non applica le regole .htaccess
 * che su Apache bloccavano l'esecuzione di script in questa cartella.
 */
const FORBIDDEN_UPLOAD_EXTENSIONS = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar'];

function upload_product_image(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Errore durante il caricamento del file (codice ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Il file supera la dimensione massima consentita (5MB).');
    }

    $originalExtension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (in_array($originalExtension, FORBIDDEN_UPLOAD_EXTENSIONS, true)) {
        throw new RuntimeException('Formato immagine non supportato. Usa JPG, PNG o WEBP.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo !== false ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo !== false) {
        finfo_close($finfo);
    }
    if ($mime === false || !isset($allowed[$mime])) {
        throw new RuntimeException('Formato immagine non supportato. Usa JPG, PNG o WEBP.');
    }

    // getimagesize() decodifica realmente l'intestazione dell'immagine: un
    // file che superasse il controllo finfo ma non sia un'immagine valida
    // (o il cui mime rilevato non coincide) viene comunque respinto.
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false || !isset($imageInfo['mime']) || $imageInfo['mime'] !== $mime) {
        throw new RuntimeException('Il file non è un\'immagine valida.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Nome file generato internamente: il nome fornito dall'utente non viene
    // mai riutilizzato, solo l'estensione determinata dal mime verificato.
    $filename = uniqid('prod_', true) . '.' . $allowed[$mime];
    $destination = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Impossibile salvare il file caricato.');
    }

    return UPLOAD_URL_PATH . $filename;
}

function delete_product_image(?string $imagePath): void
{
    if (empty($imagePath)) {
        return;
    }
    $filename = basename($imagePath);
    $fullPath = UPLOAD_DIR . $filename;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function url(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}
