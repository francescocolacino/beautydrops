document.addEventListener('DOMContentLoaded', function () {
  if (window.AOS) {
    AOS.init({ duration: 600, once: true, offset: 60 });
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
