<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = get_db();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

    if ($count > 0) {
        echo "Catalogo già presente: importazione saltata\n";
        exit(0);
    }

    // La tabella è vuota: esegue l'import esistente (che a sua volta cancella
    // solo le righe con image_path del catalogo, quindi qui è un no-op sicuro
    // dato che non c'è ancora nulla da cancellare).
    require __DIR__ . '/import-catalog.php';
} catch (Throwable $e) {
    error_log('Seed del catalogo fallito: ' . $e->getMessage());
    fwrite(STDERR, "Errore durante l'importazione del catalogo iniziale.\n");
    exit(1);
}
