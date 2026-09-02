<?php
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();

$offers = $pdo->query(
    "SELECT * FROM offers WHERE active = 1 ORDER BY created_at DESC"
)->fetchAll();

foreach ($offers as &$offer) {
    $offer['products'] = get_offer_products($pdo, (int)$offer['id']);
    $offer['savings'] = calculate_offer_savings((float)$offer['offer_price'], $offer['products']);
}
unset($offer);

$pageTitle = 'BeautyDrops · Catalogo prodotti beauty';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container hero-inner" data-aos="fade-up">
    <span class="brand-mark hero-mark">BD</span>
    <h1>BeautyDrops</h1>
    <p class="tagline">Cosmetici, elettronica e moda selezionati per te — sfoglia il catalogo e scopri le offerte del momento.</p>
  </div>
</section>

<section class="categories-section">
  <div class="container">
    <div class="category-cards">
      <?php $i = 0; foreach (CATEGORIES as $slug => $label): $i++; ?>
        <a href="/category.php?slug=<?= h($slug) ?>" class="category-card cat-<?= h($slug) ?>" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
          <span class="category-card-label"><?= h($label) ?></span>
          <span class="category-card-cta">Scopri i prodotti &rarr;</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (!empty($offers)): ?>
<section class="offers-section">
  <div class="container">
    <h2 class="section-title" data-aos="fade-up">✨ Super Offerte</h2>
    <div class="offer-cards">
      <?php foreach ($offers as $i => $offer): ?>
        <div class="offer-card" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
          <div class="offer-thumbs">
            <?php foreach (array_slice($offer['products'], 0, 4) as $p): ?>
              <div class="offer-thumb">
                <?php if (!empty($p['image_path'])): ?>
                  <img src="<?= h($p['image_path']) ?>" alt="<?= h($p['name']) ?>" loading="lazy">
                <?php else: ?>
                  <div class="img-placeholder">BD</div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="offer-body">
            <h3><?= h($offer['name']) ?></h3>
            <p class="offer-products-list">
              <?= h(implode(' · ', array_map(fn($p) => $p['name'], $offer['products']))) ?>
            </p>
            <div class="offer-price-row">
              <?php if ($offer['savings']['sum'] !== null): ?>
                <span class="offer-price-original"><?= format_price($offer['savings']['sum']) ?></span>
              <?php endif; ?>
              <span class="offer-price-final"><?= format_price((float)$offer['offer_price']) ?></span>
              <?php if ($offer['savings']['discount_percent'] !== null && $offer['savings']['discount_percent'] > 0): ?>
                <span class="offer-discount-pill">-<?= $offer['savings']['discount_percent'] ?>%</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
