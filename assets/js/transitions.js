// Caricato nell'<head> (non in fondo alla pagina come main.js): sia la
// dissolvenza delle foto sia le View Transitions devono agganciarsi prima che
// la pagina venga disegnata la prima volta.
(function () {
  'use strict';

  var root = document.documentElement;

  // ---- Foto in dissolvenza ----
  // Gli eventi "load" delle immagini non risalgono il DOM ma si possono
  // intercettare in fase di cattura: un solo listener copre anche le foto
  // lazy e quelle create dopo da cart.js.
  root.classList.add('bd-fade');

  function markLoaded(e) {
    if (e.target && e.target.tagName === 'IMG') e.target.classList.add('is-loaded');
  }
  document.addEventListener('load', markLoaded, true);
  document.addEventListener('error', markLoaded, true);

  function markCompleteImages() {
    document.querySelectorAll('img').forEach(function (img) {
      if (img.complete) img.classList.add('is-loaded');
    });
  }
  document.addEventListener('DOMContentLoaded', markCompleteImages);
  window.addEventListener('pageshow', markCompleteImages);

  // ---- View Transitions tra card e pagina prodotto ----
  // Un solo elemento per pagina può avere il nome: lo assegniamo al volo alla
  // foto della card cliccata (pagina che si chiude) e alla foto principale
  // della pagina prodotto (pagina che si apre), o viceversa tornando indietro.
  var VT_NAME = 'bd-product-image';
  var namedImg = null;
  var lastClickedHref = null;

  function setTransitionImage(img) {
    if (namedImg) namedImg.style.viewTransitionName = '';
    namedImg = img || null;
    if (namedImg) namedImg.style.viewTransitionName = VT_NAME;
  }

  function productIdFromUrl(url) {
    try {
      var parsed = new URL(url, window.location.href);
      return /\/product\.php$/.test(parsed.pathname) ? parsed.searchParams.get('id') : null;
    } catch (e) {
      return null;
    }
  }

  function cardImageFor(productId) {
    var cards = document.querySelectorAll('a.product-card[href]');
    for (var i = 0; i < cards.length; i++) {
      if (productIdFromUrl(cards[i].href) === productId) return cards[i].querySelector('.product-image img');
    }
    return null;
  }

  function mainProductImage() {
    return document.querySelector('[data-product-gallery-main]');
  }

  function isVisible(el) {
    var rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.bottom > 0 && rect.top < window.innerHeight;
  }

  // Le card con AOS non ancora animate sono trasparenti: meglio nessun
  // effetto che una foto che "vola" verso il vuoto.
  function isSettled(el) {
    var aos = el.closest('[data-aos]');
    return !aos || aos.classList.contains('aos-animate');
  }

  document.addEventListener('click', function (e) {
    var link = e.target.closest ? e.target.closest('a[href]') : null;
    if (link) lastClickedHref = link.href;
  }, true);

  window.addEventListener('pageswap', function (e) {
    if (!e.viewTransition) return;
    var targetUrl = (e.activation && e.activation.entry && e.activation.entry.url) || lastClickedHref;
    var targetId = targetUrl ? productIdFromUrl(targetUrl) : null;
    var img = targetId ? cardImageFor(targetId) : mainProductImage();
    setTransitionImage(img && isVisible(img) ? img : null);
  });

  window.addEventListener('pagereveal', function (e) {
    setTransitionImage(null);
    if (!e.viewTransition) return;

    var img = mainProductImage();
    if (!img) {
      var activation = window.navigation && window.navigation.activation;
      var fromUrl = (activation && activation.from && activation.from.url) || document.referrer;
      var fromId = fromUrl ? productIdFromUrl(fromUrl) : null;
      img = fromId ? cardImageFor(fromId) : null;
      if (img && !(isVisible(img) && isSettled(img))) img = null;
    }
    setTransitionImage(img);
    e.viewTransition.finished.finally(function () { setTransitionImage(null); });
  });
})();
