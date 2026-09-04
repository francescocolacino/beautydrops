<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT image_path FROM products WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch();

    if ($product) {
        $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);
        delete_product_image($product['image_path']);
    }

    redirect('/admin/dashboard.php' . (!empty($_POST['category']) ? '?category=' . urlencode($_POST['category']) : ''));
}

$categoryFilter = $_GET['category'] ?? '';
if ($categoryFilter !== '' && !array_key_exists($categoryFilter, CATEGORIES)) {
    $categoryFilter = '';
}

if ($categoryFilter !== '') {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE category = :category ORDER BY updated_at DESC');
    $stmt->execute(['category' => $categoryFilter]);
} else {
    $stmt = $pdo->query('SELECT * FROM products ORDER BY updated_at DESC');
}
$products = $stmt->fetchAll();

$activeOfferProductIds = get_active_offer_product_ids($pdo);

$pageTitle = 'Prodotti · Admin';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-toolbar">
  <h1>Prodotti</h1>
  <a href="<?= url('admin/product-form.php') ?>" class="btn btn-primary">+ Nuovo prodotto</a>
</div>

<div class="search-box admin-search-box">
  <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
  <input type="search" id="adminProductSearch" placeholder="Cerca per brand o nome prodotto...">
</div>

<div class="admin-filters">
  <a href="<?= url('admin/dashboard.php') ?>" class="filter-pill <?= $categoryFilter === '' ? 'active' : '' ?>">Tutte</a>
  <?php foreach (CATEGORIES as $slug => $label): ?>
    <a href="<?= url('admin/dashboard.php?category=' . $slug) ?>" class="filter-pill <?= $categoryFilter === $slug ? 'active' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (empty($products)): ?>
  <p class="empty-state">Nessun prodotto trovato. <a href="<?= url('admin/product-form.php') ?>">Aggiungine uno</a>.</p>
<?php else: ?>
<div class="admin-table-wrap" id="productsTableWrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th></th>
        <th>Nome</th>
        <th>Brand</th>
        <th>Varianti</th>
        <th>Categoria</th>
        <th>Prezzo</th>
        <th>Stato</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $product): ?>
        <?php $productVariants = decode_variants($product['variants']); ?>
        <tr data-search="<?= h(mb_strtolower($product['brand'] . ' ' . $product['name'])) ?>">
          <td class="col-thumb">
            <?php if (!empty($product['image_path'])): ?>
              <img src="<?= h(url($product['image_path'])) ?>" alt="" class="table-thumb">
            <?php else: ?>
              <div class="table-thumb img-placeholder">BD</div>
            <?php endif; ?>
          </td>
          <td><?= h($product['name']) ?></td>
          <td><?= h($product['brand']) ?></td>
          <td>
            <?php if (empty($productVariants)): ?>
              &mdash;
            <?php elseif (is_variant_groups($productVariants)): ?>
              <div class="table-chips">
                <?php foreach ($productVariants as $groupLabel => $groupOptions): ?>
                  <span class="chip"><?= h($groupLabel) ?>: <?= h(implode(', ', $groupOptions)) ?></span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="table-chips">
                <?php foreach ($productVariants as $variant): ?>
                  <span class="chip"><?= h($variant) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
          <td><?= h(category_label($product['category'])) ?></td>
          <td><?= $product['price'] !== null ? format_price((float)$product['price']) : '&mdash;' ?></td>
          <td><?= render_product_badges($product, $activeOfferProductIds) ?></td>
          <td class="col-actions">
            <a href="<?= url('admin/product-form.php?id=' . (int)$product['id']) ?>" class="btn btn-small">Modifica</a>
            <form method="post" class="inline-form delete-product-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
              <input type="hidden" name="category" value="<?= h($categoryFilter) ?>">
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
