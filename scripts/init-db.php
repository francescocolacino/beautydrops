<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$schemaPath = __DIR__ . '/../schema.sql';

if (!is_file($schemaPath)) {
    fwrite(STDERR, "Errore: file schema.sql non trovato.\n");
    exit(1);
}

$schemaSql = file_get_contents($schemaPath);
if ($schemaSql === false || trim($schemaSql) === '') {
    fwrite(STDERR, "Errore: schema.sql è vuoto o non leggibile.\n");
    exit(1);
}

try {
    $pdo = get_db();
    $pdo->beginTransaction();
    $pdo->exec($schemaSql);
    $pdo->commit();

    echo "Schema PostgreSQL inizializzato correttamente\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Log del dettaglio lato server (nessuna credenziale coinvolta: è un
    // errore di esecuzione SQL, non di connessione) ma messaggio su STDERR generico.
    error_log('Inizializzazione schema fallita: ' . $e->getMessage());
    fwrite(STDERR, "Errore durante l'inizializzazione dello schema del database.\n");
    exit(1);
}
