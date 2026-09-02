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
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='48' fill='%23c8a862'/><text x='50' y='66' font-size='48' font-family='Georgia,serif' fill='%23fff' text-anchor='middle'>B</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="admin-body">
<header class="admin-header">
  <div class="admin-header-inner">
    <a href="<?= url('admin/dashboard.php') ?>" class="brand">
      <img
        src="<?= url('assets/images/beautydrops-logo.png') ?>"
        class="brand-logo brand-logo-admin"
        width="1942"
        height="809"
        alt="Beauty Drops"
      >
      <span class="brand-admin-label">Admin</span>
    </a>
    <nav class="admin-nav">
      <a href="<?= url('admin/dashboard.php') ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Prodotti</a>
      <a href="<?= url('admin/offers.php') ?>" class="<?= $activeNav === 'offers' ? 'active' : '' ?>">Offerte</a>
      <a href="<?= url('index.php') ?>" target="_blank" rel="noopener">Vedi sito</a>
      <a href="<?= url('admin/logout.php') ?>" class="logout-link">Esci</a>
    </nav>
  </div>
</header>
<main class="admin-main">
  <div class="container">
