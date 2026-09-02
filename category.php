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
ksort($byBrand, SORT_NATURAL | SORT_FLAG_CASE);

$pageTitle = category_label($slug) . ' · ' . SITE_NAME;
$activeSlug = $slug;
require __DIR__ . '/includes/header.php';
?>

<section class="category-hero cat-<?= h($slug) ?>" data-aos="fade-up">
  <div class="container">
    <a href="<?= url('index.php') ?>" class="breadcrumb">&larr; Tutte le categorie</a>
    <h1><?= h(category_label($slug)) ?></h1>
    <p class="category-count"><?= count($products) ?> <?= count($products) === 1 ? 'prodotto disponibile' : 'prodotti disponibili' ?></p>
    <div class="search-box">
      <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
      <input type="search" id="productSearch" placeholder="Cerca per brand o nome prodotto...">
    </div>
    <?php if (!empty($byBrand)): ?>
      <nav class="brand-index" aria-label="Vai al brand">
        <?php foreach (array_keys($byBrand) as $brand): ?>
          <a href="#<?= h(brand_anchor($brand)) ?>"><?= h($brand) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  </div>
</section>

<section class="products-section container">
  <?php if (empty($byBrand)): ?>
    <p class="empty-state">Nessun prodotto disponibile in questa categoria al momento.</p>
  <?php endif; ?>

  <?php foreach ($byBrand as $brand => $items): ?>
    <div class="brand-group" id="<?= h(brand_anchor($brand)) ?>" data-brand="<?= h(mb_strtolower($brand)) ?>">
      <h2 class="brand-title" data-aos="fade-up"><?= h($brand) ?></h2>
      <div class="product-grid">
        <?php foreach ($items as $i => $product): ?>
          <div class="product-card" data-product-id="<?= (int)$product['id'] ?>" data-name="<?= h(mb_strtolower($product['name'])) ?>" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 60 ?>" role="button" tabindex="0" aria-haspopup="dialog">
            <div class="product-image">
              <?php if (!empty($product['image_path'])): ?>
                <img src="<?= h(url($product['image_path'])) ?>" alt="<?= h($product['name']) ?>" loading="lazy">
              <?php else: ?>
                <div class="img-placeholder">BD</div>
              <?php endif; ?>
            </div>
            <div class="product-body">
              <span class="product-brand"><?= h($product['brand']) ?></span>
              <h3 class="product-name"><?= h($product['name']) ?></h3>
              <?php $variants = decode_variants($product['variants']); ?>
              <?php if (!empty($variants)): ?>
                <details class="product-variants">
                  <summary><?= count($variants) ?> <?= count($variants) === 1 ? 'variante' : 'varianti' ?></summary>
                  <div class="variant-chips">
                    <?php foreach ($variants as $variant): ?>
                      <span class="chip"><?= h($variant) ?></span>
                    <?php endforeach; ?>
                  </div>
                </details>
              <?php endif; ?>
              <?= render_product_badges($product, $activeOfferProductIds) ?>
              <?php if ($product['price'] !== null): ?>
                <p class="product-price"><?= format_price((float)$product['price']) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <template class="product-detail-template" data-product-id="<?= (int)$product['id'] ?>">
            <div class="modal-product-image">
              <?php if (!empty($product['image_path'])): ?>
                <img src="<?= h(url($product['image_path'])) ?>" alt="<?= h($product['name']) ?>">
              <?php else: ?>
                <div class="img-placeholder">BD</div>
              <?php endif; ?>
            </div>
            <div class="modal-product-body">
              <span class="product-brand"><?= h($product['brand']) ?></span>
              <h2 id="productModalTitle"><?= h($product['name']) ?></h2>
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
              <?php if (!empty($product['description'])): ?>
                <p class="modal-description"><?= nl2br(h($product['description'])) ?></p>
              <?php endif; ?>
            </div>
          </template>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<div class="modal-overlay" id="productModalOverlay" hidden>
  <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
    <button type="button" class="modal-close" id="productModalClose" aria-label="Chiudi">&times;</button>
    <div class="modal-content" id="productModalContent"></div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
