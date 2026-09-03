  <footer class="site-footer">
    <div class="container site-footer-inner">
      <img
        src="<?= url('assets/images/beautydrops-logo.png') ?>"
        class="brand-logo footer-logo"
        width="1942"
        height="809"
        alt="Beauty Drops"
        loading="lazy"
      >
      <p>&copy; <?= date('Y') ?> BeautyDrops. Tutti i diritti riservati.</p>
    </div>
  </footer>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
  <script>window.BD_BASE_URL = <?= json_encode(url(''), JSON_UNESCAPED_SLASHES) ?>;</script>
  <script src="<?= url('assets/js/main.js') ?>"></script>
  <script src="<?= url('assets/js/cart.js') ?>"></script>
</body>
</html>
