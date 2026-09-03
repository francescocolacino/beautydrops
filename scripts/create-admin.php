<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$email = getenv('ADMIN_EMAIL');
$password = getenv('ADMIN_PASSWORD');

if ($email === false || trim($email) === '' || $password === false || $password === '') {
    fwrite(STDERR, "Errore: impostare le variabili d'ambiente ADMIN_EMAIL e ADMIN_PASSWORD prima di eseguire questo script.\n");
    exit(1);
}

$email = trim($email);

try {
    $pdo = get_db();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO admins (email, password_hash) VALUES (:email, :password_hash)
         ON CONFLICT (email) DO UPDATE SET password_hash = EXCLUDED.password_hash'
    );
    $stmt->execute([
        'email' => $email,
        'password_hash' => $hash,
    ]);

    echo "Amministratore creato/aggiornato con successo.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Errore durante la creazione dell'amministratore. Verifica la configurazione del database.\n");
    exit(1);
}
