<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/lib/product-classification.php';

try {
    $pdo = get_db();

    // Solo i prodotti senza tipo: non sovrascrive mai una scelta manuale
    // già fatta dall'admin nel form prodotto.
    $rows = $pdo->query('SELECT id, name FROM products WHERE product_type IS NULL')->fetchAll();

    $stmt = $pdo->prepare('UPDATE products SET product_type = :product_type WHERE id = :id');
    $classified = 0;
    foreach ($rows as $row) {
        $type = classify_product_type($row['name']);
        if ($type === null) {
            continue;
        }
        $stmt->execute(['product_type' => $type, 'id' => $row['id']]);
        $classified++;
    }

    echo "Tipi prodotto classificati: {$classified} su " . count($rows) . " senza tipo assegnato.\n";
} catch (Throwable $e) {
    error_log('Classificazione tipo prodotto fallita: ' . $e->getMessage());
    fwrite(STDERR, "Errore durante la classificazione dei tipi prodotto.\n");
    exit(1);
}
