<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = get_db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$product = null;

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch();
    if (!$product) {
        redirect('/admin/dashboard.php');
    }
}

$errors = [];
$formData = $product ?: [
    'category' => 'cosmetici',
    'brand' => '',
    'name' => '',
    'variants' => null,
    'image_path' => null,
    'stock_quantity' => 0,
    'orderable_quantity' => 0,
    'price' => null,
    'description' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $formData['category'] = $_POST['category'] ?? '';
    $formData['brand'] = trim($_POST['brand'] ?? '');
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['variants_input'] = trim($_POST['variants_input'] ?? '');
    $formData['stock_quantity'] = (int)($_POST['stock_quantity'] ?? 0);
    $formData['orderable_quantity'] = (int)($_POST['orderable_quantity'] ?? 0);
    $priceInput = trim($_POST['price'] ?? '');
    $formData['price'] = $priceInput === '' ? null : (float)str_replace(',', '.', $priceInput);
    $formData['description'] = trim($_POST['description'] ?? '');

    if (!array_key_exists($formData['category'], CATEGORIES)) {
        $errors[] = 'Seleziona una categoria valida.';
    }
    if ($formData['brand'] === '') {
        $errors[] = 'Il brand è obbligatorio.';
    }
    if ($formData['name'] === '') {
        $errors[] = 'Il nome prodotto è obbligatorio.';
    }
    if ($formData['stock_quantity'] < 0 || $formData['orderable_quantity'] < 0) {
        $errors[] = 'Le quantità non possono essere negative.';
    }

    $newImagePath = $formData['image_path'] ?? null;
    if (empty($errors)) {
        try {
            $uploaded = upload_product_image($_FILES['image'] ?? []);
            if ($uploaded) {
                $newImagePath = $uploaded;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        $variantsJson = encode_variants_from_input($formData['variants_input']);

        if ($id) {
            if ($newImagePath !== $product['image_path']) {
                delete_product_image($product['image_path']);
            }
            $stmt = $pdo->prepare(
                'UPDATE products SET category=:category, brand=:brand, name=:name, variants=:variants,
                 image_path=:image_path, stock_quantity=:stock_quantity, orderable_quantity=:orderable_quantity,
                 price=:price, description=:description WHERE id=:id'
            );
            $stmt->execute([
                'category' => $formData['category'],
                'brand' => $formData['brand'],
                'name' => $formData['name'],
                'variants' => $variantsJson,
                'image_path' => $newImagePath,
                'stock_quantity' => $formData['stock_quantity'],
                'orderable_quantity' => $formData['orderable_quantity'],
                'price' => $formData['price'],
                'description' => $formData['description'] ?: null,
                'id' => $id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO products (category, brand, name, variants, image_path, stock_quantity, orderable_quantity, price, description)
                 VALUES (:category, :brand, :name, :variants, :image_path, :stock_quantity, :orderable_quantity, :price, :description)'
            );
            $stmt->execute([
                'category' => $formData['category'],
                'brand' => $formData['brand'],
                'name' => $formData['name'],
                'variants' => $variantsJson,
                'image_path' => $newImagePath,
                'stock_quantity' => $formData['stock_quantity'],
                'orderable_quantity' => $formData['orderable_quantity'],
                'price' => $formData['price'],
                'description' => $formData['description'] ?: null,
            ]);
        }

        redirect('/admin/dashboard.php');
    }
}

$variantsInputValue = $formData['variants_input'] ?? implode(', ', decode_variants($formData['variants'] ?? null));

$pageTitle = ($id ? 'Modifica prodotto' : 'Nuovo prodotto') . ' · Admin';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-toolbar">
  <h1><?= $id ? 'Modifica prodotto' : 'Nuovo prodotto' ?></h1>
  <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-small">&larr; Torna all'elenco</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="form-error">
    <ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="admin-form">
  <?= csrf_field() ?>

  <div class="form-row">
    <label for="category">Categoria</label>
    <select id="category" name="category" required>
      <?php foreach (CATEGORIES as $slug => $label): ?>
        <option value="<?= h($slug) ?>" <?= $formData['category'] === $slug ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-row">
    <label for="brand">Brand</label>
    <input type="text" id="brand" name="brand" required value="<?= h($formData['brand']) ?>">
  </div>

  <div class="form-row">
    <label for="name">Nome prodotto</label>
    <input type="text" id="name" name="name" required value="<?= h($formData['name']) ?>">
  </div>

  <div class="form-row">
    <label for="variants_input">Varianti / colori (separati da virgola)</label>
    <input type="text" id="variants_input" name="variants_input" placeholder="Rosso, Blu, Taglia M" value="<?= h($variantsInputValue) ?>">
  </div>

  <div class="form-row">
    <label for="image">Immagine prodotto</label>
    <?php if (!empty($formData['image_path'])): ?>
      <img src="<?= h(url($formData['image_path'])) ?>" alt="" class="current-image-preview">
    <?php endif; ?>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
  </div>

  <div class="form-row form-row-split">
    <div>
      <label for="stock_quantity">Quantità a magazzino</label>
      <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?= (int)$formData['stock_quantity'] ?>">
    </div>
    <div>
      <label for="orderable_quantity">Quantità ordinabile</label>
      <input type="number" id="orderable_quantity" name="orderable_quantity" min="0" value="<?= (int)$formData['orderable_quantity'] ?>">
    </div>
  </div>

  <div class="form-row">
    <label for="price">Prezzo (opzionale, es. 19,90)</label>
    <input type="text" id="price" name="price" value="<?= $formData['price'] !== null ? h(number_format((float)$formData['price'], 2, ',', '')) : '' ?>">
  </div>

  <div class="form-row">
    <label for="description">Descrizione (opzionale)</label>
    <textarea id="description" name="description" rows="4"><?= h($formData['description'] ?? '') ?></textarea>
  </div>

  <button type="submit" class="btn btn-primary"><?= $id ? 'Salva modifiche' : 'Crea prodotto' ?></button>
</form>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
