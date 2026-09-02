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
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='48' fill='%23c8a862'/><text x='50' y='66' font-size='48' font-family='Georgia,serif' fill='%23fff' text-anchor='middle'>B</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="admin-body login-body">
  <div class="login-blob login-blob-1" aria-hidden="true"></div>
  <div class="login-blob login-blob-2" aria-hidden="true"></div>
  <div class="login-card">
    <a href="<?= url('index.php') ?>" class="brand login-brand">
      <img
        src="<?= url('assets/images/beautydrops-logo.png') ?>"
        class="brand-logo login-logo"
        width="1942"
        height="809"
        alt="Beauty Drops"
      >
    </a>
    <h1>Accesso Admin</h1>
    <?php if ($error): ?>
      <p class="form-error"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Accedi</button>
    </form>
    <a href="<?= url('index.php') ?>" class="back-link">&larr; Torna al sito</a>
  </div>
</body>
</html>
