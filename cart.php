<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Il tuo carrello · ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>

<section class="products-section container">
  <h1>Il tuo carrello</h1>
  <p class="category-count">Rivedi gli articoli scelti, imposta le quantità e genera il preventivo in PDF.</p>

  <div class="cart-page-grid">
    <div id="cartPageList">
      <p class="empty-state">Caricamento carrello...</p>
    </div>

    <div>
      <div class="cart-summary-box" id="cartSummary" hidden>
        <div class="cart-summary-row">
          <span>Subtotale</span>
          <span class="js-subtotal">€ 0,00</span>
        </div>
        <div class="cart-summary-row">
          <span>Sconto quantità</span>
          <span class="js-discount">− € 0,00</span>
        </div>
        <div class="cart-summary-row total">
          <span>Totale</span>
          <span class="js-total">€ 0,00</span>
        </div>
        <p class="cart-summary-note js-note" hidden>Alcuni articoli hanno il prezzo su richiesta e non sono inclusi nel totale: verranno confermati direttamente da BeautyDrops.</p>
        <p class="cart-summary-note">Sconto quantità automatico sulla stessa referenza: 2 pezzi −5%, 3-4 pezzi −10%, 5+ pezzi −15%.</p>

        <form id="cartOrderForm" hidden>
          <p class="form-error" id="orderFormError" hidden></p>
          <div class="form-row">
            <label for="orderFirstName">Nome</label>
            <input type="text" id="orderFirstName" name="firstName" required>
          </div>
          <div class="form-row">
            <label for="orderLastName">Cognome</label>
            <input type="text" id="orderLastName" name="lastName" required>
          </div>
          <div class="form-row">
            <label for="orderEmail">Email</label>
            <input type="email" id="orderEmail" name="email" required>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Genera ordine</button>
          <p class="cart-summary-note">Non è un pagamento online: genereremo un PDF con il riepilogo da confermare privatamente con BeautyDrops.</p>
        </form>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
