<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdf.php';

header('Content-Type: application/json; charset=utf-8');

function json_error(string $message): void
{
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_error('Metodo non consentito.');
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    json_error('Richiesta non valida.');
}

$firstName = trim((string) ($body['firstName'] ?? ''));
$lastName = trim((string) ($body['lastName'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$rawItems = is_array($body['items'] ?? null) ? $body['items'] : [];

if ($firstName === '' || $lastName === '') {
    json_error('Inserisci nome e cognome.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Inserisci un indirizzo email valido.');
}
if (empty($rawItems)) {
    json_error('Il carrello è vuoto.');
}

$pdo = get_db();
$lines = [];

// Lo sconto quantità vale sulla somma dei pezzi dello stesso prodotto, anche
// se distribuiti su più varianti (es. 1 rosso + 1 blu = 2 pezzi = -5%).
$quantityByProduct = [];
foreach ($rawItems as $rawItem) {
    $productId = (int) ($rawItem['productId'] ?? 0);
    $quantity = (int) ($rawItem['quantity'] ?? 0);
    if ($productId <= 0 || $quantity <= 0) {
        json_error('Riga del carrello non valida.');
    }
    $quantityByProduct[$productId] = ($quantityByProduct[$productId] ?? 0) + $quantity;
}

foreach ($rawItems as $rawItem) {
    $productId = (int) ($rawItem['productId'] ?? 0);
    $quantity = (int) ($rawItem['quantity'] ?? 0);
    $variant = isset($rawItem['variant']) && $rawItem['variant'] !== null ? trim((string) $rawItem['variant']) : null;

    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch();
    if (!$product) {
        json_error('Un prodotto nel carrello non è più disponibile.');
    }

    $variants = decode_variants($product['variants']);

    if (is_variant_groups($variants)) {
        if ($variant === null || $variant === '' || !is_valid_combined_variant($variants, $variant)) {
            json_error('Seleziona tutte le varianti per "' . $product['name'] . '".');
        }
    } else {
        $selectable = selectable_variants($variants);
        if (!empty($selectable)) {
            if ($variant === null || $variant === '' || !in_array($variant, $selectable, true)) {
                json_error('Seleziona una variante valida per "' . $product['name'] . '".');
            }
        } elseif (count($variants) === 1) {
            $variant = $variants[0];
        } else {
            $variant = null;
        }
    }

    $discount = quantity_discount_percent($quantityByProduct[$productId]);
    $unitPrice = $product['price'] !== null ? (float) $product['price'] : null;
    $lineSubtotal = $unitPrice !== null ? $unitPrice * $quantity : null;
    $lineTotal = $lineSubtotal !== null ? round($lineSubtotal * (1 - $discount / 100), 2) : null;

    $lines[] = [
        'product_id' => $productId,
        'brand' => $product['brand'],
        'name' => $product['name'],
        'variant' => $variant,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'discount_percent' => $discount,
        'line_subtotal' => $lineSubtotal,
        'line_total' => $lineTotal,
    ];
}

$subtotal = 0.0;
$total = 0.0;
$hasPriceOnRequest = false;
foreach ($lines as $line) {
    if ($line['unit_price'] === null) {
        $hasPriceOnRequest = true;
        continue;
    }
    $subtotal += $line['line_subtotal'];
    $total += $line['line_total'];
}
$discountTotal = round($subtotal - $total, 2);

$pdo->beginTransaction();
try {
    $token = generate_order_token();
    $stmt = $pdo->prepare(
        'INSERT INTO orders (access_token, customer_first_name, customer_last_name, customer_email, subtotal, discount_total, total, has_price_on_request)
         VALUES (:token, :first_name, :last_name, :email, :subtotal, :discount_total, :total, :on_request)
         RETURNING id'
    );
    $stmt->execute([
        'token' => $token,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'subtotal' => round($subtotal, 2),
        'discount_total' => $discountTotal,
        'total' => round($total, 2),
        'on_request' => $hasPriceOnRequest ? 1 : 0,
    ]);
    $orderId = (int) $stmt->fetchColumn();

    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, brand, name, variant, quantity, unit_price, discount_percent, line_total)
         VALUES (:order_id, :product_id, :brand, :name, :variant, :quantity, :unit_price, :discount_percent, :line_total)'
    );
    foreach ($lines as $line) {
        $itemStmt->execute([
            'order_id' => $orderId,
            'product_id' => $line['product_id'],
            'brand' => $line['brand'],
            'name' => $line['name'],
            'variant' => $line['variant'],
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'discount_percent' => $line['discount_percent'],
            'line_total' => $line['line_total'],
        ]);
    }

    $pdfPath = generate_order_pdf(
        [
            'id' => $orderId,
            'access_token' => $token,
            'customer_first_name' => $firstName,
            'customer_last_name' => $lastName,
            'customer_email' => $email,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'total' => $total,
            'has_price_on_request' => $hasPriceOnRequest,
            'created_at' => date('Y-m-d H:i:s'),
        ],
        array_map(static function ($line) {
            return [
                'brand' => $line['brand'],
                'name' => $line['name'],
                'variant' => $line['variant'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_percent' => $line['discount_percent'],
                'line_total' => $line['line_total'],
            ];
        }, $lines)
    );

    $pdo->prepare('UPDATE orders SET pdf_path = :pdf_path WHERE id = :id')
        ->execute(['pdf_path' => $pdfPath, 'id' => $orderId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error('Impossibile completare l\'ordine. Riprova tra poco.');
}

echo json_encode([
    'success' => true,
    'redirect' => url('order-confirmation.php?order=' . $orderId . '&token=' . $token),
]);
