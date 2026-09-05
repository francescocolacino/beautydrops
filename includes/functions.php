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

/**
 * Fotografie aggiuntive di un prodotto (oltre alla copertina), lette dal
 * database — non dal filesystem: gli upload/eliminazioni fatti
 * dall'admin devono sopravvivere ai redeploy del container.
 *
 * @return list<array{id:int,image_path:string}>
 */
function get_product_gallery_rows(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, image_path FROM product_gallery_images WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['product_id' => $productId]);
    return $stmt->fetchAll();
}

/**
 * Immagine di copertina + fotografie aggiuntive di un prodotto, nell'ordine
 * in cui vanno mostrate nella galleria della pagina prodotto pubblica.
 *
 * @return list<string>
 */
function product_gallery_images(PDO $pdo, ?string $primaryImagePath, int $productId): array
{
    $images = [];
    $primaryImagePath = trim((string)$primaryImagePath);
    if ($primaryImagePath !== '') {
        $images[] = $primaryImagePath;
    }
    foreach (get_product_gallery_rows($pdo, $productId) as $row) {
        $images[] = $row['image_path'];
    }
    return array_values(array_unique($images));
}

function next_gallery_sort_order(PDO $pdo, int $productId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_gallery_images WHERE product_id = :product_id');
    $stmt->execute(['product_id' => $productId]);
    return (int) $stmt->fetchColumn();
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

/**
 * Ordina i prodotti all'interno di un brand quando il catalogo richiede una
 * sequenza editoriale diversa da quella alfabetica. Per Rhode mostriamo prima
 * i cosmetici/trattamenti nell'ordine richiesto e soltanto dopo packaging e
 * accessori (scatole, carta, patch, cover, borse, ecc.).
 *
 * @param array<int, array> $products
 * @return array<int, array>
 */
function order_products_for_brand(string $brand, array $products): array
{
    if (mb_strtolower(trim($brand), 'UTF-8') !== 'rhode') {
        return $products;
    }

    usort($products, function (array $a, array $b): int {
        $rankDiff = rhode_product_display_rank((string)($a['name'] ?? ''))
            <=> rhode_product_display_rank((string)($b['name'] ?? ''));
        if ($rankDiff !== 0) {
            return $rankDiff;
        }

        // All'interno dello stesso gruppo conserva la sequenza del catalogo
        // Rhode (rh-1, rh-2, ...); per eventuali inserimenti manuali usa il nome.
        $numberA = catalog_product_number((string)($a['image_path'] ?? ''));
        $numberB = catalog_product_number((string)($b['image_path'] ?? ''));
        if ($numberA !== $numberB) {
            return $numberA <=> $numberB;
        }

        return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });

    return array_values($products);
}

function rhode_product_display_rank(string $name): int
{
    $name = mb_strtolower(trim($name), 'UTF-8');

    // Gli accessori vanno riconosciuti prima delle parole "gloss" e "set":
    // ad esempio "Magnetic Phone Case with Gloss" resta una cover.
    if (str_contains($name, 'paper box')) {
        return 200;
    }
    if (str_contains($name, 'paper')) {
        return 210;
    }
    if (str_contains($name, 'pimple patch')) {
        return 220;
    }
    if (str_contains($name, 'phone case')) {
        return 230;
    }
    if (str_contains($name, 'makeup bag')) {
        return 240;
    }
    if (
        str_contains($name, 'patch')
        || str_contains($name, 'mirror')
        || str_contains($name, 'hairband')
        || str_contains($name, 'sticker')
        || $name === 'card'
        || str_contains($name, 'towel')
    ) {
        return 250;
    }

    // Priorità dei prodotti Rhode: Milk, Blush, Mist, Fluid, Gloss, Set,
    // Pineapple e Barrier. Gli altri cosmetici restano comunque prima degli
    // accessori e mantengono tra loro l'ordine originario del catalogo.
    if (str_contains($name, 'milk')) {
        return 10;
    }
    if (str_contains($name, 'blush')) {
        return 20;
    }
    if (str_contains($name, 'mist')) {
        return 30;
    }
    if (str_contains($name, 'fluid')) {
        return 40;
    }
    if (str_contains($name, 'gloss') && !str_contains($name, 'set')) {
        return 50;
    }
    if (str_contains($name, 'gloss') && str_contains($name, 'set')) {
        return 60;
    }
    if (str_contains($name, 'pineapple')) {
        return 70;
    }
    if (str_contains($name, 'barrier')) {
        return 80;
    }

    return 100;
}

function catalog_product_number(string $imagePath): int
{
    if (preg_match('~/rh-(\d+)(?:[-.]|$)~i', $imagePath, $matches)) {
        return (int)$matches[1];
    }
    return PHP_INT_MAX;
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

function decode_variant_prices(?string $json): array
{
    if (empty($json)) {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Prezzo per la variante scelta: usa l'override in variant_prices se
 * presente per quella variante, altrimenti il prezzo base del prodotto.
 * Solo per varianti "piatte" (non raggruppate) — non si applica a
 * combinazioni colore+modello.
 */
function resolve_variant_price(?float $basePrice, array $variantPrices, ?string $variant): ?float
{
    if ($variant !== null && array_key_exists($variant, $variantPrices)) {
        return (float) $variantPrices[$variant];
    }
    return $basePrice;
}

/**
 * Converte l'input admin "Variante:Prezzo" (una coppia per riga o separate
 * da virgola) in mappa variante => prezzo, scartando le righe che non
 * corrispondono a una delle varianti valide del prodotto.
 */
function encode_variant_prices_from_input(string $input, array $validVariants): ?string
{
    $map = [];
    foreach (preg_split('/[\r\n,]+/', $input) as $part) {
        $part = trim($part);
        if ($part === '' || !str_contains($part, ':')) {
            continue;
        }
        [$label, $rawPrice] = array_map('trim', explode(':', $part, 2));
        if ($label === '' || !in_array($label, $validVariants, true)) {
            continue;
        }
        $price = (float) str_replace(',', '.', $rawPrice);
        if ($price > 0) {
            $map[$label] = $price;
        }
    }
    return empty($map) ? null : json_encode($map, JSON_UNESCAPED_UNICODE);
}

/**
 * Separatore usato per combinare in un'unica stringa la selezione di più
 * gruppi di varianti (es. colore + modello iPhone) prima di salvarla nel
 * carrello/ordine. Deve restare identico alla costante JS in cart.js.
 */
const VARIANT_GROUP_SEPARATOR = ' · ';

/**
 * Un prodotto ha "varianti raggruppate" (es. Colore + Modello iPhone, invece
 * di una singola lista di colori) quando `variants` è un oggetto JSON con
 * chiavi stringa (nome gruppo => elenco opzioni), non un semplice array.
 */
function is_variant_groups(array $variants): bool
{
    if (empty($variants)) {
        return false;
    }
    foreach (array_keys($variants) as $key) {
        if (!is_string($key)) {
            return false;
        }
    }
    return true;
}

/**
 * Nome da mostrare al pubblico: se il prodotto ha una sola variante, viene
 * assorbita nel titolo (es. "Lip Sleeping Mask — Berry") invece di essere
 * proposta come scelta. Con 0 o 2+ varianti il nome resta invariato.
 */
function display_product_name(string $name, array $variants): string
{
    if (!is_variant_groups($variants) && count($variants) === 1) {
        return $name . ' — ' . $variants[0];
    }
    return $name;
}

/**
 * Varianti da proporre come scelta al cliente: vuoto se il prodotto ne ha
 * 0 (nessuna scelta necessaria) o 1 sola (già assorbita nel titolo). I
 * prodotti a varianti raggruppate (colore + modello) usano invece
 * `is_variant_groups()` e vengono gestiti a parte: qui restituiscono [].
 */
function selectable_variants(array $variants): array
{
    if (is_variant_groups($variants)) {
        return [];
    }
    return count($variants) >= 2 ? $variants : [];
}

/**
 * Valida una stringa di variante combinata (es. "Nero · iPhone 11") contro
 * i gruppi di varianti raggruppate del prodotto: deve avere esattamente un
 * segmento per gruppo, nell'ordine dei gruppi, ciascuno tra le opzioni valide.
 */
function is_valid_combined_variant(array $groups, string $variant): bool
{
    $groupOptions = array_values($groups);
    $parts = explode(VARIANT_GROUP_SEPARATOR, $variant);
    if (count($parts) !== count($groupOptions)) {
        return false;
    }
    foreach ($groupOptions as $i => $options) {
        if (!in_array($parts[$i], $options, true)) {
            return false;
        }
    }
    return true;
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

/**
 * Codifica l'input del form admin in JSON per la colonna `variants`.
 *
 * Senza secondo gruppo: lista piatta di colori/varianti (comportamento
 * storico). Con un secondo gruppo (nome + valori compilati): oggetto
 * raggruppato {"Colore": [...], "<nome gruppo 2>": [...]} — usato per i
 * prodotti dove il cliente deve scegliere due varianti indipendenti (es.
 * colore + modello iPhone per le cover Rhode), entrambe obbligatorie in
 * fase di aggiunta al carrello.
 */
function encode_variants_from_input(string $commaSeparated, string $group2Label = '', string $group2CommaSeparated = ''): ?string
{
    $split = fn(string $v) => array_values(array_filter(array_map('trim', explode(',', $v)), fn($p) => $p !== ''));

    $group1 = $split($commaSeparated);
    $group2Label = trim($group2Label);
    $group2 = $split($group2CommaSeparated);

    if ($group2Label !== '' && !empty($group2)) {
        if (empty($group1)) {
            return null;
        }
        return json_encode(['Colore' => $group1, $group2Label => $group2], JSON_UNESCAPED_UNICODE);
    }

    if (empty($group1)) {
        return null;
    }
    return json_encode($group1, JSON_UNESCAPED_UNICODE);
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

/**
 * Normalizza la struttura di $_FILES prodotta da un input multiplo
 * (name="x[]") in un elenco di singoli file, ciascuno nello stesso formato
 * atteso da upload_product_image() (come per un input singolo). I campi
 * lasciati vuoti (nessun file scelto in quello slot) vengono scartati.
 */
function normalize_multi_upload(array $filesField): array
{
    if (!isset($filesField['name'])) {
        return [];
    }
    if (!is_array($filesField['name'])) {
        return $filesField['error'] === UPLOAD_ERR_NO_FILE ? [] : [$filesField];
    }

    $files = [];
    foreach ($filesField['name'] as $index => $name) {
        if ($name === '' || ($filesField['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $files[] = [
            'name' => $filesField['name'][$index],
            'type' => $filesField['type'][$index],
            'tmp_name' => $filesField['tmp_name'][$index],
            'error' => $filesField['error'][$index],
            'size' => $filesField['size'][$index],
        ];
    }
    return $files;
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
