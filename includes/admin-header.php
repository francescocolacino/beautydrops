<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_admin();

$pageTitle = $pageTitle ?? 'Admin · ' . SITE_NAME;
$activeNav = $activeNav ?? '';
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
<body class="admin-body">
<header class="admin-header">
  <div class="admin-header-inner">
    <a href="/admin/dashboard.php" class="brand">
      <span class="brand-mark">BD</span>
      <span class="brand-word">BeautyDrops Admin</span>
    </a>
    <nav class="admin-nav">
      <a href="/admin/dashboard.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Prodotti</a>
      <a href="/admin/offers.php" class="<?= $activeNav === 'offers' ? 'active' : '' ?>">Offerte</a>
      <a href="/index.php" target="_blank" rel="noopener">Vedi sito</a>
      <a href="/admin/logout.php" class="logout-link">Esci</a>
    </nav>
  </div>
</header>
<main class="admin-main">
  <div class="container">
