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
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='48' fill='%23c8a862'/><text x='50' y='66' font-size='48' font-family='Georgia,serif' fill='%23fff' text-anchor='middle'>B</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<header class="site-header" id="siteHeader">
  <div class="container site-header-inner">
    <a href="<?= url('index.php') ?>" class="brand">
      <img
        src="<?= url('assets/images/beautydrops-logo.png') ?>"
        class="brand-logo brand-logo-header"
        width="1942"
        height="809"
        alt="Beauty Drops"
      >
    </a>
    <nav class="main-nav" id="mainNav">
      <?php foreach (CATEGORIES as $navSlug => $navLabel): ?>
        <a href="<?= url('category.php?slug=' . $navSlug) ?>" class="<?= $activeSlug === $navSlug ? 'active' : '' ?>"><?= h($navLabel) ?></a>
      <?php endforeach; ?>
      <a href="<?= url('admin/login.php') ?>" class="admin-access-btn nav-only">Accesso Admin</a>
    </nav>
    <a href="<?= url('admin/login.php') ?>" class="admin-access-btn">Accesso Admin</a>
    <button class="nav-toggle" id="navToggle" aria-label="Apri menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>
