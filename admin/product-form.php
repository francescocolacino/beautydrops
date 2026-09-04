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

// Azioni sulla galleria foto: form separati dal salvataggio prodotto
// principale (riconosciuti dal campo "action", assente nel form principale).
if ($id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    $galleryAction = $_POST['action'];

    if ($galleryAction === 'gallery_delete') {
        $galleryImageId = (int)($_POST['gallery_image_id'] ?? 0);
        $pdo->prepare('DELETE FROM product_gallery_images WHERE id = :gallery_id AND product_id = :product_id')
            ->execute(['gallery_id' => $galleryImageId, 'product_id' => $id]);
        redirect('/admin/product-form.php?id=' . $id);
    }

    if ($galleryAction === 'gallery_set_cover') {
        $galleryImageId = (int)($_POST['gallery_image_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT image_path FROM product_gallery_images WHERE id = :gallery_id AND product_id = :product_id');
        $stmt->execute(['gallery_id' => $galleryImageId, 'product_id' => $id]);
        $newCover = $stmt->fetchColumn();

        if ($newCover !== false) {
            $oldCover = $product['image_path'] ?? null;
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE products SET image_path = :new_cover WHERE id = :id')
                ->execute(['new_cover' => $newCover, 'id' => $id]);
            $pdo->prepare('DELETE FROM product_gallery_images WHERE id = :gallery_id')
                ->execute(['gallery_id' => $galleryImageId]);
            if (!empty($oldCover)) {
                $pdo->prepare('INSERT INTO product_gallery_images (product_id, image_path, sort_order) VALUES (:product_id, :image_path, :sort_order)')
                    ->execute(['product_id' => $id, 'image_path' => $oldCover, 'sort_order' => next_gallery_sort_order($pdo, $id)]);
            }
            $pdo->commit();
        }
        redirect('/admin/product-form.php?id=' . $id);
    }

    if ($galleryAction === 'gallery_upload') {
        try {
            $uploaded = upload_product_image($_FILES['gallery_image'] ?? []);
            if ($uploaded === null) {
                $errors[] = 'Seleziona un file da caricare.';
            } else {
                $pdo->prepare('INSERT INTO product_gallery_images (product_id, image_path, sort_order) VALUES (:product_id, :image_path, :sort_order)')
                    ->execute(['product_id' => $id, 'image_path' => $uploaded, 'sort_order' => next_gallery_sort_order($pdo, $id)]);
                redirect('/admin/product-form.php?id=' . $id);
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

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
    'product_type' => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    csrf_verify();

    $formData['category'] = $_POST['category'] ?? '';
    $formData['brand'] = trim($_POST['brand'] ?? '');
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['variants_input'] = trim($_POST['variants_input'] ?? '');
    $formData['variants_group2_label'] = trim($_POST['variants_group2_label'] ?? '');
    $formData['variants_group2_input'] = trim($_POST['variants_group2_input'] ?? '');
    $formData['stock_quantity'] = (int)($_POST['stock_quantity'] ?? 0);
    $formData['orderable_quantity'] = (int)($_POST['orderable_quantity'] ?? 0);
    $priceInput = trim($_POST['price'] ?? '');
    $formData['price'] = $priceInput === '' ? null : (float)str_replace(',', '.', $priceInput);
    $formData['description'] = trim($_POST['description'] ?? '');
    $productTypeInput = $_POST['product_type'] ?? '';
    $formData['product_type'] = array_key_exists($productTypeInput, PRODUCT_TYPES) ? $productTypeInput : null;

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
        $variantsJson = encode_variants_from_input(
            $formData['variants_input'],
            $formData['variants_group2_label'],
            $formData['variants_group2_input']
        );

        if ($id) {
            if ($newImagePath !== $product['image_path']) {
                delete_product_image($product['image_path']);
            }
            $stmt = $pdo->prepare(
                'UPDATE products SET category=:category, brand=:brand, name=:name, variants=:variants,
                 image_path=:image_path, stock_quantity=:stock_quantity, orderable_quantity=:orderable_quantity,
                 price=:price, description=:description, product_type=:product_type WHERE id=:id'
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
                'product_type' => $formData['product_type'],
                'id' => $id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO products (category, brand, name, variants, image_path, stock_quantity, orderable_quantity, price, description, product_type)
                 VALUES (:category, :brand, :name, :variants, :image_path, :stock_quantity, :orderable_quantity, :price, :description, :product_type)'
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
                'product_type' => $formData['product_type'],
            ]);
        }

        redirect('/admin/dashboard.php');
    }
}

if (isset($formData['variants_group2_label'])) {
    $variantsInputValue = $formData['variants_input'];
    $variantsGroup2LabelValue = $formData['variants_group2_label'];
    $variantsGroup2InputValue = $formData['variants_group2_input'];
} else {
    $decodedVariantsForDisplay = decode_variants($formData['variants'] ?? null);
    if (is_variant_groups($decodedVariantsForDisplay)) {
        $groupKeys = array_keys($decodedVariantsForDisplay);
        $variantsInputValue = implode(', ', $decodedVariantsForDisplay[$groupKeys[0]] ?? []);
        $variantsGroup2LabelValue = $groupKeys[1] ?? '';
        $variantsGroup2InputValue = implode(', ', $decodedVariantsForDisplay[$groupKeys[1]] ?? []);
    } else {
        $variantsInputValue = implode(', ', $decodedVariantsForDisplay);
        $variantsGroup2LabelValue = '';
        $variantsGroup2InputValue = '';
    }
}

$galleryImages = $id ? get_product_gallery_rows($pdo, $id) : [];

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
    <label for="product_type">Tipo prodotto (opzionale, per il filtro sul sito)</label>
    <select id="product_type" name="product_type">
      <option value="">Nessuno / non specificato</option>
      <?php foreach (PRODUCT_TYPES as $typeSlug => $typeLabel): ?>
        <option value="<?= h($typeSlug) ?>" <?= $formData['product_type'] === $typeSlug ? 'selected' : '' ?>><?= h($typeLabel) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-row">
    <label for="variants_input">Colori / varianti (separati da virgola)</label>
    <input type="text" id="variants_input" name="variants_input" placeholder="Rosso, Blu, Taglia M" value="<?= h($variantsInputValue) ?>">
  </div>

  <details class="admin-details"<?= $variantsGroup2LabelValue !== '' ? ' open' : '' ?>>
    <summary>Aggiungi una seconda variante obbligatoria (es. modello telefono) &mdash; solo per prodotti che ne hanno bisogno</summary>
    <div class="form-row form-row-split">
      <div>
        <label for="variants_group2_label">Nome secondo gruppo di varianti</label>
        <input type="text" id="variants_group2_label" name="variants_group2_label" placeholder="Modello iPhone" value="<?= h($variantsGroup2LabelValue) ?>">
      </div>
      <div>
        <label for="variants_group2_input">Valori secondo gruppo (separati da virgola)</label>
        <input type="text" id="variants_group2_input" name="variants_group2_input" placeholder="iPhone 11, iPhone 12, ..." value="<?= h($variantsGroup2InputValue) ?>">
        <button type="button" id="fillIphoneModels" class="btn btn-small btn-fill-models">Compila con tutti i modelli iPhone (11 &rarr; 17 Pro Max)</button>
      </div>
    </div>
    <p class="field-hint">Se compili anche il secondo gruppo, sul sito il cliente dovrà scegliere obbligatoriamente sia il colore/prima variante sia il valore del secondo gruppo prima di poter aggiungere il prodotto al carrello. Lascia questa sezione chiusa/vuota per tutti gli altri prodotti.</p>
  </details>

  <div class="form-row">
    <label for="image">Immagine di copertina<?= $id ? ' (la foto principale mostrata nel catalogo)' : '' ?></label>
    <?php if (!empty($formData['image_path'])): ?>
      <img src="<?= h(url($formData['image_path'])) ?>" alt="" class="current-image-preview">
    <?php endif; ?>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
    <?php if ($id): ?>
      <p class="field-hint">Carica un file qui solo per sostituire la copertina attuale. Per aggiungere altre foto o cambiare quale usare come copertina, usa la Galleria foto qui sotto.</p>
    <?php endif; ?>
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

<?php if ($id): ?>
<section class="admin-gallery-section">
  <h2>Galleria foto</h2>
  <p class="field-hint">La copertina attuale è la prima foto qui sotto. Imposta un'altra foto come copertina, eliminane alcune o caricane di nuove.</p>

  <div class="gallery-grid">
    <?php if (!empty($formData['image_path'])): ?>
      <figure class="gallery-item gallery-item-cover">
        <img src="<?= h(url($formData['image_path'])) ?>" alt="Copertina attuale">
        <figcaption>Copertina</figcaption>
      </figure>
    <?php endif; ?>

    <?php foreach ($galleryImages as $galleryImage): ?>
      <figure class="gallery-item">
        <img src="<?= h(url($galleryImage['image_path'])) ?>" alt="">
        <figcaption class="gallery-item-actions">
          <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="gallery_set_cover">
            <input type="hidden" name="gallery_image_id" value="<?= (int)$galleryImage['id'] ?>">
            <button type="submit" class="btn btn-small">Imposta come copertina</button>
          </form>
          <form method="post" class="inline-form" onsubmit="return confirm('Rimuovere questa foto dalla galleria?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="gallery_delete">
            <input type="hidden" name="gallery_image_id" value="<?= (int)$galleryImage['id'] ?>">
            <button type="submit" class="btn btn-small btn-danger">Elimina</button>
          </form>
        </figcaption>
      </figure>
    <?php endforeach; ?>
  </div>

  <?php if (empty($galleryImages)): ?>
    <p class="empty-state">Nessuna foto aggiuntiva oltre alla copertina.</p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="gallery-upload-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="gallery_upload">
    <label for="gallery_image">Aggiungi una foto alla galleria</label>
    <div class="gallery-upload-row">
      <input type="file" id="gallery_image" name="gallery_image" accept="image/jpeg,image/png,image/webp" required>
      <button type="submit" class="btn btn-small">Carica</button>
    </div>
  </form>
</section>
<?php endif; ?>

<script>
document.getElementById('fillIphoneModels').addEventListener('click', function () {
  var models = [
    'iPhone 11', 'iPhone 11 Pro', 'iPhone 11 Pro Max',
    'iPhone 12 mini', 'iPhone 12', 'iPhone 12 Pro', 'iPhone 12 Pro Max',
    'iPhone 13 mini', 'iPhone 13', 'iPhone 13 Pro', 'iPhone 13 Pro Max',
    'iPhone 14', 'iPhone 14 Plus', 'iPhone 14 Pro', 'iPhone 14 Pro Max',
    'iPhone 15', 'iPhone 15 Plus', 'iPhone 15 Pro', 'iPhone 15 Pro Max',
    'iPhone 16', 'iPhone 16 Plus', 'iPhone 16 Pro', 'iPhone 16 Pro Max', 'iPhone 16e',
    'iPhone 17', 'iPhone 17 Air', 'iPhone 17 Pro', 'iPhone 17 Pro Max'
  ];
  document.getElementById('variants_group2_input').value = models.join(', ');
  var labelInput = document.getElementById('variants_group2_label');
  if (!labelInput.value.trim()) labelInput.value = 'Modello iPhone';
});
</script>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
