<?php
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product || !array_key_exists($product['category'], CATEGORIES)) {
    http_response_code(404);
    $pageTitle = 'Prodotto non trovato · ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<section class="container empty-state"><p>Prodotto non trovato. <a href="' . h(url('index.php')) . '">Torna alla home</a>.</p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$activeOfferProductIds = get_active_offer_product_ids($pdo);
$variants = decode_variants($product['variants']);
$size = extract_product_size($product['name']);

$stmt = $pdo->prepare(
    'SELECT * FROM products WHERE brand = :brand AND id != :id ORDER BY RAND() LIMIT 4'
);
$stmt->execute(['brand' => $product['brand'], 'id' => $product['id']]);
$related = $stmt->fetchAll();

$pageTitle = $product['name'] . ' · ' . $product['brand'] . ' · ' . SITE_NAME;
$activeSlug = $product['category'];
require __DIR__ . '/includes/header.php';
?>

<section class="product-detail-section">
  <div class="container">
    <nav class="breadcrumb-trail" aria-label="Percorso">
      <a href="<?= url('index.php') ?>">Home</a>
      <span>/</span>
      <a href="<?= url('category.php?slug=' . $product['category']) ?>"><?= h(category_label($product['category'])) ?></a>
      <span>/</span>
      <span class="breadcrumb-current"><?= h($product['name']) ?></span>
    </nav>

    <div class="product-detail" data-aos="fade-up">
      <div class="product-gallery">
        <div class="product-gallery-main">
          <?php if (!empty($product['image_path'])): ?>
            <img src="<?= h(url($product['image_path'])) ?>" alt="<?= h($product['name']) ?>">
          <?php else: ?>
            <div class="img-placeholder">BD</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="product-detail-info">
        <span class="product-brand"><?= h($product['brand']) ?></span>
        <h1><?= h($product['name']) ?></h1>

        <?= render_product_badges($product, $activeOfferProductIds) ?>

        <?php if ($product['price'] !== null): ?>
          <p class="product-detail-price"><?= format_price((float)$product['price']) ?></p>
        <?php endif; ?>

        <?php if ($size !== null): ?>
          <p class="product-detail-meta"><span>Formato</span> <?= h($size) ?></p>
        <?php endif; ?>

        <?php if (!empty($variants)): ?>
          <div class="product-detail-variants">
            <span class="product-detail-label">Colori e varianti disponibili</span>
            <div class="variant-chips">
              <?php foreach ($variants as $variant): ?>
                <span class="chip"><?= h($variant) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($product['description'])): ?>
          <div class="product-detail-description">
            <span class="product-detail-label">Descrizione</span>
            <p><?= nl2br(h($product['description'])) ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($related)): ?>
      <div class="related-products" data-aos="fade-up">
        <h2 class="section-title-small">Altri prodotti <?= h($product['brand']) ?></h2>
        <div class="product-grid">
          <?php foreach ($related as $i => $item): ?>
            <a href="<?= url('product.php?id=' . (int)$item['id']) ?>" class="product-card" data-aos="fade-up" data-aos-delay="<?= $i * 60 ?>">
              <div class="product-image">
                <?php if (!empty($item['image_path'])): ?>
                  <img src="<?= h(url($item['image_path'])) ?>" alt="<?= h($item['name']) ?>" loading="lazy">
                <?php else: ?>
                  <div class="img-placeholder">BD</div>
                <?php endif; ?>
              </div>
              <div class="product-body">
                <span class="product-brand"><?= h($item['brand']) ?></span>
                <h3 class="product-name"><?= h($item['name']) ?></h3>
                <?php if ($item['price'] !== null): ?>
                  <p class="product-price"><?= format_price((float)$item['price']) ?></p>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
