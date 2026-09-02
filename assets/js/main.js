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
  if (productSearch) {
    productSearch.addEventListener('input', function () {
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

  var modalOverlay = document.getElementById('productModalOverlay');
  var modalContent = document.getElementById('productModalContent');
  var modalClose = document.getElementById('productModalClose');
  var lastFocusedBeforeModal = null;

  function openProductModal(productId) {
    var template = document.querySelector('.product-detail-template[data-product-id="' + productId + '"]');
    if (!template || !modalOverlay || !modalContent) return;
    modalContent.innerHTML = '';
    modalContent.appendChild(template.content.cloneNode(true));
    lastFocusedBeforeModal = document.activeElement;
    modalOverlay.hidden = false;
    document.body.classList.add('modal-open');
    requestAnimationFrame(function () { modalOverlay.classList.add('open'); });
    if (modalClose) modalClose.focus();
  }

  function closeProductModal() {
    if (!modalOverlay || !modalOverlay.classList.contains('open')) return;
    modalOverlay.classList.remove('open');
    document.body.classList.remove('modal-open');
    window.setTimeout(function () { modalOverlay.hidden = true; }, 220);
    if (lastFocusedBeforeModal) lastFocusedBeforeModal.focus();
  }

  document.querySelectorAll('.product-card[data-product-id]').forEach(function (card) {
    card.addEventListener('click', function (e) {
      if (e.target.closest('.product-variants')) return;
      openProductModal(card.getAttribute('data-product-id'));
    });
    card.addEventListener('keydown', function (e) {
      if (e.target.closest('.product-variants')) return;
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openProductModal(card.getAttribute('data-product-id'));
      }
    });
  });

  if (modalClose) modalClose.addEventListener('click', closeProductModal);
  if (modalOverlay) {
    modalOverlay.addEventListener('click', function (e) {
      if (e.target === modalOverlay) closeProductModal();
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeProductModal();
  });
});
