<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = get_db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$offer = null;
$selectedProductIds = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM offers WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $offer = $stmt->fetch();
    if (!$offer) {
        redirect('/admin/offers.php');
    }
    $stmt = $pdo->prepare('SELECT product_id FROM offer_products WHERE offer_id = :id');
    $stmt->execute(['id' => $id]);
    $selectedProductIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$errors = [];
$formData = [
    'name' => $offer['name'] ?? '',
    'offer_price' => $offer['offer_price'] ?? '',
    'active' => $offer ? (bool)$offer['active'] : true,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $formData['name'] = trim($_POST['name'] ?? '');
    $priceInput = trim($_POST['offer_price'] ?? '');
    $formData['offer_price'] = $priceInput === '' ? null : (float)str_replace(',', '.', $priceInput);
    $formData['active'] = isset($_POST['active']);
    $selectedProductIds = array_map('intval', $_POST['product_ids'] ?? []);

    if ($formData['name'] === '') {
        $errors[] = 'Il nome offerta è obbligatorio.';
    }
    if ($formData['offer_price'] === null || $formData['offer_price'] <= 0) {
        $errors[] = 'Inserisci un prezzo offerta valido.';
    }
    if (empty($selectedProductIds)) {
        $errors[] = 'Seleziona almeno un prodotto per l\'offerta.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();

        if ($id) {
            $stmt = $pdo->prepare('UPDATE offers SET name=:name, offer_price=:offer_price, active=:active WHERE id=:id');
            $stmt->execute([
                'name' => $formData['name'],
                'offer_price' => $formData['offer_price'],
                'active' => $formData['active'] ? 1 : 0,
                'id' => $id,
            ]);
            $pdo->prepare('DELETE FROM offer_products WHERE offer_id = :id')->execute(['id' => $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO offers (name, offer_price, active) VALUES (:name, :offer_price, :active)');
            $stmt->execute([
                'name' => $formData['name'],
                'offer_price' => $formData['offer_price'],
                'active' => $formData['active'] ? 1 : 0,
            ]);
            $id = (int)$pdo->lastInsertId();
        }

        $insertStmt = $pdo->prepare('INSERT INTO offer_products (offer_id, product_id) VALUES (:offer_id, :product_id)');
        foreach ($selectedProductIds as $productId) {
            $insertStmt->execute(['offer_id' => $id, 'product_id' => $productId]);
        }

        $pdo->commit();
        redirect('/admin/offers.php');
    }
}

$allProducts = $pdo->query('SELECT id, name, brand, category, price FROM products ORDER BY brand ASC, name ASC')->fetchAll();

$pageTitle = ($id ? 'Modifica offerta' : 'Nuova offerta') . ' · Admin';
$activeNav = 'offers';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-toolbar">
  <h1><?= $id ? 'Modifica offerta' : 'Nuova offerta' ?></h1>
  <a href="<?= url('admin/offers.php') ?>" class="btn btn-small">&larr; Torna all'elenco</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="form-error">
    <ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" class="admin-form">
  <?= csrf_field() ?>

  <div class="form-row">
    <label for="name">Nome offerta</label>
    <input type="text" id="name" name="name" required value="<?= h($formData['name']) ?>" placeholder="Es. Rhode Lip Bundle">
  </div>

  <div class="form-row">
    <label for="offer_price">Prezzo unico offerta</label>
    <input type="text" id="offer_price" name="offer_price" required value="<?= $formData['offer_price'] !== '' && $formData['offer_price'] !== null ? h(number_format((float)$formData['offer_price'], 2, ',', '')) : '' ?>" placeholder="Es. 39,90">
  </div>

  <div class="form-row">
    <label class="checkbox-label">
      <input type="checkbox" name="active" <?= $formData['active'] ? 'checked' : '' ?>>
      Offerta attiva (visibile sul sito)
    </label>
  </div>

  <div class="form-row">
    <label>Prodotti inclusi</label>
    <input type="search" id="offerProductSearch" placeholder="Cerca prodotto per nome o brand...">
    <div class="offer-product-list">
      <?php if (empty($allProducts)): ?>
        <p class="empty-state">Nessun prodotto disponibile. <a href="<?= url('admin/product-form.php') ?>">Crea un prodotto</a> prima di aggiungere un'offerta.</p>
      <?php endif; ?>
      <?php foreach ($allProducts as $p): ?>
        <label class="offer-product-item" data-search="<?= h(mb_strtolower($p['name'] . ' ' . $p['brand'])) ?>">
          <input type="checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $selectedProductIds, true) ? 'checked' : '' ?>>
          <span><strong><?= h($p['brand']) ?></strong> — <?= h($p['name']) ?> <span class="muted"><?= h(category_label($p['category'])) ?><?= $p['price'] !== null ? ' · ' . format_price((float)$p['price']) : '' ?></span></span>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <button type="submit" class="btn btn-primary"><?= $id ? 'Salva modifiche' : 'Crea offerta' ?></button>
</form>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
