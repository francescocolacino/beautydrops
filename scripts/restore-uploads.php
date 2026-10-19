<?php
declare(strict_types=1);

// Ripristina ad ogni avvio del container le foto caricate dall'admin.
//
// Il filesystem del container è effimero: ad ogni deploy la cartella
// assets/images/products/ torna vuota, mentre il database continua a puntare
// a "assets/images/products/prod_xxx.jpg". Le copie salvate (e versionate in
// Git) in assets/images/uploads-backup/ vengono quindi ricopiate qui con lo
// stesso nome, così quei percorsi tornano validi. I file già presenti non
// vengono mai sovrascritti.

$backupDir = dirname(__DIR__) . '/assets/images/uploads-backup/';
$uploadDir = dirname(__DIR__) . '/assets/images/products/';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];

if (!is_dir($backupDir)) {
    echo "Nessun backup foto da ripristinare\n";
    exit(0);
}
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    fwrite(STDERR, "Errore: impossibile creare la cartella delle foto caricate.\n");
    exit(1);
}

$restored = 0;
$failed = 0;
foreach (scandir($backupDir) ?: [] as $filename) {
    $source = $backupDir . $filename;
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!is_file($source) || !in_array($extension, $allowedExtensions, true)) {
        continue;
    }
    $destination = $uploadDir . $filename;
    if (is_file($destination)) {
        continue;
    }
    if (copy($source, $destination)) {
        $restored++;
    } else {
        $failed++;
    }
}

echo "Foto caricate ripristinate dal backup: {$restored}\n";
if ($failed > 0) {
    // Non blocca l'avvio del sito: una foto mancante è meglio di un sito giù.
    fwrite(STDERR, "Attenzione: {$failed} foto non ripristinate.\n");
}
