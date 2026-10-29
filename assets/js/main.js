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
    var applyProductFilters = function () {
      var query = productSearch.value.trim().toLowerCase();
      document.querySelectorAll('.brand-group').forEach(function (group) {
        var brand = group.getAttribute('data-brand') || '';
        var visibleCount = 0;
        group.querySelectorAll('.product-card').forEach(function (card) {
          var name = card.getAttribute('data-name') || '';
          var matches = query === '' || brand.includes(query) || name.includes(query);
          card.hidden = !matches;
          if (matches) visibleCount++;
        });
        group.hidden = visibleCount === 0;
      });
    };
    productSearch.addEventListener('input', applyProductFilters);
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
