<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = get_db();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

    if ($count === 0) {
        // La tabella è vuota: l'import crea il catalogo usando anche i prezzi
        // recuperati e salvati nel JSON versionato.
        require __DIR__ . '/import-catalog.php';
    } else {
        echo "Catalogo già presente: importazione saltata\n";
    }

    // A ogni avvio ripristina soltanto i prezzi mancanti. I prezzi già
    // presenti, inclusi quelli cambiati in seguito dall'admin, restano intatti.
    $catalogPath = __DIR__ . '/../data/catalog-products.json';
    $catalogJson = file_get_contents($catalogPath);
    if ($catalogJson === false) {
        throw new RuntimeException('Impossibile leggere data/catalog-products.json.');
    }

    $products = json_decode($catalogJson, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($products)) {
        throw new RuntimeException('Il catalogo non contiene un elenco valido.');
    }

    $restorePrice = $pdo->prepare(
        'UPDATE products SET price = :price
         WHERE image_path = :image_path AND price IS NULL'
    );
    $restoredCount = 0;

    $pdo->beginTransaction();
    try {
        foreach ($products as $index => $product) {
            $price = $product['price'] ?? null;
            if ($price === null) {
                continue;
            }
            if (!is_numeric($price) || (float) $price <= 0 || empty($product['image_path'])) {
                throw new RuntimeException("Prezzo non valido nel prodotto #{$index}.");
            }

            $restorePrice->execute([
                'price' => (float) $price,
                'image_path' => $product['image_path'],
            ]);
            $restoredCount += $restorePrice->rowCount();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    echo "Prezzi catalogo ripristinati: {$restoredCount}\n";
} catch (Throwable $e) {
    error_log('Seed del catalogo fallito: ' . $e->getMessage());
    fwrite(STDERR, "Errore durante l'importazione del catalogo iniziale.\n");
    exit(1);
}
