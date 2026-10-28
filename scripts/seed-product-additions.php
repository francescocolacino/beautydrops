<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

/**
 * Aggiunge al catalogo live i nuovi prodotti descritti nei file
 * data/product-additions/*.json (uno per lotto, foto versionate sotto
 * assets/images/catalog/).
 *
 * Gira all'avvio del container, dopo seed-catalog.php e seed-galleries.php,
 * ma ogni lotto viene applicato una sola volta: il nome del file viene
 * registrato in data_migrations, così un prodotto eliminato o modificato
 * dall'admin non viene mai ricreato o sovrascritto dai deploy successivi.
 * Lo script inserisce soltanto: non aggiorna né cancella righe esistenti, e
 * salta i prodotti la cui foto di copertina è già usata da un altro prodotto.
 */

try {
    $pdo = get_db();

    $batchDir = __DIR__ . '/../data/product-additions';
    $batchFiles = glob($batchDir . '/*.json') ?: [];
    sort($batchFiles);
    if ($batchFiles === []) {
        echo "Nessun lotto di nuovi prodotti da importare\n";
        exit(0);
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS data_migrations (
            name VARCHAR(191) PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $migrationCheck = $pdo->prepare('SELECT 1 FROM data_migrations WHERE name = :name');
    $markMigration = $pdo->prepare('INSERT INTO data_migrations (name) VALUES (:name)');
    $findByImage = $pdo->prepare('SELECT 1 FROM products WHERE image_path = :image_path');
    $insertProduct = $pdo->prepare(
        'INSERT INTO products (
            category, brand, name, product_type, variants, image_path,
            stock_quantity, orderable_quantity, price, description
        ) VALUES (
            :category, :brand, :name, :product_type, :variants, :image_path,
            0, 0, :price, :description
        ) RETURNING id'
    );
    $insertGalleryImage = $pdo->prepare(
        'INSERT INTO product_gallery_images (product_id, image_path, sort_order) VALUES (:product_id, :image_path, :sort_order)'
    );

    $projectRoot = dirname(__DIR__);
    $assertImageExists = static function (string $imagePath, string $label) use ($projectRoot): void {
        if (!str_starts_with($imagePath, '/assets/images/') || !is_file($projectRoot . $imagePath)) {
            throw new RuntimeException("Foto mancante per {$label}: {$imagePath}");
        }
    };

    foreach ($batchFiles as $batchFile) {
        $migrationName = 'product-additions/' . basename($batchFile);
        $migrationCheck->execute(['name' => $migrationName]);
        if ($migrationCheck->fetchColumn()) {
            continue;
        }

        $products = json_decode((string) file_get_contents($batchFile), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($products) || !array_is_list($products)) {
            throw new RuntimeException(basename($batchFile) . ' non contiene un elenco di prodotti valido.');
        }

        $inserted = 0;
        $skipped = 0;
        $galleryInserted = 0;

        $pdo->beginTransaction();
        try {
            foreach ($products as $index => $product) {
                $label = basename($batchFile) . " #{$index}";
                foreach (['category', 'brand', 'name', 'image_path', 'description'] as $field) {
                    if (!isset($product[$field]) || !is_string($product[$field]) || trim($product[$field]) === '') {
                        throw new RuntimeException("Campo {$field} non valido in {$label}.");
                    }
                }
                if (!array_key_exists($product['category'], CATEGORIES)) {
                    throw new RuntimeException("Categoria non valida in {$label}.");
                }
                $productType = $product['product_type'] ?? null;
                if ($productType !== null && !isset(PRODUCT_TYPES[$productType])) {
                    throw new RuntimeException("Tipo prodotto non valido in {$label}.");
                }
                $variants = $product['variants'] ?? [];
                $gallery = $product['gallery'] ?? [];
                if (!is_array($variants) || !is_array($gallery)) {
                    throw new RuntimeException("Varianti o galleria non valide in {$label}.");
                }
                $price = $product['price'] ?? null;
                if ($price !== null && (!is_numeric($price) || (float) $price <= 0)) {
                    throw new RuntimeException("Prezzo non valido in {$label}.");
                }

                $assertImageExists($product['image_path'], $label);
                foreach ($gallery as $galleryPath) {
                    $assertImageExists((string) $galleryPath, $label);
                }

                $findByImage->execute(['image_path' => $product['image_path']]);
                if ($findByImage->fetchColumn()) {
                    $skipped++;
                    continue;
                }

                $insertProduct->execute([
                    'category' => $product['category'],
                    'brand' => $product['brand'],
                    'name' => $product['name'],
                    'product_type' => $productType,
                    'variants' => $variants === []
                        ? null
                        : json_encode(array_values($variants), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'image_path' => $product['image_path'],
                    'price' => $price === null ? null : (float) $price,
                    'description' => $product['description'],
                ]);
                $productId = (int) $insertProduct->fetchColumn();
                $inserted++;

                foreach (array_values($gallery) as $sortOrder => $galleryPath) {
                    $insertGalleryImage->execute([
                        'product_id' => $productId,
                        'image_path' => $galleryPath,
                        'sort_order' => $sortOrder,
                    ]);
                    $galleryInserted++;
                }
            }

            $markMigration->execute(['name' => $migrationName]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        echo basename($batchFile) . ": prodotti aggiunti {$inserted}, foto di galleria {$galleryInserted}";
        echo $skipped > 0 ? ", già presenti {$skipped}\n" : "\n";
    }
} catch (Throwable $e) {
    error_log('Import nuovi prodotti fallito: ' . $e->getMessage());
    fwrite(STDERR, "Errore durante l'importazione dei nuovi prodotti.\n");
    exit(1);
}
