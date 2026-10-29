<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

/*
 * Import dei prezzi d'acquisto da un file JSON preparato a partire dai listini
 * fornitore: un elenco di {id, purchase_price}, con eventuali campi descrittivi
 * (codice, brand, nome) ignorati. I costi non vanno mai versionati nella
 * repository, che è pubblica: arrivano solo da qui e restano nel database.
 */

$pdo = get_db();
$errors = [];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $overwrite = isset($_POST['overwrite']);
    $file = $_FILES['prices_file'] ?? null;

    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Seleziona il file JSON dei prezzi d\'acquisto.';
    } else {
        try {
            $rows = json_decode((string)file_get_contents($file['tmp_name']), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $rows = null;
        }
        if (!is_array($rows) || !array_is_list($rows)) {
            $errors[] = 'Il file non contiene un elenco JSON valido.';
        } else {
            foreach ($rows as $index => $row) {
                $price = $row['purchase_price'] ?? null;
                if (!is_int($row['id'] ?? null) || !is_numeric($price) || (float)$price <= 0) {
                    $errors[] = 'Riga ' . ($index + 1) . ' non valida: servono "id" e "purchase_price" maggiore di zero.';
                }
            }
        }

        if (empty($errors)) {
            $sql = 'UPDATE products SET purchase_price = :purchase_price WHERE id = :id';
            if (!$overwrite) {
                $sql .= ' AND purchase_price IS NULL';
            }
            $update = $pdo->prepare($sql);
            $exists = $pdo->prepare('SELECT 1 FROM products WHERE id = :id');

            $result = ['updated' => 0, 'kept' => 0, 'missing' => 0];
            $pdo->beginTransaction();
            try {
                foreach ($rows as $row) {
                    $update->execute(['purchase_price' => (float)$row['purchase_price'], 'id' => $row['id']]);
                    if ($update->rowCount() > 0) {
                        $result['updated']++;
                        continue;
                    }
                    $exists->execute(['id' => $row['id']]);
                    $exists->fetchColumn() ? $result['kept']++ : $result['missing']++;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                $result = null;
                $errors[] = 'Import non riuscito, nessun prezzo è stato modificato. Riprova.';
            }
        }
    }
}

$pageTitle = 'Prezzi d\'acquisto · Admin';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-toolbar">
  <h1>Importa prezzi d'acquisto</h1>
  <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-small">&larr; Torna all'elenco</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="form-error">
    <ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<?php if ($result !== null): ?>
  <div class="form-success">
    <p>Prezzi d'acquisto impostati: <strong><?= $result['updated'] ?></strong>.</p>
    <?php if ($result['kept'] > 0): ?><p>Già impostati e lasciati invariati: <?= $result['kept'] ?>.</p><?php endif; ?>
    <?php if ($result['missing'] > 0): ?><p>Prodotti non trovati (forse eliminati): <?= $result['missing'] ?>.</p><?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="admin-form">
  <?= csrf_field() ?>
  <p class="field-hint">
    Carica il file JSON con i prezzi del fornitore in USD alla MOQ più bassa (un elenco di <code>{"id", "purchase_price"}</code>).
    Nell'elenco prodotti verranno mostrati con <?= format_usd(SHIPPING_COST_USD) ?> di spedizione a pezzo e il guadagno netto in euro.
    I prezzi restano solo nel database, visibili esclusivamente in admin.
  </p>

  <div class="form-row">
    <label for="prices_file">File prezzi (.json)</label>
    <input type="file" id="prices_file" name="prices_file" accept=".json,application/json" required>
  </div>

  <div class="form-row">
    <label class="checkbox-label">
      <input type="checkbox" name="overwrite">
      Sovrascrivi anche i prezzi d'acquisto già impostati
    </label>
  </div>

  <button type="submit" class="btn btn-primary">Importa</button>
</form>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
