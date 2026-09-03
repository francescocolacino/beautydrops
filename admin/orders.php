<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = get_db();

$orders = $pdo->query(
    'SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
     FROM orders o ORDER BY o.created_at DESC'
)->fetchAll();

$pageTitle = 'Ordini · Admin';
$activeNav = 'orders';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-toolbar">
  <h1>Ordini</h1>
</div>

<?php if (empty($orders)): ?>
  <p class="empty-state">Nessun preventivo ricevuto finora.</p>
<?php else: ?>
<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Data</th>
        <th>Cliente</th>
        <th>Email</th>
        <th>Articoli</th>
        <th>Totale</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $order): ?>
        <tr>
          <td><?= h(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
          <td><?= h($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></td>
          <td><?= h($order['customer_email']) ?></td>
          <td><?= (int)$order['item_count'] ?></td>
          <td>
            <?= format_price((float)$order['total']) ?>
            <?php if ($order['has_price_on_request']): ?>
              <span class="muted">(+ articoli su richiesta)</span>
            <?php endif; ?>
          </td>
          <td class="col-actions">
            <?php if (!empty($order['pdf_path'])): ?>
              <a href="<?= url($order['pdf_path']) ?>" class="btn btn-small" target="_blank" rel="noopener">PDF</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
