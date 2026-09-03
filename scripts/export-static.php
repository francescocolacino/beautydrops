<?php
declare(strict_types=1);

/**
 * Esporta il catalogo pubblico (home, categorie, schede prodotto) come sito
 * statico HTML pronto per Netlify (o qualsiasi hosting statico).
 *
 * Richiede che il sito dinamico sia raggiungibile su $baseUrl (es. il server
 * PHP integrato: `php -S localhost:8000` dalla root del progetto, con il
 * database configurato in config.php raggiungibile).
 *
 * Uso: php scripts/export-static.php
 */

require_once __DIR__ . '/../includes/functions.php';

$baseUrl = 'http://localhost:8000';
$projectRoot = dirname(__DIR__);
$outDir = $projectRoot . '/dist';

function fetch_page(string $url): string
{
    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
    $html = @file_get_contents($url, false, $context);
    if ($html === false) {
        throw new RuntimeException("Impossibile scaricare {$url}. Il server dev (php -S) è avviato?");
    }
    return $html;
}

function rewrite_links(string $html): string
{
    $html = preg_replace('#href="/index\.php"#', 'href="/"', $html);
    $html = preg_replace_callback('#href="/category\.php\?slug=([a-z]+)"#', function ($m) {
        return 'href="/categoria/' . $m[1] . '/"';
    }, $html);
    $html = preg_replace_callback('#href="/product\.php\?id=(\d+)"#', function ($m) {
        return 'href="/prodotto/' . $m[1] . '/"';
    }, $html);
    // L'area admin richiede PHP+MySQL: non esiste su un hosting statico, quindi
    // il pulsante "Accesso Admin" viene rimosso dall'export pubblico.
    $html = preg_replace('#<a href="[^"]*admin/login\.php"[^>]*>.*?</a>#s', '', $html);
    return $html;
}

function write_page(string $outDir, string $relativePath, string $html): void
{
    $fullPath = $outDir . '/' . ltrim($relativePath, '/');
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($fullPath, $html);
}

function copy_dir(string $source, string $dest): void
{
    if (!is_dir($source)) {
        return;
    }
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        $target = $dest . '/' . $items->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            copy($item->getPathname(), $target);
        }
    }
}

function remove_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

echo "Pulisco {$outDir}...\n";
remove_dir($outDir);
mkdir($outDir, 0755, true);

echo "Esporto la homepage...\n";
write_page($outDir, 'index.html', rewrite_links(fetch_page($baseUrl . '/index.php')));

$pdo = get_db();

echo "Esporto le pagine categoria...\n";
foreach (array_keys(CATEGORIES) as $slug) {
    $html = fetch_page($baseUrl . '/category.php?slug=' . $slug);
    write_page($outDir, 'categoria/' . $slug . '/index.html', rewrite_links($html));
}

echo "Esporto le schede prodotto...\n";
$ids = $pdo->query('SELECT id FROM products ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$count = 0;
foreach ($ids as $id) {
    $html = fetch_page($baseUrl . '/product.php?id=' . $id);
    write_page($outDir, 'prodotto/' . $id . '/index.html', rewrite_links($html));
    $count++;
}
echo "  {$count} schede prodotto esportate.\n";

echo "Copio gli asset statici...\n";
copy_dir($projectRoot . '/assets/css', $outDir . '/assets/css');
copy_dir($projectRoot . '/assets/js', $outDir . '/assets/js');
copy_dir($projectRoot . '/assets/images', $outDir . '/assets/images');

// Pagina 404 statica per Netlify (usa la 404 dinamica del catalogo come base).
$notFoundHtml = fetch_page($baseUrl . '/category.php?slug=___inesistente___');
write_page($outDir, '404.html', rewrite_links($notFoundHtml));

echo "Fatto. Sito statico pronto in: {$outDir}\n";
echo "Per pubblicarlo su Netlify: trascina la cartella dist/ su app.netlify.com/drop,\n";
echo "oppure collega il repo GitHub impostando 'Publish directory' = dist e 'Build command' = (vuoto).\n";
