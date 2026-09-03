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

    // Migrazione una tantum: gli articoli che erano nella categoria
    // "Abbigliamento, Borse e Scarpe" devono confluire in "Beauty e Cosmetici".
    // Il marcatore evita di spostare in futuro nuovi prodotti assegnati
    // volontariamente alla categoria abbigliamento dall'amministrazione.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS data_migrations (
            name VARCHAR(191) PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $migrationName = 'move-abbigliamento-to-cosmetici-20260903';
    $migrationCheck = $pdo->prepare('SELECT 1 FROM data_migrations WHERE name = :name');
    $migrationCheck->execute(['name' => $migrationName]);

    if (!$migrationCheck->fetchColumn()) {
        $pdo->beginTransaction();
        try {
            $moveProducts = $pdo->prepare(
                'UPDATE products SET category = :target WHERE category = :source'
            );
            $moveProducts->execute([
                'target' => 'cosmetici',
                'source' => 'abbigliamento',
            ]);
            $movedCount = $moveProducts->rowCount();

            $markMigration = $pdo->prepare(
                'INSERT INTO data_migrations (name) VALUES (:name)'
            );
            $markMigration->execute(['name' => $migrationName]);
            $pdo->commit();

            echo "Prodotti spostati da abbigliamento a cosmetici: {$movedCount}\n";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // Migrazione una tantum richiesta dopo l'aggiornamento dei prezzi online:
    // elimina esclusivamente i prodotti che non hanno alcun prezzo assegnato.
    // Il controllo avviene direttamente sul database di produzione, quindi i
    // prezzi inseriti dall'admin online vengono rispettati.
    $deleteMigrationName = 'delete-products-without-price-20260903';
    $migrationCheck->execute(['name' => $deleteMigrationName]);

    if (!$migrationCheck->fetchColumn()) {
        $pdo->beginTransaction();
        try {
            $deleteProducts = $pdo->prepare('DELETE FROM products WHERE price IS NULL');
            $deleteProducts->execute();
            $deletedCount = $deleteProducts->rowCount();

            $markDeleteMigration = $pdo->prepare(
                'INSERT INTO data_migrations (name) VALUES (:name)'
            );
            $markDeleteMigration->execute(['name' => $deleteMigrationName]);
            $pdo->commit();

            echo "Prodotti senza prezzo eliminati: {$deletedCount}\n";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
} catch (Throwable $e) {
    error_log('Seed del catalogo fallito: ' . $e->getMessage());
    fwrite(STDERR, "Errore durante l'importazione del catalogo iniziale.\n");
    exit(1);
}
