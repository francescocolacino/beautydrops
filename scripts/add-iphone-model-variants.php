<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

/**
 * Aggiunge il secondo gruppo di varianti "Modello iPhone" (11 -> 17 Pro Max)
 * a "Phone Case Set" e "Magnetic Phone Case with Gloss" (brand Rhode),
 * preservando i colori/varianti già presenti come primo gruppo ("Colore").
 *
 * Script una tantum, idempotente: un prodotto già a varianti raggruppate
 * viene saltato invece di essere sovrascritto. Non fa parte della sequenza
 * di avvio del container, va eseguito manualmente:
 *
 *   DATABASE_URL="postgresql://..." php scripts/add-iphone-model-variants.php
 */

const TARGET_BRAND = 'Rhode';
const TARGET_NAMES = ['Phone Case Set', 'Magnetic Phone Case with Gloss'];
const GROUP2_LABEL = 'Modello iPhone';
const IPHONE_MODELS = [
    'iPhone 11', 'iPhone 11 Pro', 'iPhone 11 Pro Max',
    'iPhone 12 mini', 'iPhone 12', 'iPhone 12 Pro', 'iPhone 12 Pro Max',
    'iPhone 13 mini', 'iPhone 13', 'iPhone 13 Pro', 'iPhone 13 Pro Max',
    'iPhone 14', 'iPhone 14 Plus', 'iPhone 14 Pro', 'iPhone 14 Pro Max',
    'iPhone 15', 'iPhone 15 Plus', 'iPhone 15 Pro', 'iPhone 15 Pro Max',
    'iPhone 16', 'iPhone 16 Plus', 'iPhone 16 Pro', 'iPhone 16 Pro Max', 'iPhone 16e',
    'iPhone 17', 'iPhone 17 Air', 'iPhone 17 Pro', 'iPhone 17 Pro Max',
];

try {
    $pdo = get_db();

    $placeholders = implode(',', array_fill(0, count(TARGET_NAMES), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, name, variants FROM products WHERE brand = ? AND name IN ($placeholders)"
    );
    $stmt->execute([TARGET_BRAND, ...TARGET_NAMES]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        fwrite(STDERR, "Nessun prodotto trovato per brand '" . TARGET_BRAND . "' con i nomi indicati. Controlla che i nomi in TARGET_NAMES corrispondano esattamente a quelli nel database.\n");
        exit(1);
    }

    $updateStmt = $pdo->prepare('UPDATE products SET variants = :variants WHERE id = :id');
    $found = [];

    foreach ($rows as $row) {
        $found[] = $row['name'];
        $existing = decode_variants($row['variants']);

        if (is_variant_groups($existing)) {
            echo "Saltato \"{$row['name']}\" (id {$row['id']}): ha già varianti raggruppate, nessuna modifica.\n";
            continue;
        }

        if (empty($existing)) {
            echo "Attenzione: \"{$row['name']}\" (id {$row['id']}) non ha colori/varianti esistenti da preservare come primo gruppo. Saltato: aggiungi prima i colori dal pannello admin, poi rilancia questo script.\n";
            continue;
        }

        $grouped = [
            'Colore' => array_values($existing),
            GROUP2_LABEL => IPHONE_MODELS,
        ];

        $updateStmt->execute([
            'variants' => json_encode($grouped, JSON_UNESCAPED_UNICODE),
            'id' => $row['id'],
        ]);

        echo "Aggiornato \"{$row['name']}\" (id {$row['id']}): colori preservati (" . implode(', ', $existing) . ") + " . count(IPHONE_MODELS) . " modelli iPhone.\n";
    }

    $missing = array_diff(TARGET_NAMES, $found);
    foreach ($missing as $name) {
        echo "Attenzione: nessun prodotto \"$name\" (brand $TARGET_BRAND) trovato nel database.\n";
    }
} catch (Throwable $e) {
    error_log('add-iphone-model-variants fallito: ' . $e->getMessage());
    fwrite(STDERR, "Errore durante l'esecuzione. Verifica la configurazione del database.\n");
    exit(1);
}
