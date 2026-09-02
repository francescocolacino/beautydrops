<?php
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
if (!array_key_exists($slug, CATEGORIES)) {
    http_response_code(404);
    $pageTitle = 'Categoria non trovata · ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<section class="container empty-state"><p>Categoria non trovata. <a href="/index.php">Torna alla home</a>.</p></section>';
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
ksort($byBrand, SORT_NATURAL | SORT_FLAG_CASE);

$pageTitle = category_label($slug) . ' · ' . SITE_NAME;
$activeSlug = $slug;
require __DIR__ . '/includes/header.php';
?>

<section class="category-hero" data-aos="fade-up">
  <div class="container">
    <h1><?= h(category_label($slug)) ?></h1>
    <div class="search-box">
      <input type="search" id="productSearch" placeholder="Cerca per brand o nome prodotto...">
    </div>
  </div>
</section>

<section class="products-section container">
  <?php if (empty($byBrand)): ?>
    <p class="empty-state">Nessun prodotto disponibile in questa categoria al momento.</p>
  <?php endif; ?>

  <?php foreach ($byBrand as $brand => $items): ?>
    <div class="brand-group" data-brand="<?= h(mb_strtolower($brand)) ?>">
      <h2 class="brand-title" data-aos="fade-up"><?= h($brand) ?></h2>
      <div class="product-grid">
        <?php foreach ($items as $i => $product): ?>
          <div class="product-card" data-name="<?= h(mb_strtolower($product['name'])) ?>" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 60 ?>">
            <div class="product-image">
              <?php if (!empty($product['image_path'])): ?>
                <img src="<?= h($product['image_path']) ?>" alt="<?= h($product['name']) ?>" loading="lazy">
              <?php else: ?>
                <div class="img-placeholder">BD</div>
              <?php endif; ?>
            </div>
            <div class="product-body">
              <span class="product-brand"><?= h($product['brand']) ?></span>
              <h3 class="product-name"><?= h($product['name']) ?></h3>
              <?php $variants = decode_variants($product['variants']); ?>
              <?php if (!empty($variants)): ?>
                <div class="variant-chips">
                  <?php foreach ($variants as $variant): ?>
                    <span class="chip"><?= h($variant) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <?= render_product_badges($product, $activeOfferProductIds) ?>
              <?php if ($product['price'] !== null): ?>
                <p class="product-price"><?= format_price((float)$product['price']) ?></p>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
