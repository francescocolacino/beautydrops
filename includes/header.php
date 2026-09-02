<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? SITE_NAME;
$activeSlug = $activeSlug ?? null;
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="container site-header-inner">
    <a href="/index.php" class="brand">
      <span class="brand-mark">BD</span>
      <span class="brand-word">BeautyDrops</span>
    </a>
    <nav class="main-nav">
      <?php foreach (CATEGORIES as $slug => $label): ?>
        <a href="/category.php?slug=<?= h($slug) ?>" class="<?= $activeSlug === $slug ? 'active' : '' ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <a href="/admin/login.php" class="admin-access-btn">Accesso Admin</a>
    <button class="nav-toggle" id="navToggle" aria-label="Apri menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>
