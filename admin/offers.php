<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM offers WHERE id = :id')->execute(['id' => $id]);
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE offers SET active = NOT active WHERE id = :id')->execute(['id' => $id]);
    }

    redirect('/admin/offers.php');
}

$offers = $pdo->query('SELECT * FROM offers ORDER BY created_at DESC')->fetchAll();
foreach ($offers as &$offer) {
    $offer['products'] = get_offer_products($pdo, (int)$offer['id']);
}
unset($offer);

$pageTitle = 'Offerte · Admin';
$activeNav = 'offers';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-toolbar">
  <h1>Offerte</h1>
  <a href="<?= url('admin/offer-form.php') ?>" class="btn btn-primary">+ Nuova offerta</a>
</div>

<?php if (empty($offers)): ?>
  <p class="empty-state">Nessuna offerta creata. <a href="<?= url('admin/offer-form.php') ?>">Creane una</a>.</p>
<?php else: ?>
<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Nome</th>
        <th>Prodotti inclusi</th>
        <th>Prezzo offerta</th>
        <th>Stato</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($offers as $offer): ?>
        <tr>
          <td><?= h($offer['name']) ?></td>
          <td><?= h(implode(', ', array_map(fn($p) => $p['name'], $offer['products']))) ?></td>
          <td><?= format_price((float)$offer['offer_price']) ?></td>
          <td>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$offer['id'] ?>">
              <button type="submit" class="status-toggle <?= $offer['active'] ? 'is-active' : '' ?>">
                <?= $offer['active'] ? 'Attiva' : 'Disattivata' ?>
              </button>
            </form>
          </td>
          <td class="col-actions">
            <a href="<?= url('admin/offer-form.php?id=' . (int)$offer['id']) ?>" class="btn btn-small">Modifica</a>
            <form method="post" class="inline-form" onsubmit="return confirm('Eliminare definitivamente questa offerta?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$offer['id'] ?>">
              <button type="submit" class="btn btn-small btn-danger">Elimina</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
