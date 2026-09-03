(function () {
  'use strict';

  var CART_KEY = 'bdCart';
  var baseUrl = window.BD_BASE_URL || '/';

  function readCart() {
    try {
      var raw = localStorage.getItem(CART_KEY);
      var cart = raw ? JSON.parse(raw) : [];
      return Array.isArray(cart) ? cart : [];
    } catch (e) {
      return [];
    }
  }

  function writeCart(cart) {
    try {
      localStorage.setItem(CART_KEY, JSON.stringify(cart));
    } catch (e) { /* storage unavailable, cart just won't persist */ }
    updateBadge();
    document.dispatchEvent(new CustomEvent('bd:cart-changed'));
  }

  function lineKey(productId, variant) {
    return productId + '::' + (variant || '');
  }

  function addToCart(productId, variant, quantity) {
    var cart = readCart();
    var key = lineKey(productId, variant);
    var existing = cart.find(function (item) { return lineKey(item.productId, item.variant) === key; });
    if (existing) {
      existing.quantity += quantity;
    } else {
      cart.push({ productId: productId, variant: variant || null, quantity: quantity });
    }
    writeCart(cart);
  }

  function updateLineQuantity(productId, variant, quantity) {
    var key = lineKey(productId, variant);
    var cart = readCart()
      .map(function (item) {
        if (lineKey(item.productId, item.variant) === key) {
          item.quantity = quantity;
        }
        return item;
      })
      .filter(function (item) { return item.quantity > 0; });
    writeCart(cart);
  }

  function removeLine(productId, variant) {
    var key = lineKey(productId, variant);
    writeCart(readCart().filter(function (item) { return lineKey(item.productId, item.variant) !== key; }));
  }

  function clearCart() { writeCart([]); }

  function totalQuantity() {
    return readCart().reduce(function (sum, item) { return sum + item.quantity; }, 0);
  }

  function updateBadge() {
    var badge = document.getElementById('cartBadge');
    if (!badge) return;
    var qty = totalQuantity();
    badge.textContent = String(qty);
    badge.hidden = qty === 0;
  }

  function discountPercent(qty) {
    if (qty >= 5) return 15;
    if (qty >= 3) return 10;
    if (qty >= 2) return 5;
    return 0;
  }

  function formatPrice(value) {
    return '€ ' + value.toFixed(2).replace('.', ',');
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : str;
    return div.innerHTML;
  }

  function fetchProductData(ids, callback) {
    if (!ids.length) { callback([]); return; }
    fetch(baseUrl + 'cart-data.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ids: ids })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) { callback(data.products || []); })
      .catch(function () { callback([]); });
  }

  function buildEnrichedCart(callback) {
    var cart = readCart();

    // Lo sconto quantità vale sulla somma dei pezzi dello stesso prodotto,
    // anche se distribuiti su più varianti (es. 1 rosso + 1 blu = 2 pezzi).
    var qtyByProduct = {};
    cart.forEach(function (item) {
      qtyByProduct[item.productId] = (qtyByProduct[item.productId] || 0) + item.quantity;
    });

    fetchProductData(cart.map(function (item) { return item.productId; }), function (products) {
      var byId = {};
      products.forEach(function (p) { byId[p.id] = p; });
      var lines = cart.map(function (item) {
        var product = byId[item.productId];
        if (!product) return null;
        var discount = discountPercent(qtyByProduct[item.productId]);
        var lineSubtotal = product.price !== null ? product.price * item.quantity : null;
        var lineTotal = lineSubtotal !== null ? lineSubtotal * (1 - discount / 100) : null;
        return {
          productId: item.productId,
          variant: item.variant,
          quantity: item.quantity,
          product: product,
          discount: discount,
          lineSubtotal: lineSubtotal,
          lineTotal: lineTotal
        };
      }).filter(Boolean);
      callback(lines);
    });
  }

  function renderPopup() {
    var itemsEl = document.getElementById('cartPopupItems');
    var totalEl = document.getElementById('cartPopupTotal');
    if (!itemsEl) return;
    buildEnrichedCart(function (lines) {
      itemsEl.innerHTML = '';
      if (!lines.length) {
        itemsEl.innerHTML = '<p class="cart-empty-state">Il carrello è vuoto.</p>';
        if (totalEl) totalEl.textContent = formatPrice(0);
        return;
      }
      var total = 0;
      lines.forEach(function (line) {
        if (line.lineTotal !== null) total += line.lineTotal;
        var div = document.createElement('div');
        div.className = 'cart-popup-item';
        var imgHtml = line.product.image
          ? '<img src="' + line.product.image + '" alt="">'
          : '<div class="img-placeholder">BD</div>';
        var priceHtml = line.product.price === null
          ? '<span class="price on-request">Su richiesta</span>'
          : '<span class="price">' +
              (line.discount > 0 ? '<span class="original">' + formatPrice(line.lineSubtotal) + '</span>' : '') +
              formatPrice(line.lineTotal) +
            '</span>';
        div.innerHTML = imgHtml +
          '<div class="cart-popup-item-info">' +
            '<span class="name">' + escapeHtml(line.product.name) + '</span>' +
            '<span class="meta">' + (line.variant ? escapeHtml(line.variant) + ' · ' : '') + 'Qt. ' + line.quantity +
              (line.discount > 0 ? ' · <span class="discount">-' + line.discount + '%</span>' : '') +
            '</span>' +
          '</div>' +
          priceHtml;
        itemsEl.appendChild(div);
      });
      if (totalEl) totalEl.textContent = formatPrice(total);
    });
  }

  function renderCartPage() {
    var cartPageList = document.getElementById('cartPageList');
    if (!cartPageList) return;
    var summaryEl = document.getElementById('cartSummary');
    var formSection = document.getElementById('cartOrderForm');

    buildEnrichedCart(function (lines) {
      cartPageList.innerHTML = '';
      if (!lines.length) {
        cartPageList.innerHTML = '<p class="empty-state">Il carrello è vuoto. <a href="' + baseUrl + 'index.php">Torna al catalogo</a>.</p>';
        if (summaryEl) summaryEl.hidden = true;
        if (formSection) formSection.hidden = true;
        return;
      }
      if (summaryEl) summaryEl.hidden = false;
      if (formSection) formSection.hidden = false;

      var subtotal = 0, discountTotal = 0, total = 0, hasOnRequest = false;

      lines.forEach(function (line) {
        var row = document.createElement('div');
        row.className = 'cart-line';
        var imgHtml = line.product.image ? '<img src="' + line.product.image + '" alt="">' : '<div class="img-placeholder">BD</div>';
        var priceHtml;
        if (line.product.price === null) {
          priceHtml = '<span class="on-request">Prezzo su richiesta</span>';
          hasOnRequest = true;
        } else {
          subtotal += line.lineSubtotal;
          total += line.lineTotal;
          discountTotal += (line.lineSubtotal - line.lineTotal);
          priceHtml = (line.discount > 0 ? '<span class="original">' + formatPrice(line.lineSubtotal) + '</span>' : '') +
            '<span class="final">' + formatPrice(line.lineTotal) + '</span>';
        }
        row.innerHTML =
          '<a href="' + line.product.productUrl + '">' + imgHtml + '</a>' +
          '<div class="cart-line-info">' +
            '<span class="brand">' + escapeHtml(line.product.brand) + '</span>' +
            '<p class="name"><a href="' + line.product.productUrl + '">' + escapeHtml(line.product.name) + '</a></p>' +
            (line.variant ? '<span class="variant">Variante: ' + escapeHtml(line.variant) + '</span>' : '') +
            (line.discount > 0 ? '<span class="cart-line-discount">Sconto quantità: -' + line.discount + '%</span>' : '') +
          '</div>' +
          '<div class="cart-line-qty">' +
            '<div class="qty-stepper">' +
              '<button type="button" class="qty-decrease" aria-label="Diminuisci">&minus;</button>' +
              '<input type="number" class="qty-input" min="1" value="' + line.quantity + '" readonly>' +
              '<button type="button" class="qty-increase" aria-label="Aumenta">+</button>' +
            '</div>' +
          '</div>' +
          '<div class="cart-line-price">' + priceHtml + '</div>' +
          '<button type="button" class="cart-line-remove">Rimuovi</button>';

        row.querySelector('.qty-decrease').addEventListener('click', function () {
          updateLineQuantity(line.productId, line.variant, Math.max(1, line.quantity - 1));
        });
        row.querySelector('.qty-increase').addEventListener('click', function () {
          updateLineQuantity(line.productId, line.variant, line.quantity + 1);
        });
        row.querySelector('.cart-line-remove').addEventListener('click', function () {
          removeLine(line.productId, line.variant);
        });

        cartPageList.appendChild(row);
      });

      if (summaryEl) {
        var subtotalEl = summaryEl.querySelector('.js-subtotal');
        var discountEl = summaryEl.querySelector('.js-discount');
        var totalEl = summaryEl.querySelector('.js-total');
        var noteEl = summaryEl.querySelector('.js-note');
        if (subtotalEl) subtotalEl.textContent = formatPrice(subtotal);
        if (discountEl) discountEl.textContent = '− ' + formatPrice(discountTotal);
        if (totalEl) totalEl.textContent = formatPrice(total);
        if (noteEl) noteEl.hidden = !hasOnRequest;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    updateBadge();

    var cartButton = document.getElementById('cartButton');
    var overlay = document.getElementById('cartPopupOverlay');
    var closeBtn = document.getElementById('cartPopupClose');

    function openPopup() {
      if (!overlay) return;
      renderPopup();
      overlay.hidden = false;
      requestAnimationFrame(function () { overlay.classList.add('open'); });
    }
    function closePopup() {
      if (!overlay) return;
      overlay.classList.remove('open');
      window.setTimeout(function () { overlay.hidden = true; }, 200);
    }

    if (cartButton) cartButton.addEventListener('click', openPopup);
    if (closeBtn) closeBtn.addEventListener('click', closePopup);
    if (overlay) overlay.addEventListener('click', function (e) { if (e.target === overlay) closePopup(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePopup(); });
    document.addEventListener('bd:cart-changed', function () {
      if (overlay && !overlay.hidden) renderPopup();
    });

    var addForm = document.getElementById('addToCartForm');
    if (addForm) {
      var productId = parseInt(addForm.getAttribute('data-product-id'), 10);
      var requiresVariant = addForm.getAttribute('data-requires-variant') === '1';
      var selectedVariant = null;
      var variantButtons = addForm.querySelectorAll('.variant-option');
      var hint = addForm.querySelector('.variant-required-hint');
      var qtyInput = addForm.querySelector('.qty-input');
      var addButton = addForm.querySelector('.btn-add-cart');

      variantButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          variantButtons.forEach(function (b) { b.classList.remove('selected'); });
          btn.classList.add('selected');
          selectedVariant = btn.getAttribute('data-variant');
          if (hint) hint.classList.remove('visible');
        });
      });

      addForm.querySelectorAll('.qty-decrease, .qty-increase').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var current = parseInt(qtyInput.value, 10) || 1;
          qtyInput.value = btn.classList.contains('qty-increase') ? current + 1 : Math.max(1, current - 1);
        });
      });

      addForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (requiresVariant && !selectedVariant) {
          if (hint) hint.classList.add('visible');
          return;
        }
        var qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);
        addToCart(productId, selectedVariant, qty);
        if (addButton) {
          var original = addButton.getAttribute('data-default-label') || addButton.textContent.trim();
          addButton.setAttribute('data-default-label', original);
          addButton.classList.add('added');
          addButton.textContent = 'Aggiunto ✓';
          window.setTimeout(function () {
            addButton.classList.remove('added');
            addButton.textContent = original;
          }, 1600);
        }
      });
    }

    if (document.getElementById('cartPageList')) {
      renderCartPage();
      document.addEventListener('bd:cart-changed', renderCartPage);
    }

    var orderForm = document.getElementById('cartOrderForm');
    if (orderForm) {
      orderForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var cart = readCart();
        if (!cart.length) return;
        var submitBtn = orderForm.querySelector('button[type=submit]');
        var errorEl = document.getElementById('orderFormError');
        if (errorEl) errorEl.hidden = true;
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Invio in corso...'; }

        fetch(baseUrl + 'order-submit.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            firstName: document.getElementById('orderFirstName').value,
            lastName: document.getElementById('orderLastName').value,
            email: document.getElementById('orderEmail').value,
            items: cart
          })
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.success) {
              clearCart();
              window.location.href = data.redirect;
            } else {
              if (errorEl) { errorEl.textContent = data.error || "Si è verificato un errore. Riprova."; errorEl.hidden = false; }
              if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Genera ordine'; }
            }
          })
          .catch(function () {
            if (errorEl) { errorEl.textContent = 'Errore di connessione. Riprova.'; errorEl.hidden = false; }
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Genera ordine'; }
          });
      });
    }
  });
})();
