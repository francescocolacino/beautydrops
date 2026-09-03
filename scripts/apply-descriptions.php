<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$jsonPath = $argv[1] ?? null;
if ($jsonPath === null || !is_file($jsonPath)) {
    fwrite(STDERR, "Uso: php apply-descriptions.php <path-al-json>\n");
    exit(1);
}

$rows = json_decode((string) file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($rows)) {
    throw new RuntimeException('Il file non contiene un array JSON valido.');
}

$pdo = get_db();
$stmt = $pdo->prepare('UPDATE products SET description = :description WHERE id = :id');

$updated = 0;
$missing = 0;
foreach ($rows as $row) {
    if (!isset($row['id'], $row['description'])) {
        throw new RuntimeException('Riga malformata: manca id o description.');
    }
    $affected = $stmt->execute([
        'id' => (int) $row['id'],
        'description' => trim((string) $row['description']),
    ]);
    if ($stmt->rowCount() > 0) {
        $updated++;
    } else {
        $missing++;
        echo "Nessuna riga aggiornata per id {$row['id']} (probabilmente eliminato nel frattempo)\n";
    }
}

echo "Aggiornati: {$updated}\n";
echo "Non trovati: {$missing}\n";
