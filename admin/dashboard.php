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

$categoryCounts = $pdo->query('SELECT category, COUNT(*) FROM products GROUP BY category')->fetchAll(PDO::FETCH_KEY_PAIR);
$withCostCount = count(array_filter($products, fn($p) => $p['purchase_price'] !== null));

$pageTitle = 'Prodotti · Admin';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-toolbar">
  <div>
    <h1>Prodotti</h1>
    <p class="admin-toolbar-meta">
      <?= count($products) ?> <?= count($products) === 1 ? 'prodotto' : 'prodotti' ?>
      · <?= $withCostCount ?> con prezzo d'acquisto
    </p>
  </div>
  <div class="admin-toolbar-actions">
    <a href="<?= url('admin/purchase-prices.php') ?>" class="btn btn-small">Importa prezzi d'acquisto</a>
    <a href="<?= url('admin/product-form.php') ?>" class="btn btn-primary">+ Nuovo prodotto</a>
  </div>
</div>

<div class="search-box admin-search-box">
  <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
  <input type="search" id="adminProductSearch" placeholder="Cerca per brand, nome o variante...">
</div>

<div class="admin-filters">
  <a href="<?= url('admin/dashboard.php') ?>" class="filter-pill <?= $categoryFilter === '' ? 'active' : '' ?>">Tutte <span class="filter-count"><?= array_sum($categoryCounts) ?></span></a>
  <?php foreach (CATEGORIES as $slug => $label): ?>
    <a href="<?= url('admin/dashboard.php?category=' . $slug) ?>" class="filter-pill <?= $categoryFilter === $slug ? 'active' : '' ?>"><?= h($label) ?> <span class="filter-count"><?= (int)($categoryCounts[$slug] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>

<?php if (empty($products)): ?>
  <p class="empty-state">Nessun prodotto trovato. <a href="<?= url('admin/product-form.php') ?>">Aggiungine uno</a>.</p>
<?php else: ?>
<div class="admin-table-wrap products-table-wrap" id="productsTableWrap">
  <table class="admin-table products-table">
    <thead>
      <tr>
        <th class="col-product">Prodotto</th>
        <th class="col-num">Prezzo</th>
        <th class="col-num">Costo</th>
        <th class="col-num">Netto</th>
        <th class="col-status">Stato</th>
        <th class="col-actions-head"><span class="visually-hidden">Azioni</span></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $product): ?>
        <?php
          $productVariants = decode_variants($product['variants']);
          $variantsSummary = admin_variants_summary($productVariants);
          $editUrl = url('admin/product-form.php?id=' . (int)$product['id']);
          $purchasePrice = $product['purchase_price'] !== null ? (float)$product['purchase_price'] : null;
          $salePrice = $product['price'] !== null ? (float)$product['price'] : null;
          $netProfit = product_net_profit($salePrice, $purchasePrice);
          $variantPrices = array_map('floatval', decode_variant_prices($product['variant_prices'] ?? null));
          $productMeta = array_filter([
              $categoryFilter === '' ? category_label($product['category']) : '',
              $variantsSummary['label'],
          ]);
          $searchText = $product['brand'] . ' ' . $product['name'] . ' ' . $variantsSummary['label'] . ' ' . $variantsSummary['title'];
        ?>
        <tr data-search="<?= h(mb_strtolower($searchText)) ?>">
          <td class="col-product">
            <a href="<?= $editUrl ?>" class="table-product">
              <?php if (!empty($product['image_path'])): ?>
                <img src="<?= h(url($product['image_path'])) ?>" alt="" class="table-thumb" loading="lazy">
              <?php else: ?>
                <span class="table-thumb img-placeholder">BD</span>
              <?php endif; ?>
              <span class="table-product-text">
                <span class="table-product-brand"><?= h($product['brand']) ?></span>
                <span class="table-product-name"><?= h($product['name']) ?></span>
                <?php if (!empty($productMeta)): ?>
                  <span class="table-product-meta"<?= $variantsSummary['title'] !== '' ? ' title="' . h($variantsSummary['title']) . '"' : '' ?>><?= h(implode(' · ', $productMeta)) ?></span>
                <?php endif; ?>
              </span>
            </a>
          </td>
          <td class="col-num" data-label="Prezzo">
            <?php if ($salePrice === null): ?>
              <span class="table-empty">&mdash;</span>
            <?php else: ?>
              <span class="table-amount"><?= format_price($salePrice) ?></span>
              <?php if (!empty($variantPrices)): ?>
                <?php $allPrices = array_merge([$salePrice], array_values($variantPrices)); ?>
                <span class="cost-detail">da <?= number_format(min($allPrices), 2, ',', '.') ?> a <?= number_format(max($allPrices), 2, ',', '.') ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td class="col-num" data-label="Costo">
            <?php if ($purchasePrice === null): ?>
              <span class="table-empty">&mdash;</span>
            <?php else: ?>
              <span class="table-amount"><?= format_price(product_total_cost($purchasePrice)) ?></span>
              <span class="cost-detail"><?= number_format($purchasePrice, 2, ',', '.') ?> + <?= number_format(SHIPPING_COST, 2, ',', '.') ?> sped.</span>
            <?php endif; ?>
          </td>
          <td class="col-num" data-label="Netto">
            <?php if ($netProfit === null): ?>
              <span class="table-empty">&mdash;</span>
            <?php else: ?>
              <span class="table-amount <?= $netProfit >= 0 ? 'net-positive' : 'net-negative' ?>"><?= format_price($netProfit) ?></span>
              <?php if ($salePrice > 0): ?>
                <span class="cost-detail">margine <?= (int)round($netProfit / $salePrice * 100) ?>%</span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td class="col-status">
            <div class="badges">
              <?php if (!empty($product['is_special_edition'])): ?><span class="badge badge-special">Special Edition</span><?php endif; ?>
              <?php if ((int)$product['stock_quantity'] >= 1): ?><span class="badge badge-available">Disponibile · <?= (int)$product['stock_quantity'] ?></span><?php endif; ?>
              <?php if ((int)$product['orderable_quantity'] >= 1): ?><span class="badge badge-orderable">Ordinabile · <?= (int)$product['orderable_quantity'] ?></span><?php endif; ?>
              <?php if (in_array((int)$product['id'], $activeOfferProductIds, true)): ?><span class="badge badge-offer">In offerta</span><?php endif; ?>
              <?php if ((int)$product['stock_quantity'] < 1 && (int)$product['orderable_quantity'] < 1): ?><span class="badge badge-muted">Esaurito</span><?php endif; ?>
            </div>
          </td>
          <td class="col-actions">
            <a href="<?= $editUrl ?>" class="icon-btn" title="Modifica" aria-label="Modifica <?= h($product['name']) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20h4L19 9l-4-4L4 16v4Z" stroke-linejoin="round"/><path d="m13.5 6.5 4 4" stroke-linecap="round"/></svg>
            </a>
            <form method="post" class="inline-form delete-product-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
              <input type="hidden" name="category" value="<?= h($categoryFilter) ?>">
              <button type="submit" class="icon-btn icon-btn-danger" title="Elimina" aria-label="Elimina <?= h($product['name']) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4.5h6V7M6.5 7l1 13h9l1-13" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11v5.5M14 11v5.5" stroke-linecap="round"/></svg>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="table-search-empty" id="adminSearchEmpty" hidden>Nessun prodotto corrisponde alla ricerca.</p>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
