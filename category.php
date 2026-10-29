<?php
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
if (!array_key_exists($slug, CATEGORIES)) {
    http_response_code(404);
    $pageTitle = 'Categoria non trovata · ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<section class="container empty-state"><p>Categoria non trovata. <a href="' . h(url('index.php')) . '">Torna alla home</a>.</p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = get_db();

$stmt = $pdo->prepare('SELECT * FROM products WHERE category = :category ORDER BY brand ASC, name ASC');
$stmt->execute(['category' => $slug]);
$products = $stmt->fetchAll();

$activeOfferProductIds = get_active_offer_product_ids($pdo);

$byBrand = [];
foreach ($products as $product) {
    $byBrand[$product['brand']][] = $product;
}
foreach ($byBrand as $brand => $items) {
    $byBrand[$brand] = order_products_for_brand($brand, $items);
}
$byBrand = order_brands_by_priority($byBrand);

// Categorie senza ancora prodotti reali: sostituiscono l'intera griglia con
// un avviso "in allestimento".
$fullComingSoonSections = [
    'abbigliamento' => [
        'description' => 'Stiamo preparando la selezione di abbigliamento, borse e scarpe: trattiamo brand come Ralph Lauren, Dior, Gucci, Balenciaga, The North Face, Burberry, Tommy Hilfiger e molti altri.',
        'brands' => ['Ralph Lauren', 'Dior', 'Gucci', 'Balenciaga', 'The North Face', 'Burberry', 'Tommy Hilfiger'],
    ],
];
// Categorie già con prodotti reali ma ancora in espansione: mostrano la
// griglia normale e in più un avviso sotto per gli altri brand in arrivo.
$appendComingSoonSections = [
    'elettronica' => [
        'description' => 'Stiamo ampliando ulteriormente la selezione tech e audio: in arrivo anche altri brand audio e accessori.',
        'brands' => ['Marshall', 'Shure'],
    ],
];
$isComingSoon = isset($fullComingSoonSections[$slug]);
$comingSoon = $fullComingSoonSections[$slug] ?? null;
$appendComingSoon = $appendComingSoonSections[$slug] ?? null;

$pageTitle = category_label($slug) . ' · ' . SITE_NAME;
$activeSlug = $slug;
require __DIR__ . '/includes/header.php';
?>

<section class="category-hero cat-<?= h($slug) ?>" data-aos="fade-up">
  <div class="container">
    <a href="<?= url('index.php') ?>" class="breadcrumb">&larr; Tutte le categorie</a>
    <h1><?= h(category_label($slug)) ?></h1>
    <?php if (!$isComingSoon): ?>
      <p class="category-count" id="categoryCount" aria-live="polite"><?= count($products) ?> <?= count($products) === 1 ? 'prodotto disponibile' : 'prodotti disponibili' ?></p>
      <div class="search-box">
        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
        <input type="search" id="productSearch" placeholder="Cerca brand, prodotto o colore..." autocomplete="off" enterkeyhint="search" aria-label="Cerca nel catalogo">
      </div>
      <?php if (!empty($byBrand)): ?>
        <nav class="brand-index" aria-label="Vai al brand">
          <?php foreach (array_keys($byBrand) as $brand): ?>
            <a href="#<?= h(brand_anchor($brand)) ?>"><?= h($brand) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php if ($isComingSoon): ?>
  <section class="products-section container">
    <div class="coming-soon-card" data-aos="fade-up">
      <span class="eyebrow">In arrivo</span>
      <h2 class="section-title">Sezione in allestimento</h2>
      <p><?= h($comingSoon['description']) ?></p>
      <div class="coming-soon-brands">
        <?php foreach ($comingSoon['brands'] as $brandName): ?>
          <span class="chip"><?= h($brandName) ?></span>
        <?php endforeach; ?>
        <span class="chip chip-more">e molti altri</span>
      </div>
      <p class="coming-soon-contact">Contattaci per altre info.</p>
    </div>
  </section>
<?php else: ?>
  <section class="products-section container">
    <?php if (empty($byBrand)): ?>
      <p class="empty-state">Nessun prodotto disponibile in questa categoria al momento.</p>
    <?php else: ?>
      <div class="search-empty" id="searchEmpty" hidden>
        <p><strong>Nessun prodotto trovato.</strong></p>
        <p>Prova con il nome del brand (es. Rhode, NARS) o con una parola del prodotto (es. blush, mascara).</p>
      </div>
    <?php endif; ?>

    <?php foreach ($byBrand as $brand => $items): ?>
      <div class="brand-group" id="<?= h(brand_anchor($brand)) ?>" data-brand="<?= h(mb_strtolower($brand)) ?>">
        <h2 class="brand-title" data-aos="fade-up"><?= h($brand) ?></h2>
        <div class="product-grid">
          <?php foreach ($items as $i => $product): ?>
            <?php require __DIR__ . '/includes/product-card.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <?php if ($appendComingSoon !== null): ?>
    <section class="products-section container">
      <div class="coming-soon-card coming-soon-card-compact" data-aos="fade-up">
        <span class="eyebrow">In arrivo</span>
        <h2 class="section-title-small">Sezione in allestimento</h2>
        <p><?= h($appendComingSoon['description']) ?></p>
        <div class="coming-soon-brands">
          <?php foreach ($appendComingSoon['brands'] as $brandName): ?>
            <span class="chip"><?= h($brandName) ?></span>
          <?php endforeach; ?>
          <span class="chip chip-more">e molti altri</span>
        </div>
        <p class="coming-soon-contact">Contattaci per altre info.</p>
      </div>
    </section>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
