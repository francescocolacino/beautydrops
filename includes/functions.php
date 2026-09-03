<?php
require_once __DIR__ . '/auth.php';

const CATEGORIES = [
    'cosmetici' => 'Beauty e Cosmetici',
    'elettronica' => 'Tech e Audio',
    'abbigliamento' => 'Abbigliamento, Borse e Scarpe',
];

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
         WHERE o.active = 1'
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

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Formato immagine non supportato. Usa JPG, PNG o WEBP.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

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
