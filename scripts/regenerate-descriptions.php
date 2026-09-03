<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/lib/description-generator.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Rigenera la colonna description per TUTTI i prodotti nel database, nel
 * nuovo formato breve (cos'è/a che serve/come si usa + varianti o
 * composizione set). Va eseguito manualmente una tantum (non fa parte della
 * sequenza di avvio del container): sovrascrive anche eventuali descrizioni
 * scritte a mano dall'admin, quindi non è pensato per girare ad ogni deploy.
 *
 * Uso:
 *   php scripts/regenerate-descriptions.php
 */

try {
    $pdo = get_db();
    $rows = $pdo->query('SELECT id, name, variants FROM products')->fetchAll();

    $stmt = $pdo->prepare('UPDATE products SET description = :description WHERE id = :id');

    $updated = 0;
    foreach ($rows as $row) {
        $variants = decode_variants($row['variants']);
        $description = generate_rich_description($row['name'], $variants);
        $stmt->execute(['description' => $description, 'id' => $row['id']]);
        $updated++;
    }

    echo "Descrizioni rigenerate: {$updated} su " . count($rows) . " prodotti.\n";
} catch (Throwable $e) {
    error_log('Rigenerazione descrizioni fallita: ' . $e->getMessage());
    fwrite(STDERR, "Errore durante la rigenerazione delle descrizioni.\n");
    exit(1);
}
