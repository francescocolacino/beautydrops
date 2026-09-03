<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

/**
 * Popola le foto aggiuntive di prodotto nel database a partire dai dati
 * curati in data/product-galleries.json, promuovendo la prima foto nuova a
 * copertina del prodotto (products.image_path) e lasciando le restanti come
 * galleria: la vecchia copertina a bassa risoluzione del catalogo PDF non
 * viene più referenziata da nessuna parte, né come copertina né in galleria.
 *
 * Gira all'avvio del container (come scripts/seed-catalog.php) ma è
 * idempotente: se product_gallery_images contiene già righe non fa nulla,
 * così non sovrascrive mai foto caricate o riordinate dall'admin dopo il
 * primo deploy.
 */

try {
    $pdo = get_db();

    $existing = (int) $pdo->query('SELECT COUNT(*) FROM product_gallery_images')->fetchColumn();
    if ($existing > 0) {
        echo "Galleria già presente: importazione saltata\n";
        exit(0);
    }

    $dataPath = __DIR__ . '/../data/product-galleries.json';
    if (!is_file($dataPath)) {
        echo "Nessun file data/product-galleries.json: nulla da importare.\n";
        exit(0);
    }

    $galleries = json_decode((string) file_get_contents($dataPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($galleries)) {
        throw new RuntimeException('data/product-galleries.json non contiene un oggetto valido.');
    }

    $findProduct = $pdo->prepare('SELECT id FROM products WHERE image_path = :old_cover');
    $updateCover = $pdo->prepare('UPDATE products SET image_path = :new_cover WHERE id = :id');
    $insertGalleryImage = $pdo->prepare(
        'INSERT INTO product_gallery_images (product_id, image_path, sort_order) VALUES (:product_id, :image_path, :sort_order)'
    );

    $pdo->beginTransaction();

    $productsUpdated = 0;
    $imagesInserted = 0;
    $skippedNoMatch = 0;

    foreach ($galleries as $oldCoverPath => $newPhotos) {
        if (!is_array($newPhotos) || empty($newPhotos)) {
            continue;
        }
        $newPhotos = array_values(array_filter($newPhotos, fn($p) => is_string($p) && trim($p) !== ''));
        if (empty($newPhotos)) {
            continue;
        }

        $findProduct->execute(['old_cover' => $oldCoverPath]);
        $productId = $findProduct->fetchColumn();
        if ($productId === false) {
            $skippedNoMatch++;
            continue;
        }
        $productId = (int) $productId;

        $newCover = array_shift($newPhotos);
        $updateCover->execute(['new_cover' => $newCover, 'id' => $productId]);
        $productsUpdated++;

        $sortOrder = 0;
        foreach ($newPhotos as $imagePath) {
            $insertGalleryImage->execute([
                'product_id' => $productId,
                'image_path' => $imagePath,
                'sort_order' => $sortOrder++,
            ]);
            $imagesInserted++;
        }
    }

    $pdo->commit();

    echo "Copertine aggiornate: {$productsUpdated}\n";
    echo "Foto di galleria inserite: {$imagesInserted}\n";
    if ($skippedNoMatch > 0) {
        echo "Voci saltate (copertina non trovata nel database): {$skippedNoMatch}\n";
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Seed della galleria fallito: ' . $e->getMessage());
    fwrite(STDERR, "Errore durante l'importazione della galleria foto.\n");
    exit(1);
}
