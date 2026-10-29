document.addEventListener('DOMContentLoaded', function () {
  if (window.AOS) {
    AOS.init({ duration: 600, once: true, offset: 60 });
  }

  var siteHeader = document.getElementById('siteHeader');
  if (siteHeader) {
    var toggleHeaderShadow = function () {
      siteHeader.classList.toggle('scrolled', window.scrollY > 10);
    };
    toggleHeaderShadow();
    window.addEventListener('scroll', toggleHeaderShadow, { passive: true });
  }

  var productSearch = document.getElementById('productSearch');
  if (productSearch) {
    // Minuscole, senza accenti né punteggiatura: "E.L.F." si trova con "elf",
    // "Crème" con "creme".
    var normalizeSearch = function (text) {
      return (text || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9\s]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
    };

    var searchCards = Array.prototype.map.call(document.querySelectorAll('.brand-group .product-card'), function (card) {
      return { card: card, text: normalizeSearch(card.getAttribute('data-search') || card.getAttribute('data-name')) };
    });
    var brandGroups = document.querySelectorAll('.brand-group');
    var categoryCount = document.getElementById('categoryCount');
    var defaultCountText = categoryCount ? categoryCount.textContent : '';
    var searchEmpty = document.getElementById('searchEmpty');
    var brandIndex = document.querySelector('.brand-index');

    var applyProductFilters = function () {
      var rawQuery = productSearch.value.trim();
      var words = normalizeSearch(rawQuery).split(' ').filter(Boolean);
      var total = 0;

      // Ogni parola scritta deve comparire (anche a metà) in brand, nome,
      // varianti o tipo: più lettere si aggiungono, più i risultati si restringono.
      searchCards.forEach(function (entry) {
        var matches = words.every(function (word) { return entry.text.indexOf(word) !== -1; });
        entry.card.hidden = !matches;
        if (matches) {
          total++;
          // Le card ancora in attesa dell'animazione di comparsa resterebbero
          // invisibili finché non si scorre: le mostriamo subito.
          entry.card.classList.add('aos-animate');
        }
      });

      brandGroups.forEach(function (group) {
        var hasResults = group.querySelector('.product-card:not([hidden])') !== null;
        group.hidden = !hasResults;
        if (hasResults) {
          group.querySelectorAll('[data-aos]').forEach(function (el) { el.classList.add('aos-animate'); });
        }
        var indexLink = document.querySelector('.brand-index a[href="#' + group.id + '"]');
        if (indexLink) indexLink.hidden = !hasResults;
      });
      if (brandIndex) brandIndex.hidden = total === 0;

      if (categoryCount) {
        if (words.length === 0) {
          categoryCount.textContent = defaultCountText;
        } else if (total === 0) {
          categoryCount.textContent = 'Nessun prodotto trovato per «' + rawQuery + '»';
        } else {
          categoryCount.textContent = total + (total === 1 ? ' prodotto trovato' : ' prodotti trovati') + ' per «' + rawQuery + '»';
        }
      }
      if (searchEmpty) searchEmpty.hidden = !(words.length > 0 && total === 0);
    };

    productSearch.addEventListener('input', applyProductFilters);
    productSearch.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        productSearch.value = '';
        applyProductFilters();
      } else if (e.key === 'Enter') {
        // Su telefono chiude la tastiera e porta ai risultati.
        e.preventDefault();
        productSearch.blur();
        var firstResult = document.querySelector('.brand-group:not([hidden])');
        if (firstResult) firstResult.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }

  var adminProductSearch = document.getElementById('adminProductSearch');
  if (adminProductSearch) {
    adminProductSearch.addEventListener('input', function () {
      var query = adminProductSearch.value.trim().toLowerCase();
      document.querySelectorAll('#productsTableWrap tbody tr').forEach(function (row) {
        var haystack = row.getAttribute('data-search') || '';
        row.hidden = query !== '' && !haystack.includes(query);
      });
    });
  }

  var PRODUCTS_SCROLL_KEY = 'bdAdminProductsScrollY';
  var productsTableWrap = document.getElementById('productsTableWrap');
  if (productsTableWrap) {
    var savedProductsScrollY = sessionStorage.getItem(PRODUCTS_SCROLL_KEY);
    if (savedProductsScrollY !== null) {
      window.scrollTo(0, parseInt(savedProductsScrollY, 10) || 0);
      sessionStorage.removeItem(PRODUCTS_SCROLL_KEY);
    }

    productsTableWrap.querySelectorAll('a.btn-small').forEach(function (link) {
      link.addEventListener('click', function () {
        sessionStorage.setItem(PRODUCTS_SCROLL_KEY, String(window.scrollY));
      });
    });

    productsTableWrap.querySelectorAll('form.delete-product-form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.confirm('Eliminare definitivamente questo prodotto?')) {
          e.preventDefault();
          return;
        }
        sessionStorage.setItem(PRODUCTS_SCROLL_KEY, String(window.scrollY));
      });
    });
  }

  var offerProductSearch = document.getElementById('offerProductSearch');
  if (offerProductSearch) {
    offerProductSearch.addEventListener('input', function () {
      var query = offerProductSearch.value.trim().toLowerCase();
      document.querySelectorAll('.offer-product-item').forEach(function (item) {
        var haystack = item.getAttribute('data-search') || '';
        item.hidden = query !== '' && !haystack.includes(query);
      });
    });
  }

  var productGalleryMain = document.querySelector('[data-product-gallery-main]');
  var productGalleryThumbnails = document.querySelectorAll('[data-product-gallery-thumbnail]');
  if (productGalleryMain && productGalleryThumbnails.length) {
    // Dissolvenza tra una foto e l'altra: la nuova viene precaricata e
    // sostituita solo quando la vecchia è già sparita, così non si vede mai
    // un'immagine a metà caricamento.
    var gallerySwapId = 0;
    var swapMainImage = function (imagePath) {
      if (productGalleryMain.getAttribute('src') === imagePath) return;
      var swapId = ++gallerySwapId;
      var isLoaded = false;
      var isFadedOut = false;
      var finishSwap = function () {
        if (swapId !== gallerySwapId || !isLoaded || !isFadedOut) return;
        productGalleryMain.src = imagePath;
        productGalleryMain.classList.remove('is-swapping');
      };
      var preload = new Image();
      preload.onload = preload.onerror = function () { isLoaded = true; finishSwap(); };
      preload.src = imagePath;
      productGalleryMain.classList.add('is-swapping');
      window.setTimeout(function () { isFadedOut = true; finishSwap(); }, 220);
    };

    productGalleryThumbnails.forEach(function (thumbnail) {
      thumbnail.addEventListener('click', function () {
        var imagePath = thumbnail.getAttribute('data-image');
        if (!imagePath) return;

        swapMainImage(imagePath);
        productGalleryThumbnails.forEach(function (item) {
          var isSelected = item === thumbnail;
          item.classList.toggle('active', isSelected);
          item.setAttribute('aria-current', isSelected ? 'true' : 'false');
        });
      });
    });
  }

  var unavailablePopup = document.querySelector('[data-unavailable-popup]');
  if (unavailablePopup) {
    // Avviso dei brand momentaneamente non ordinabili (UNAVAILABLE_BRANDS in
    // includes/functions.php): si apre da solo all'arrivo sulla pagina.
    var closeUnavailablePopup = function () {
      unavailablePopup.classList.remove('open');
      window.setTimeout(function () { unavailablePopup.hidden = true; }, 220);
    };
    unavailablePopup.hidden = false;
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { unavailablePopup.classList.add('open'); });
    });
    unavailablePopup.querySelectorAll('[data-unavailable-popup-close]').forEach(function (btn) {
      btn.addEventListener('click', closeUnavailablePopup);
    });
    unavailablePopup.addEventListener('click', function (e) {
      if (e.target === unavailablePopup) closeUnavailablePopup();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !unavailablePopup.hidden) closeUnavailablePopup();
    });
  }

});
