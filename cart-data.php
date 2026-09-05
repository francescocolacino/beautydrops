<?php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$ids = is_array($body['ids'] ?? null) ? array_values(array_unique(array_map('intval', $body['ids']))) : [];
$ids = array_filter($ids, fn($id) => $id > 0);

if (empty($ids)) {
    echo json_encode(['products' => []]);
    exit;
}

$pdo = get_db();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
$stmt->execute($ids);
$rows = $stmt->fetchAll();

$activeOfferProductIds = get_active_offer_product_ids($pdo);

$products = [];
foreach ($rows as $row) {
    $variants = decode_variants($row['variants']);
    $products[] = [
        'id' => (int) $row['id'],
        'brand' => $row['brand'],
        'name' => display_product_name($row['name'], $variants),
        'image' => !empty($row['image_path']) ? url($row['image_path']) : null,
        'price' => $row['price'] !== null ? (float) $row['price'] : null,
        'variants' => selectable_variants($variants),
        'variantPrices' => is_variant_groups($variants) ? [] : decode_variant_prices($row['variant_prices'] ?? null),
        'inOffer' => in_array((int) $row['id'], $activeOfferProductIds, true),
        'productUrl' => url('product.php?id=' . (int) $row['id']),
    ];
}

echo json_encode(['products' => $products], JSON_UNESCAPED_UNICODE);
