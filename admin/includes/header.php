<?php $__bodyClass = isset($bodyClass) ? e($bodyClass) : 'admin-dashboard'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? e($pageTitle).' - ' : '' ?>Admin - Sambal House 🌶️</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/assets/style.css?v=<?=@filemtime(__DIR__.'/../../public/assets/style.css')?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="<?=$__bodyClass?>">
<nav class="navbar">
  <div class="nav-left">
    <a href="<?=ADMIN_URL?>/index.php"><b>Sambal House · Admin</b></a>
  </div>
  <div class="nav-center">
    <span class="nav-box"><i class="fas fa-user-shield"></i> &nbsp;Hello, <?=e($_SESSION['admin_name'] ?? 'Admin')?></span>
  </div>
  <div class="nav-right">
    <a href="<?=ADMIN_URL?>/index.php" class="nav-box"><i class="fas fa-tachometer-alt"></i> &nbsp;Dashboard</a>
    <a href="<?=ADMIN_URL?>/products.php" class="nav-box"><i class="fas fa-box"></i> &nbsp;Products</a>
    <a href="<?=ADMIN_URL?>/orders.php" class="nav-box"><i class="fas fa-shopping-cart"></i> &nbsp;Orders</a>
    <a href="<?=BASE_URL?>/" class="nav-box" target="_blank"><i class="fas fa-external-link-alt"></i> &nbsp;View Shop</a>
    <a href="<?=ADMIN_URL?>/logout.php" class="nav-box"><i class="fas fa-sign-out-alt"></i> &nbsp;Logout</a>
  </div>
</nav>
