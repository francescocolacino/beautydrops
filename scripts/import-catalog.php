<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$catalogPath = __DIR__ . '/../data/catalog-products.json';
$catalogJson = file_get_contents($catalogPath);
if ($catalogJson === false) {
    throw new RuntimeException('Impossibile leggere data/catalog-products.json.');
}

$products = json_decode($catalogJson, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($products)) {
    throw new RuntimeException('Il catalogo non contiene un elenco valido.');
}

$pdo = get_db();
$pdo->beginTransaction();

try {
    // Rimuove soltanto le schede generate da questo catalogo, lasciando intatti
    // gli eventuali prodotti caricati manualmente dall'amministrazione.
    $pdo->exec("DELETE FROM products WHERE image_path LIKE '/assets/images/catalog/%'");

    $insert = $pdo->prepare(
        'INSERT INTO products (
            category, brand, name, variants, image_path,
            stock_quantity, orderable_quantity, price, description
        ) VALUES (
            :category, :brand, :name, :variants, :image_path,
            0, 0, NULL, :description
        )'
    );

    foreach ($products as $index => $product) {
        foreach (['category', 'brand', 'name', 'image_path', 'description'] as $field) {
            if (!isset($product[$field]) || !is_string($product[$field])) {
                throw new RuntimeException("Campo {$field} non valido nel prodotto #{$index}.");
            }
        }

        $variants = $product['variants'] ?? [];
        if (!is_array($variants)) {
            throw new RuntimeException("Varianti non valide nel prodotto #{$index}.");
        }

        $insert->execute([
            'category' => $product['category'],
            'brand' => $product['brand'],
            'name' => $product['name'],
            'variants' => $variants === []
                ? null
                : json_encode(array_values($variants), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'image_path' => $product['image_path'],
            'description' => $product['description'],
        ]);
    }

    $pdo->commit();
    $catalogCount = (int)$pdo
        ->query("SELECT COUNT(*) FROM products WHERE image_path LIKE '/assets/images/catalog/%'")
        ->fetchColumn();
    $pricedCount = (int)$pdo
        ->query("SELECT COUNT(*) FROM products WHERE image_path LIKE '/assets/images/catalog/%' AND price IS NOT NULL")
        ->fetchColumn();
    $brandCount = (int)$pdo
        ->query("SELECT COUNT(DISTINCT brand) FROM products WHERE image_path LIKE '/assets/images/catalog/%'")
        ->fetchColumn();

    echo "Import completato:\n";
    echo "- prodotti: {$catalogCount}\n";
    echo "- brand: {$brandCount}\n";
    echo "- prodotti con prezzo: {$pricedCount}\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}
