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

  var navToggle = document.getElementById('navToggle');
  var mainNav = document.querySelector('.main-nav');
  if (navToggle && mainNav) {
    navToggle.addEventListener('click', function () {
      var isOpen = mainNav.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  var productSearch = document.getElementById('productSearch');
  var typeFilter = document.getElementById('typeFilter');
  if (productSearch || typeFilter) {
    var applyProductFilters = function () {
      var query = productSearch ? productSearch.value.trim().toLowerCase() : '';
      var type = typeFilter ? typeFilter.value : '';
      document.querySelectorAll('.brand-group').forEach(function (group) {
        var brand = group.getAttribute('data-brand') || '';
        var visibleCount = 0;
        group.querySelectorAll('.product-card').forEach(function (card) {
          var name = card.getAttribute('data-name') || '';
          var cardType = card.getAttribute('data-type') || '';
          var matchesQuery = query === '' || brand.includes(query) || name.includes(query);
          var matchesType = type === '' || cardType === type;
          var matches = matchesQuery && matchesType;
          card.hidden = !matches;
          if (matches) visibleCount++;
        });
        group.hidden = visibleCount === 0;
      });
    };
    if (productSearch) productSearch.addEventListener('input', applyProductFilters);
    if (typeFilter) typeFilter.addEventListener('change', applyProductFilters);
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

});
