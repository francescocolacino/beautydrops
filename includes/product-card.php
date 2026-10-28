<?php
/**
 * Card prodotto pubblica, condivisa tra pagine categoria e homepage.
 *
 * Variabili attese: $product (riga di products), $i (indice per il ritardo
 * dell'animazione), $activeOfferProductIds.
 */
$variants = decode_variants($product['variants']);
$cardName = display_product_name($product['name'], $variants);
$cardVariants = selectable_variants($variants);
?>
<a href="<?= url('product.php?id=' . (int)$product['id']) ?>" class="product-card" data-name="<?= h(mb_strtolower($cardName)) ?>" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 60 ?>">
  <div class="product-image">
    <?php if (!empty($product['image_path'])): ?>
      <img src="<?= h(url($product['image_path'])) ?>" alt="<?= h($cardName) ?>" loading="lazy">
    <?php else: ?>
      <div class="img-placeholder">BD</div>
    <?php endif; ?>
  </div>
  <div class="product-body">
    <span class="product-brand"><?= h($product['brand']) ?></span>
    <h3 class="product-name"><?= h($cardName) ?></h3>
    <?php if (!empty($cardVariants)): ?>
      <div class="variant-chips variant-chips-compact">
        <?php foreach (array_slice($cardVariants, 0, 3) as $variant): ?>
          <span class="chip"><?= h($variant) ?></span>
        <?php endforeach; ?>
        <?php if (count($cardVariants) > 3): ?>
          <span class="chip chip-more">+<?= count($cardVariants) - 3 ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?= render_product_badges($product, $activeOfferProductIds) ?>
    <?php if ($product['price'] !== null): ?>
      <p class="product-price"><?= format_price((float)$product['price']) ?></p>
    <?php endif; ?>
  </div>
</a>
