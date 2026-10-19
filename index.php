<?php
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();

$offers = $pdo->query(
    "SELECT * FROM offers WHERE active = TRUE ORDER BY created_at DESC"
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
  <div class="hero-drops" aria-hidden="true">
    <span class="hero-drop" style="--size: 22px; --dur: 9s; --delay: -1s; top: 20%; left: 8%;"></span>
    <span class="hero-drop hero-desktop-only" style="--size: 14px; --dur: 7.5s; --delay: -3s; --drop-color: var(--color-gold-light); top: 62%; left: 14%;"></span>
    <span class="hero-drop" style="--size: 18px; --dur: 8.5s; --delay: -2s; top: 16%; left: 86%;"></span>
    <span class="hero-drop hero-desktop-only" style="--size: 26px; --dur: 10s; --delay: -5s; --drop-color: var(--color-gold-light); top: 58%; left: 90%;"></span>
    <span class="hero-drop" style="--size: 12px; --dur: 7s; --delay: -1.5s; top: 82%; left: 70%;"></span>
    <span class="hero-drop-fall hero-lane-left" style="--size: 12px; --dur: 7s; --delay: 0.5s;"></span>
    <span class="hero-ripple hero-lane-left" style="--dur: 7s; --delay: 0.5s;"></span>
    <span class="hero-drop-fall hero-lane-right" style="--size: 10px; --dur: 8s; --delay: 3.5s; --drop-color: var(--color-gold-light);"></span>
    <span class="hero-ripple hero-lane-right" style="--dur: 8s; --delay: 3.5s;"></span>
    <span class="hero-sparkle" style="--size: 14px; --dur: 3.4s; --delay: 0s; top: 12%; left: 28%;"></span>
    <span class="hero-sparkle" style="--size: 10px; --dur: 2.8s; --delay: 1.1s; top: 34%; left: 6%;"></span>
    <span class="hero-sparkle" style="--size: 16px; --dur: 3.8s; --delay: 0.6s; top: 26%; left: 72%;"></span>
    <span class="hero-sparkle" style="--size: 12px; --dur: 3s; --delay: 1.8s; top: 70%; left: 82%;"></span>
    <span class="hero-sparkle" style="--size: 14px; --dur: 3.6s; --delay: 2.4s; top: 76%; left: 24%;"></span>
    <span class="hero-sparkle" style="--size: 9px; --dur: 2.6s; --delay: 0.3s; top: 44%; left: 94%;"></span>
    <span class="hero-sparkle" style="--size: 10px; --dur: 3.2s; --delay: 1.5s; top: 8%; left: 58%;"></span>
  </div>
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

<section class="quantity-promo">
  <div class="container">
    <div class="quantity-promo-card" data-aos="fade-up">
      <div class="quantity-promo-text">
        <span class="eyebrow">Sconto quantità automatico</span>
        <h2 class="section-title">Acquista di più, risparmia di più</h2>
        <p>Scegli più pezzi della stessa referenza — anche in colori o varianti diversi — e lo sconto si applica da solo nel carrello, senza bisogno di codici.</p>
      </div>
      <div class="quantity-promo-tiers">
        <div class="quantity-promo-tier">
          <span class="tier-qty">2 pezzi</span>
          <span class="tier-percent">-5%</span>
        </div>
        <div class="quantity-promo-tier">
          <span class="tier-qty">3-4 pezzi</span>
          <span class="tier-percent">-10%</span>
        </div>
        <div class="quantity-promo-tier">
          <span class="tier-qty">5+ pezzi</span>
          <span class="tier-percent">-15%</span>
        </div>
      </div>
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
