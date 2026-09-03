<?php

// Costanti di configurazione dell'app (non credenziali: possono restare nel
// codice versionato). In precedenza vivevano in config.php, un file escluso
// da Git che quindi non esisteva più negli ambienti con deploy da Git (es.
// Railway), causando un fatal error al require. La connessione al database
// resta comunque configurata esclusivamente via variabili d'ambiente, vedi
// db_connection_params() più sotto.
define('SITE_NAME', 'BeautyDrops');

define('UPLOAD_DIR', dirname(__DIR__) . '/assets/images/products/');
define('UPLOAD_URL_PATH', 'assets/images/products/');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

// Email a cui il cliente invia il PDF del preventivo generato dal carrello.
define('SHOP_CONTACT_EMAIL', 'admin@beautydrops.it');

define('ORDERS_DIR', dirname(__DIR__) . '/assets/orders/');
define('ORDERS_URL_PATH', 'assets/orders/');

// Percorso base del progetto rispetto alla document root del webserver,
// calcolato automaticamente: permette al sito di funzionare sia installato
// nella root del dominio sia in una sottocartella (es. hosting condiviso).
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/')) : '';
$projectRoot = str_replace('\\', '/', dirname(__DIR__));
$basePath = ($documentRoot !== '' && str_starts_with($projectRoot, $documentRoot))
    ? substr($projectRoot, strlen($documentRoot))
    : '';
define('BASE_URL', $basePath);
unset($documentRoot, $projectRoot, $basePath);

/**
 * Costruisce i parametri di connessione PostgreSQL leggendo prioritariamente
 * DATABASE_URL (formato "postgresql://utente:password@host:porta/database"),
 * con fallback alle variabili separate DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS/DB_SSLMODE.
 *
 * @return array{dsn:string,user:?string,pass:?string}
 */
function db_connection_params(): array
{
    $databaseUrl = getenv('DATABASE_URL');

    if ($databaseUrl !== false && $databaseUrl !== '') {
        $parts = parse_url($databaseUrl);
        if ($parts === false || !isset($parts['host'])) {
            throw new RuntimeException('DATABASE_URL non è valida: impossibile interpretarne il formato.');
        }

        $host = urldecode($parts['host']);
        $port = $parts['port'] ?? 5432;
        $dbName = isset($parts['path']) ? urldecode(ltrim($parts['path'], '/')) : '';
        $user = isset($parts['user']) ? urldecode($parts['user']) : null;
        $pass = isset($parts['pass']) ? urldecode($parts['pass']) : null;

        if ($dbName === '') {
            throw new RuntimeException('DATABASE_URL non è valida: manca il nome del database.');
        }

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $sslMode = $query['sslmode'] ?? (getenv('DB_SSLMODE') ?: 'require');

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbName};sslmode={$sslMode}";

        return ['dsn' => $dsn, 'user' => $user, 'pass' => $pass];
    }

    $host = getenv('DB_HOST');
    $dbName = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');

    if ($host === false || $host === '' || $dbName === false || $dbName === '') {
        throw new RuntimeException(
            'Configurazione database mancante: impostare la variabile d\'ambiente DATABASE_URL, '
            . 'oppure DB_HOST, DB_NAME, DB_USER e DB_PASS.'
        );
    }

    $port = getenv('DB_PORT');
    $port = ($port === false || $port === '') ? '5432' : $port;
    $sslMode = getenv('DB_SSLMODE');
    $sslMode = ($sslMode === false || $sslMode === '') ? 'require' : $sslMode;

    $dsn = "pgsql:host={$host};port={$port};dbname={$dbName};sslmode={$sslMode}";

    return [
        'dsn' => $dsn,
        'user' => ($user === false ? null : $user),
        'pass' => ($pass === false ? null : $pass),
    ];
}

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $params = db_connection_params();

        try {
            $pdo = new PDO($params['dsn'], $params['user'], $params['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            // Logga il dettaglio lato server (senza credenziali) ma mostra un messaggio generico.
            error_log('Connessione al database fallita: ' . $e->getMessage());
            throw new RuntimeException('Impossibile connettersi al database. Verifica la configurazione.');
        }
    }

    return $pdo;
}
