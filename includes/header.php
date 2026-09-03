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
    <button type="button" class="cart-button" id="cartButton" aria-label="Carrello">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="21" r="1.4"/><circle cx="19" cy="21" r="1.4"/><path d="M2.5 3h2.4l2.4 12.2a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 2-1.6L21.5 7H6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span class="cart-badge" id="cartBadge" hidden>0</span>
    </button>
    <a href="<?= url('admin/login.php') ?>" class="admin-access-btn">Accesso Admin</a>
    <button class="nav-toggle" id="navToggle" aria-label="Apri menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<div class="cart-popup-overlay" id="cartPopupOverlay" hidden>
  <div class="cart-popup" role="dialog" aria-modal="true" aria-label="Carrello">
    <div class="cart-popup-header">
      <h3>Il tuo carrello</h3>
      <button type="button" class="cart-popup-close" id="cartPopupClose" aria-label="Chiudi">&times;</button>
    </div>
    <div class="cart-popup-items" id="cartPopupItems"></div>
    <div class="cart-popup-footer">
      <div class="cart-popup-total">
        <span>Totale</span>
        <span id="cartPopupTotal">€ 0,00</span>
      </div>
      <a href="<?= url('cart.php') ?>" class="btn btn-primary btn-block">Vai al carrello</a>
    </div>
  </div>
</div>
