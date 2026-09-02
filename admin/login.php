<?php
require_once __DIR__ . '/../includes/functions.php';

if (is_admin_logged_in()) {
    redirect('/admin/dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = get_db()->prepare('SELECT * FROM admins WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        redirect('/admin/dashboard.php');
    }

    $error = 'Email o password non corretti.';
}

$pageTitle = 'Accesso Admin · ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="admin-body login-body">
  <div class="login-card">
    <a href="/index.php" class="brand login-brand">
      <span class="brand-mark">BD</span>
      <span class="brand-word">BeautyDrops</span>
    </a>
    <h1>Accesso Admin</h1>
    <?php if ($error): ?>
      <p class="form-error"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <button type="submit" class="btn btn-primary btn-block">Accedi</button>
    </form>
    <a href="/index.php" class="back-link">&larr; Torna al sito</a>
  </div>
</body>
</html>
