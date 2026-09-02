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

$categoryIcons = [
    'cosmetici' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3h6l1 3H8l1-3Z" stroke-linejoin="round"/><path d="M8 6h8l1 4a6 6 0 0 1-4 8.5V21h-2v-2.5A6 6 0 0 1 7 10l1-4Z" stroke-linejoin="round"/></svg>',
    'elettronica' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M10 5h4M11 18.5h2" stroke-linecap="round"/></svg>',
    'abbigliamento' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 8h14l1 13H4L5 8Z" stroke-linejoin="round"/><path d="M9 9V6a3 3 0 0 1 6 0v3" stroke-linecap="round"/></svg>',
];

$pageTitle = 'BeautyDrops · Catalogo prodotti beauty';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="hero-blob hero-blob-1" aria-hidden="true"></div>
  <div class="hero-blob hero-blob-2" aria-hidden="true"></div>
  <div class="container hero-inner" data-aos="fade-up">
    <span class="eyebrow">✨ Il catalogo BeautyDrops</span>
    <img
      src="<?= url('assets/images/beautydrops-logo.png') ?>"
      class="hero-logo"
      width="1942"
      height="809"
      alt="Beauty Drops"
      fetchpriority="high"
    >
    <h1 class="visually-hidden">BeautyDrops</h1>
    <p class="tagline">Beauty, cover e accessori selezionati per te — esplora il catalogo organizzato per brand e scopri tutte le varianti.</p>
    <a href="#categorie" class="scroll-cue" aria-label="Scorri per esplorare">
      <span></span>
    </a>
  </div>
</section>

<section class="categories-section" id="categorie">
  <div class="container">
    <div class="category-cards">
      <?php $i = 0; foreach (CATEGORIES as $slug => $label): $i++; ?>
        <a href="<?= url('category.php?slug=' . $slug) ?>" class="category-card cat-<?= h($slug) ?>" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
          <span class="category-card-icon"><?= $categoryIcons[$slug] ?></span>
          <span class="category-card-label"><?= h($label) ?></span>
          <span class="category-card-cta">Scopri i prodotti <svg class="cta-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (!empty($offers)): ?>
<section class="offers-section">
  <div class="container">
    <div class="section-heading" data-aos="fade-up">
      <span class="eyebrow">Per un tempo limitato</span>
      <h2 class="section-title">Super Offerte</h2>
    </div>
    <div class="offer-cards">
      <?php foreach ($offers as $i => $offer): ?>
        <div class="offer-card" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
          <?php if ($offer['savings']['discount_percent'] !== null && $offer['savings']['discount_percent'] > 0): ?>
            <span class="offer-ribbon">-<?= $offer['savings']['discount_percent'] ?>%</span>
          <?php endif; ?>
          <div class="offer-thumbs offer-thumbs-<?= min(count($offer['products']), 4) ?>">
            <?php foreach (array_slice($offer['products'], 0, 4) as $p): ?>
              <div class="offer-thumb">
                <?php if (!empty($p['image_path'])): ?>
                  <img src="<?= h(url($p['image_path'])) ?>" alt="<?= h($p['name']) ?>" loading="lazy">
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
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
