<?php
require_once __DIR__ . '/includes/functions.php';

$orderId = isset($_GET['order']) ? (int) $_GET['order'] : 0;
$token = (string) ($_GET['token'] ?? '');

$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id AND access_token = :token');
$stmt->execute(['id' => $orderId, 'token' => $token]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Ordine non trovato · ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<section class="container empty-state"><p>Preventivo non trovato. <a href="' . h(url('index.php')) . '">Torna alla home</a>.</p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = 'Preventivo inviato · ' . SITE_NAME;
require __DIR__ . '/includes/header.php';

$pdfUrl = !empty($order['pdf_path']) ? url($order['pdf_path']) : null;
?>

<section class="container">
  <div class="order-confirmation" data-aos="fade-up">
    <div class="check-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <h1>Grazie, <?= h($order['customer_first_name']) ?>!</h1>
    <p>Il tuo preventivo #<?= (int) $order['id'] ?> è pronto. Non è un ordine pagato: scarica il PDF e invialo a chi ti ha condiviso questo link, per confermarlo insieme in privato.</p>

    <?php if ($pdfUrl): ?>
      <p class="product-detail-price"><?= format_price((float) $order['total']) ?></p>
    <?php endif; ?>

    <div class="order-actions">
      <?php if ($pdfUrl): ?>
        <a href="<?= h($pdfUrl) ?>" class="btn btn-primary btn-block" target="_blank" rel="noopener">Scarica il PDF del preventivo</a>
      <?php endif; ?>
      <a href="<?= url('index.php') ?>" class="back-link">&larr; Torna al catalogo</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
