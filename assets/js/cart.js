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

  function productQuantityInCart(productId) {
    return readCart().reduce(function (sum, item) {
      return item.productId === productId ? sum + item.quantity : sum;
    }, 0);
  }

  function setBadgeCount(qty) {
    var badge = document.getElementById('cartBadge');
    if (!badge) return;
    badge.textContent = String(qty);
    badge.hidden = qty === 0;
  }

  function updateBadge() {
    setBadgeCount(totalQuantity());
  }

  // Deve restare identico a quantity_discount_percent() in includes/functions.php.
  var DISCOUNT_TIERS = [
    { minQty: 2, percent: 5 },
    { minQty: 3, percent: 10 },
    { minQty: 5, percent: 15 }
  ];
  var MAX_TIER_QTY = DISCOUNT_TIERS[DISCOUNT_TIERS.length - 1].minQty;

  function discountPercent(qty) {
    var percent = 0;
    DISCOUNT_TIERS.forEach(function (tier) {
      if (qty >= tier.minQty) percent = tier.percent;
    });
    return percent;
  }

  function nextDiscountTier(qty) {
    for (var i = 0; i < DISCOUNT_TIERS.length; i++) {
      if (qty < DISCOUNT_TIERS[i].minQty) return DISCOUNT_TIERS[i];
    }
    return null;
  }

  function piecesLabel(qty) {
    return qty === 1 ? '1 pezzo' : qty + ' pezzi';
  }

  function tierMessageHtml(qty) {
    var current = discountPercent(qty);
    var next = nextDiscountTier(qty);
    if (!next) return 'Sconto massimo <strong>-' + current + '%</strong> raggiunto ✨';
    var prefix = current > 0 ? 'Sconto <strong>-' + current + '%</strong> attivo · aggiungi' : 'Aggiungi';
    return prefix + ' ancora ' + piecesLabel(next.minQty - qty) + ' per il <strong>-' + next.percent + '%</strong>';
  }

  function prefersReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  // ---- Effetti di aggiunta al carrello ----

  function isOnScreen(rect) {
    return rect.width > 0 && rect.bottom > 0 && rect.top < window.innerHeight;
  }

  // La foto vola dal prodotto all'icona del carrello: lo spostamento
  // orizzontale (sul contenitore) e quello verticale (sul contenuto) usano
  // curve diverse, così la traiettoria è un arco invece di una linea retta.
  // Se la foto è già uscita dallo schermo (succede quasi sempre su telefono,
  // dove il pulsante sta sotto la galleria) il volo parte dal pulsante.
  function flyToCart(sourceImg, fallbackEl, onLand) {
    var cartButton = document.getElementById('cartButton');
    if (!sourceImg || !cartButton || !sourceImg.animate || prefersReducedMotion()) { onLand(); return; }
    var from = sourceImg.getBoundingClientRect();
    var to = cartButton.getBoundingClientRect();
    if (!isOnScreen(from) && fallbackEl) {
      var button = fallbackEl.getBoundingClientRect();
      var top = button.top + button.height / 2 - 40;
      from = { left: button.left + button.width / 2 - 40, top: top, bottom: top + 80, width: 80, height: 80 };
    }
    if (!isOnScreen(from)) { onLand(); return; }

    var size = Math.min(from.width, from.height, 220);
    var startX = from.left + from.width / 2 - size / 2;
    var startY = from.top + from.height / 2 - size / 2;
    var dx = to.left + to.width / 2 - (startX + size / 2);
    var dy = to.top + to.height / 2 - (startY + size / 2);

    var flyer = document.createElement('div');
    flyer.className = 'fly-to-cart';
    flyer.style.left = startX + 'px';
    flyer.style.top = startY + 'px';
    flyer.style.width = size + 'px';
    flyer.style.height = size + 'px';
    var inner = document.createElement('div');
    inner.className = 'fly-to-cart-inner';
    var img = document.createElement('img');
    img.src = sourceImg.currentSrc || sourceImg.src;
    img.alt = '';
    inner.appendChild(img);
    flyer.appendChild(inner);
    document.body.appendChild(flyer);

    var duration = 750;
    var landed = false;
    function land() {
      if (landed) return;
      landed = true;
      flyer.remove();
      onLand();
    }
    flyer.animate(
      [{ transform: 'translateX(0)' }, { transform: 'translateX(' + dx + 'px)' }],
      { duration: duration, easing: 'cubic-bezier(0.55, 0, 0.9, 0.5)', fill: 'forwards' }
    );
    inner.animate(
      [
        { transform: 'translateY(0) scale(1) rotate(0deg)', opacity: 1 },
        { transform: 'translateY(' + dy + 'px) scale(0.12) rotate(18deg)', opacity: 0.7 }
      ],
      { duration: duration, easing: 'cubic-bezier(0.15, 0.7, 0.35, 1)', fill: 'forwards' }
    ).onfinish = land;
    window.setTimeout(land, duration + 300);
  }

  function bumpCart() {
    updateBadge();
    [document.getElementById('cartBadge'), document.getElementById('cartButton')].forEach(function (el) {
      if (!el) return;
      el.classList.remove('bump');
      void el.offsetWidth; // riavvia l'animazione anche con aggiunte ravvicinate
      el.classList.add('bump');
    });
  }

  var CONFETTI_COLORS = ['#c8a862', '#e3c98a', '#a9873f', '#f6d9df', '#d0587a', '#fff3d6'];

  function burstConfetti(x, y) {
    if (!document.body.animate || prefersReducedMotion()) return;
    for (var i = 0; i < 28; i++) {
      var piece = document.createElement('span');
      var size = 5 + Math.random() * 5;
      var isStrip = Math.random() < 0.5;
      piece.className = 'confetti-piece';
      piece.style.width = (isStrip ? size * 0.5 : size) + 'px';
      piece.style.height = (isStrip ? size * 1.6 : size) + 'px';
      piece.style.background = CONFETTI_COLORS[i % CONFETTI_COLORS.length];
      piece.style.borderRadius = isStrip ? '2px' : '50%';
      document.body.appendChild(piece);

      // Esplodono a ventaglio verso l'alto, poi ricadono girando.
      var angle = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * 1.3;
      var distance = 50 + Math.random() * 90;
      var midX = x + Math.cos(angle) * distance;
      var midY = y + Math.sin(angle) * distance;
      var spin = (Math.random() - 0.5) * 720;
      piece.animate(
        [
          { transform: 'translate(' + x + 'px, ' + y + 'px) rotate(0deg)', opacity: 1 },
          { transform: 'translate(' + midX + 'px, ' + midY + 'px) rotate(' + spin / 2 + 'deg)', opacity: 1, offset: 0.45 },
          { transform: 'translate(' + (midX + (Math.random() - 0.5) * 30) + 'px, ' + (midY + 60 + Math.random() * 80) + 'px) rotate(' + spin + 'deg)', opacity: 0 }
        ],
        { duration: 1000 + Math.random() * 500, easing: 'cubic-bezier(0.2, 0.7, 0.4, 1)' }
      ).onfinish = (function (p) { return function () { p.remove(); }; })(piece);
    }
  }

  // ---- Mini-carrello dopo l'aggiunta ----

  var TOAST_DURATION = 5000;
  var toastEl = null;
  var toastTimer = null;
  var toastRemaining = 0;
  var toastStartedAt = 0;
  var toastSeq = 0;

  function startToastTimer(ms) {
    window.clearTimeout(toastTimer);
    toastRemaining = ms;
    toastStartedAt = Date.now();
    toastTimer = window.setTimeout(hideToast, ms);
  }

  function pauseToast() {
    if (!toastEl || !toastEl.classList.contains('visible') || toastEl.classList.contains('paused')) return;
    window.clearTimeout(toastTimer);
    toastRemaining -= Date.now() - toastStartedAt;
    toastEl.classList.add('paused');
  }

  function resumeToast() {
    if (!toastEl || !toastEl.classList.contains('paused')) return;
    toastEl.classList.remove('paused');
    startToastTimer(Math.max(toastRemaining, 1200));
  }

  function hideToast() {
    window.clearTimeout(toastTimer);
    if (toastEl) toastEl.classList.remove('visible', 'paused');
  }

  function ensureToast() {
    if (toastEl) return toastEl;
    toastEl = document.createElement('div');
    toastEl.className = 'cart-toast';
    toastEl.setAttribute('role', 'status');
    toastEl.setAttribute('aria-live', 'polite');
    toastEl.style.setProperty('--toast-duration', TOAST_DURATION + 'ms');
    toastEl.addEventListener('mouseenter', pauseToast);
    toastEl.addEventListener('mouseleave', resumeToast);
    toastEl.addEventListener('focusin', pauseToast);
    toastEl.addEventListener('focusout', resumeToast);
    toastEl.addEventListener('click', function (e) {
      if (e.target.closest('.cart-toast-close, .cart-toast-continue')) hideToast();
    });
    document.body.appendChild(toastEl);
    return toastEl;
  }

  function showAddedToast(info) {
    var el = ensureToast();
    var seq = ++toastSeq;
    var imgHtml = info.image ? '<img src="' + info.image + '" alt="">' : '<div class="img-placeholder">BD</div>';
    el.innerHTML =
      '<div class="cart-toast-head">' +
        '<span class="cart-toast-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>' +
        'Aggiunto al carrello' +
        '<button type="button" class="cart-toast-close" aria-label="Chiudi">&times;</button>' +
      '</div>' +
      '<div class="cart-toast-item">' + imgHtml +
        '<div class="cart-toast-info">' +
          '<span class="cart-toast-name">' + escapeHtml(info.name) + '</span>' +
          '<span class="cart-toast-meta">' + (info.variant ? escapeHtml(info.variant) + ' · ' : '') + 'Qt. ' + info.quantity + '</span>' +
        '</div>' +
      '</div>' +
      (info.hasPrice ? '<div class="cart-toast-tier">' + tierMessageHtml(info.productQuantity) + '</div>' : '') +
      '<div class="cart-toast-summary">' +
        '<span>Nel carrello: <strong>' + piecesLabel(totalQuantity()) + '</strong></span>' +
        '<span class="js-toast-total"></span>' +
      '</div>' +
      '<div class="cart-toast-actions">' +
        '<button type="button" class="cart-toast-continue">Continua</button>' +
        '<a href="' + baseUrl + 'cart.php" class="btn btn-primary">Vai al carrello</a>' +
      '</div>' +
      '<span class="cart-toast-timer"></span>';

    el.classList.remove('visible', 'paused');
    void el.offsetWidth; // fa ripartire l'animazione d'ingresso se era già aperto
    el.classList.add('visible');
    startToastTimer(TOAST_DURATION);

    buildEnrichedCart(function (lines) {
      var totalEl = el.querySelector('.js-toast-total');
      if (seq !== toastSeq || !totalEl) return;
      var total = lines.reduce(function (sum, line) { return line.lineTotal !== null ? sum + line.lineTotal : sum; }, 0);
      if (total > 0) totalEl.innerHTML = 'Totale <strong>' + formatPrice(total) + '</strong>';
    });
  }

  // ---- Barra di avanzamento dello sconto quantità (pagina carrello) ----

  // Stato del render precedente per prodotto: serve ad animare la barra dal
  // valore di prima e a lanciare i coriandoli solo quando si sale di soglia.
  var lastCartTierState = {};

  function tierProgressHtml(productId, qty) {
    var fill = Math.min(qty, MAX_TIER_QTY) / MAX_TIER_QTY * 100;
    var previous = lastCartTierState[productId];
    var startFill = previous ? previous.fill : 0;
    var marks = DISCOUNT_TIERS.map(function (tier) {
      var left = tier.minQty / MAX_TIER_QTY * 100;
      return '<i class="tier-progress-mark' + (startFill >= left ? ' reached' : '') + '" data-min="' + tier.minQty + '" style="left:' + left + '%">' +
        '<span>-' + tier.percent + '%</span></i>';
    }).join('');
    return '<div class="tier-progress" data-product-id="' + productId + '" data-fill="' + fill + '">' +
      '<div class="tier-progress-track"><span class="tier-progress-fill" style="width:' + startFill + '%"></span>' + marks + '</div>' +
      '<span class="tier-progress-label">' + tierMessageHtml(qty) + '</span>' +
    '</div>';
  }

  function animateTierProgress(container, qtyByProduct) {
    var newState = {};
    container.querySelectorAll('.tier-progress').forEach(function (bar) {
      var productId = bar.getAttribute('data-product-id');
      var qty = qtyByProduct[productId];
      var fill = parseFloat(bar.getAttribute('data-fill'));
      var discount = discountPercent(qty);
      var previous = lastCartTierState[productId];
      var reachedNewTier = !!previous && discount > previous.discount;
      newState[productId] = { fill: fill, discount: discount };

      requestAnimationFrame(function () {
        requestAnimationFrame(function () { bar.querySelector('.tier-progress-fill').style.width = fill + '%'; });
      });
      window.setTimeout(function () {
        // Con clic ravvicinati la barra può essere già stata ridisegnata.
        var current = bar.isConnected ? bar : container.querySelector('.tier-progress[data-product-id="' + productId + '"]');
        if (!current) return;
        current.querySelectorAll('.tier-progress-mark').forEach(function (mark) {
          mark.classList.toggle('reached', qtyByProduct[productId] >= parseInt(mark.getAttribute('data-min'), 10));
        });
        if (reachedNewTier) {
          var rect = current.querySelector('.tier-progress-fill').getBoundingClientRect();
          burstConfetti(rect.right, rect.top + rect.height / 2);
        }
      }, 550);
    });
    lastCartTierState = newState;
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
        var unitPrice = product.price;
        if (item.variant && product.variantPrices && Object.prototype.hasOwnProperty.call(product.variantPrices, item.variant)) {
          unitPrice = product.variantPrices[item.variant];
        }
        var lineSubtotal = unitPrice !== null ? unitPrice * item.quantity : null;
        var lineTotal = lineSubtotal !== null ? lineSubtotal * (1 - discount / 100) : null;
        return {
          productId: item.productId,
          variant: item.variant,
          quantity: item.quantity,
          product: product,
          unitPrice: unitPrice,
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
    if (readCart().length && !itemsEl.querySelector('.cart-popup-item')) {
      var skeletonRow = '<div class="skeleton-row"><span class="skeleton skeleton-thumb"></span>' +
        '<span class="skeleton-lines"><span class="skeleton skeleton-text"></span><span class="skeleton skeleton-text short"></span></span></div>';
      itemsEl.innerHTML = skeletonRow + skeletonRow;
    }
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
        var priceHtml = line.unitPrice === null
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

      // La barra sconto va una sola volta per prodotto, anche se è nel
      // carrello in più varianti (lo sconto conta i pezzi totali).
      var qtyByProduct = {};
      lines.forEach(function (line) {
        qtyByProduct[line.productId] = (qtyByProduct[line.productId] || 0) + line.quantity;
      });
      var progressShown = {};

      lines.forEach(function (line) {
        var progressHtml = '';
        if (line.unitPrice !== null && !progressShown[line.productId]) {
          progressShown[line.productId] = true;
          progressHtml = tierProgressHtml(line.productId, qtyByProduct[line.productId]);
        }
        var row = document.createElement('div');
        row.className = 'cart-line';
        var imgHtml = line.product.image ? '<img src="' + line.product.image + '" alt="">' : '<div class="img-placeholder">BD</div>';
        var priceHtml;
        if (line.unitPrice === null) {
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
          '<a href="' + line.product.productUrl + '" class="cart-line-thumb">' + imgHtml + '</a>' +
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
          '<button type="button" class="cart-line-remove">Rimuovi</button>' +
          progressHtml;

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

      animateTierProgress(cartPageList, qtyByProduct);

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
      // Deve restare identico a VARIANT_GROUP_SEPARATOR in includes/functions.php.
      var VARIANT_GROUP_SEPARATOR = ' · ';
      var productId = parseInt(addForm.getAttribute('data-product-id'), 10);
      var requiresVariant = addForm.getAttribute('data-requires-variant') === '1';
      var hint = addForm.querySelector('.variant-required-hint');
      var qtyInput = addForm.querySelector('.qty-input');
      var addButton = addForm.querySelector('.btn-add-cart');
      var groupsWrap = addForm.querySelector('[data-variant-groups]');

      addForm.querySelectorAll('.qty-decrease, .qty-increase').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var current = parseInt(qtyInput.value, 10) || 1;
          qtyInput.value = btn.classList.contains('qty-increase') ? current + 1 : Math.max(1, current - 1);
        });
      });

      function flashAdded() {
        if (!addButton) return;
        var original = addButton.getAttribute('data-default-label') || addButton.textContent.trim();
        addButton.setAttribute('data-default-label', original);
        addButton.classList.add('added');
        addButton.textContent = 'Aggiunto ✓';
        window.setTimeout(function () {
          addButton.classList.remove('added');
          addButton.textContent = original;
        }, 1600);
      }

      function addAndCelebrate(variant, qty) {
        var previousProductQty = productQuantityInCart(productId);
        var previousTotal = totalQuantity();
        addToCart(productId, variant, qty);
        flashAdded();

        var mainImg = document.querySelector('[data-product-gallery-main]');
        var title = document.querySelector('.product-detail-info h1');
        var hasPrice = !!document.querySelector('[data-product-price]');
        var productQty = previousProductQty + qty;
        var reachedNewTier = hasPrice && discountPercent(productQty) > discountPercent(previousProductQty);

        // Il numero sul badge si aggiorna quando la foto "atterra" sul carrello.
        setBadgeCount(previousTotal);
        flyToCart(mainImg, addButton, function () {
          bumpCart();
          showAddedToast({
            name: title ? title.textContent.trim() : '',
            image: mainImg ? (mainImg.currentSrc || mainImg.src) : null,
            variant: variant,
            quantity: qty,
            hasPrice: hasPrice,
            productQuantity: productQty
          });
          var tierEl = reachedNewTier && toastEl ? toastEl.querySelector('.cart-toast-tier') : null;
          if (tierEl) {
            var rect = tierEl.getBoundingClientRect();
            burstConfetti(rect.left + rect.width / 2, rect.top + rect.height / 2);
          }
        });
      }

      if (groupsWrap) {
        // Prodotto a varianti raggruppate (es. Colore + Modello iPhone): ogni
        // gruppo è obbligatorio, la selezione va combinata in un'unica stringa.
        var groupEls = Array.prototype.slice.call(groupsWrap.querySelectorAll('[data-group-name]'));
        var selections = {};

        groupEls.forEach(function (groupEl) {
          var groupName = groupEl.getAttribute('data-group-name');

          groupEl.querySelectorAll('.variant-option').forEach(function (btn) {
            btn.addEventListener('click', function () {
              groupEl.querySelectorAll('.variant-option').forEach(function (b) { b.classList.remove('selected'); });
              btn.classList.add('selected');
              selections[groupName] = btn.getAttribute('data-variant');
              if (hint) hint.classList.remove('visible');
            });
          });

          var select = groupEl.querySelector('.variant-select');
          if (select) {
            select.addEventListener('change', function () {
              selections[groupName] = select.value || null;
              if (hint) hint.classList.remove('visible');
            });
          }
        });

        addForm.addEventListener('submit', function (e) {
          e.preventDefault();
          var complete = groupEls.every(function (groupEl) {
            return !!selections[groupEl.getAttribute('data-group-name')];
          });
          if (!complete) {
            if (hint) hint.classList.add('visible');
            return;
          }
          var combined = groupEls.map(function (groupEl) {
            return selections[groupEl.getAttribute('data-group-name')];
          }).join(VARIANT_GROUP_SEPARATOR);
          var qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);
          addAndCelebrate(combined, qty);
        });
      } else {
        var selectedVariant = null;
        var variantButtons = addForm.querySelectorAll('.variant-option');
        var priceEl = document.querySelector('[data-product-price]');

        variantButtons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            variantButtons.forEach(function (b) { b.classList.remove('selected'); });
            btn.classList.add('selected');
            selectedVariant = btn.getAttribute('data-variant');
            if (hint) hint.classList.remove('visible');
            var variantPrice = btn.getAttribute('data-price');
            if (variantPrice !== null && priceEl) {
              priceEl.textContent = formatPrice(parseFloat(variantPrice));
            }
          });
        });

        addForm.addEventListener('submit', function (e) {
          e.preventDefault();
          if (requiresVariant && !selectedVariant) {
            if (hint) hint.classList.add('visible');
            return;
          }
          var qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);
          addAndCelebrate(selectedVariant, qty);
        });
      }
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
