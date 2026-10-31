<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

/**
 * Sostituisce le foto di prodotti già presenti nel catalogo live secondo i
 * file data/photo-updates/*.json: per ogni prodotto (cercato per brand e
 * nome) la prima foto dell'elenco diventa la copertina e le altre la
 * galleria, nell'ordine indicato.
 *
 * Gira all'avvio del container dopo gli altri seed, ma ogni file viene
 * applicato una sola volta (registrato in data_migrations): eventuali foto
 * cambiate dall'admin in seguito non vengono più toccate.
 */

try {
    $pdo = get_db();

    $updateFiles = glob(__DIR__ . '/../data/photo-updates/*.json') ?: [];
    sort($updateFiles);
    if ($updateFiles === []) {
        echo "Nessun aggiornamento foto da applicare\n";
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
    $findProduct = $pdo->prepare('SELECT id FROM products WHERE LOWER(brand) = LOWER(:brand) AND LOWER(name) = LOWER(:name)');
    $updateCover = $pdo->prepare('UPDATE products SET image_path = :image_path WHERE id = :id');
    $deleteGallery = $pdo->prepare('DELETE FROM product_gallery_images WHERE product_id = :product_id');
    $insertGalleryImage = $pdo->prepare(
        'INSERT INTO product_gallery_images (product_id, image_path, sort_order) VALUES (:product_id, :image_path, :sort_order)'
    );

    $projectRoot = dirname(__DIR__);

    foreach ($updateFiles as $updateFile) {
        $migrationName = 'photo-updates/' . basename($updateFile);
        $migrationCheck->execute(['name' => $migrationName]);
        if ($migrationCheck->fetchColumn()) {
            continue;
        }

        $updates = json_decode((string) file_get_contents($updateFile), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($updates) || !array_is_list($updates)) {
            throw new RuntimeException(basename($updateFile) . ' non contiene un elenco valido.');
        }

        $updated = 0;
        $notFound = 0;

        $pdo->beginTransaction();
        try {
            foreach ($updates as $update) {
                $images = array_values($update['images'] ?? []);
                if ($images === []) {
                    throw new RuntimeException("Nessuna foto indicata per {$update['name']}");
                }
                foreach ($images as $imagePath) {
                    if (!str_starts_with($imagePath, '/assets/images/') || !is_file($projectRoot . $imagePath)) {
                        throw new RuntimeException("Foto mancante per {$update['name']}: {$imagePath}");
                    }
                }

                $findProduct->execute(['brand' => $update['brand'], 'name' => $update['name']]);
                $productIds = $findProduct->fetchAll(PDO::FETCH_COLUMN);
                if ($productIds === []) {
                    $notFound++;
                    continue;
                }

                foreach ($productIds as $productId) {
                    $updateCover->execute(['image_path' => $images[0], 'id' => $productId]);
                    $deleteGallery->execute(['product_id' => $productId]);
                    foreach (array_slice($images, 1) as $sortOrder => $imagePath) {
                        $insertGalleryImage->execute([
                            'product_id' => $productId,
                            'image_path' => $imagePath,
                            'sort_order' => $sortOrder,
                        ]);
                    }
                    $updated++;
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

        echo basename($updateFile) . ": foto aggiornate per {$updated} prodotti";
        echo $notFound > 0 ? ", {$notFound} non trovati\n" : "\n";
    }
} catch (Throwable $e) {
    error_log('Aggiornamento foto fallito: ' . $e->getMessage());
    fwrite(STDERR, "Errore durante l'aggiornamento delle foto prodotto.\n");
    exit(1);
}
