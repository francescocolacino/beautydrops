<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/description-generator.php';

$catalogPath = __DIR__ . '/../data/catalog-products.json';
$catalog = json_decode((string) file_get_contents($catalogPath), true, 512, JSON_THROW_ON_ERROR);

foreach ($catalog as &$product) {
    $product['description'] = generate_rich_description($product['name'], $product['variants'] ?? []);
}
unset($product);

file_put_contents(
    $catalogPath,
    json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
);

echo 'Descrizioni aggiornate: ' . count($catalog) . PHP_EOL;
