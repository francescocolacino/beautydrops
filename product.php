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
$displayName = display_product_name($product['name'], $variants);
$selectableVariants = selectable_variants($variants);
$size = extract_product_size($product['name']);
$productImages = product_gallery_images($product['image_path'] ?? null);

$stmt = $pdo->prepare(
    'SELECT * FROM products WHERE brand = :brand AND id != :id ORDER BY RANDOM() LIMIT 4'
);
$stmt->execute(['brand' => $product['brand'], 'id' => $product['id']]);
$related = $stmt->fetchAll();

$pageTitle = $displayName . ' · ' . $product['brand'] . ' · ' . SITE_NAME;
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
      <span class="breadcrumb-current"><?= h($displayName) ?></span>
    </nav>

    <a href="<?= url('category.php?slug=' . $product['category']) ?>" class="product-back-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M11 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Indietro
    </a>

    <div class="product-detail" data-aos="fade-up">
      <div class="product-gallery">
        <div class="product-gallery-main">
          <?php if (!empty($productImages)): ?>
            <img src="<?= h(url($productImages[0])) ?>" alt="<?= h($displayName) ?>" data-product-gallery-main fetchpriority="high">
          <?php else: ?>
            <div class="img-placeholder">BD</div>
          <?php endif; ?>
        </div>
        <?php if (count($productImages) > 1): ?>
          <div class="product-gallery-thumbnails" aria-label="Altre fotografie di <?= h($displayName) ?>">
            <?php foreach ($productImages as $index => $imagePath): ?>
              <button
                type="button"
                class="product-gallery-thumbnail<?= $index === 0 ? ' active' : '' ?>"
                data-product-gallery-thumbnail
                data-image="<?= h(url($imagePath)) ?>"
                aria-label="Visualizza foto <?= $index + 1 ?> di <?= count($productImages) ?>"
                aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
              >
                <img src="<?= h(url($imagePath)) ?>" alt="" loading="lazy">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="product-detail-info">
        <span class="product-brand"><?= h($product['brand']) ?></span>
        <h1><?= h($displayName) ?></h1>

        <?= render_product_badges($product, $activeOfferProductIds) ?>

        <?php if ($product['price'] !== null): ?>
          <p class="product-detail-price"><?= format_price((float)$product['price']) ?></p>
        <?php else: ?>
          <p class="product-detail-price product-detail-price-muted">Prezzo su richiesta</p>
        <?php endif; ?>

        <?php if ($size !== null): ?>
          <p class="product-detail-meta"><span>Formato</span> <?= h($size) ?></p>
        <?php endif; ?>

        <?php if (!empty($product['description'])): ?>
          <div class="product-detail-description">
            <span class="product-detail-label">Descrizione</span>
            <p><?= nl2br(h($product['description'])) ?></p>
          </div>
        <?php endif; ?>

        <form id="addToCartForm" data-product-id="<?= (int)$product['id'] ?>" data-requires-variant="<?= !empty($selectableVariants) ? '1' : '0' ?>">
          <?php if (!empty($selectableVariants)): ?>
            <div class="product-detail-variants">
              <span class="product-detail-label">Colori e varianti disponibili</span>
              <div class="variant-selector">
                <?php foreach ($selectableVariants as $variant): ?>
                  <button type="button" class="variant-option" data-variant="<?= h($variant) ?>"><?= h($variant) ?></button>
                <?php endforeach; ?>
              </div>
              <p class="variant-required-hint">Seleziona una variante prima di aggiungere al carrello.</p>
            </div>
          <?php endif; ?>

          <div class="add-to-cart-row">
            <div class="qty-stepper">
              <button type="button" class="qty-decrease" aria-label="Diminuisci quantità">&minus;</button>
              <input type="number" class="qty-input" min="1" value="1" inputmode="numeric">
              <button type="button" class="qty-increase" aria-label="Aumenta quantità">+</button>
            </div>
            <button type="submit" class="btn-add-cart">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="21" r="1.4"/><circle cx="19" cy="21" r="1.4"/><path d="M2.5 3h2.4l2.4 12.2a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 2-1.6L21.5 7H6" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Aggiungi al carrello
            </button>
          </div>
        </form>
      </div>
    </div>

    <?php if (!empty($related)): ?>
      <div class="related-products" data-aos="fade-up">
        <h2 class="section-title-small">Altri prodotti <?= h($product['brand']) ?></h2>
        <div class="product-grid">
          <?php foreach ($related as $i => $item): ?>
            <?php $itemVariants = decode_variants($item['variants']); ?>
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
                <h3 class="product-name"><?= h(display_product_name($item['name'], $itemVariants)) ?></h3>
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
