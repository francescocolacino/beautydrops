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

// Prodotti scelti dall'admin (checkbox nel form prodotto) per la sezione
// "Nuovi prodotti arrivati": i più recenti per primi.
$newArrivals = $pdo->query(
    'SELECT * FROM products WHERE is_new_arrival = TRUE ORDER BY created_at DESC, brand ASC, name ASC'
)->fetchAll();
$activeOfferProductIds = $newArrivals ? get_active_offer_product_ids($pdo) : [];

$pageTitle = 'BeautyDrops · Catalogo prodotti beauty';
require __DIR__ . '/includes/header.php';
?>

<h1 class="visually-hidden">BeautyDrops · Catalogo beauty</h1>

<div class="home-top">

<?php if (!empty(UNAVAILABLE_BRANDS)): ?>
<?php $unavailableBrandsLabel = implode(' e ', UNAVAILABLE_BRANDS); ?>
<section class="brand-notice-section">
  <div class="container">
    <div class="brand-notice" role="status">
      <span class="brand-notice-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01" stroke-linecap="round"/></svg>
      </span>
      <div>
        <strong><?= h($unavailableBrandsLabel) ?> temporaneamente non disponibili</strong>
        <p>Al momento non riceviamo forniture dei trucchi <?= h($unavailableBrandsLabel) ?>: torneranno ordinabili a breve.</p>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($offers)): ?>
<section class="offers-section" id="offerte">
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

</div>

<?php if (!empty($newArrivals)): ?>
<section class="new-arrivals-section">
  <div class="container">
    <div class="section-heading" data-aos="fade-up">
      <span class="eyebrow">Novità in catalogo</span>
      <h2 class="section-title">Nuovi arrivi</h2>
    </div>
    <div class="product-grid new-arrivals-grid">
      <?php foreach ($newArrivals as $i => $product): ?>
        <?php require __DIR__ . '/includes/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
    <div class="section-cta" data-aos="fade-up">
      <a href="<?= url('category.php?slug=cosmetici') ?>" class="btn btn-outline">
        Esplora tutto il catalogo beauty
        <svg class="cta-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

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

<?php require __DIR__ . '/includes/footer.php'; ?>
