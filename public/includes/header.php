<?php
$_hCartId = get_or_create_cart($mysqli);
$_hTotals = cart_totals($mysqli, $_hCartId);
$_hFavCount = 0;
if(is_logged_in()){
  $fuid2 = (int)current_user_id();
  $_hFavCount = (int)$mysqli->query("SELECT COUNT(*) AS c FROM user_favourites WHERE user_id=$fuid2")->fetch_assoc()['c'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sambal House — handcrafted Malaysian chili pastes, made fresh in small batches. Order original, extra pedas and ikan bilis sambal online.">
  <title><?= isset($pageTitle) ? e($pageTitle).' - ' : '' ?>Sambal House 🌶️</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/assets/style.css?v=<?=@filemtime(__DIR__.'/../assets/style.css')?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<nav class="navbar" id="main-nav">
  <div class="nav-left">
    <a href="<?=BASE_URL?>/index.php"><b>Sambal House</b></a>
  </div>
  <div class="nav-center">
    <?php if(is_logged_in()): ?>
      <a href="<?=BASE_URL?>/profile.php" class="nav-box"><i class="fas fa-user-circle"></i> &nbsp;Hi, <?=e($_SESSION['user_name'])?></a>
    <?php else: ?>
      <a href="<?=BASE_URL?>/login.php"    class="nav-box nav-login-btn"><i class="fas fa-right-to-bracket"></i> &nbsp;Login</a>
      <a href="<?=BASE_URL?>/register.php" class="nav-box nav-register-btn"><i class="fas fa-user-plus"></i> &nbsp;Register</a>
    <?php endif; ?>
  </div>
  <div class="nav-right">
    <a href="<?=BASE_URL?>/index.php#shop" class="nav-box"><i class="fas fa-store"></i> &nbsp;Shop</a>
    <a href="<?=BASE_URL?>/team.php"       class="nav-box"><i class="fas fa-users"></i> &nbsp;Our Team</a>
    <a href="<?=BASE_URL?>/cart.php"       class="nav-box"><i class="fas fa-cart-shopping"></i> &nbsp;Cart (<?=$_hTotals['items']?>)</a>
    <?php if(is_logged_in()): ?>
      <a href="<?=BASE_URL?>/favourites.php" class="nav-box" style="position:relative">
        <i class="fas fa-heart" style="color:#f87171"></i> &nbsp;Favourites
        <span id="navFavCount" style="position:absolute;top:-5px;right:-5px;background:#f87171;color:#fff;font-size:.6rem;font-weight:700;border-radius:99px;padding:1px 5px;min-width:16px;text-align:center;line-height:1.6;<?=$_hFavCount===0?'display:none':''?>"><?=$_hFavCount?></span>
      </a>
      <a href="<?=BASE_URL?>/orders.php"     class="nav-box"><i class="fas fa-receipt"></i> &nbsp;My Orders</a>
      <a href="<?=BASE_URL?>/logout.php"     class="nav-box"><i class="fas fa-right-from-bracket"></i> &nbsp;Logout</a>
    <?php endif; ?>
  </div>
  <button class="nav-hamburger" id="nav-hamburger" aria-label="Toggle menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</nav>
<div class="nav-mobile-menu" id="nav-mobile-menu">
  <?php if(is_logged_in()): ?>
    <a href="<?=BASE_URL?>/profile.php"    class="nav-box"><i class="fas fa-user-circle"></i> Hi, <?=e($_SESSION['user_name'])?></a>
  <?php else: ?>
    <a href="<?=BASE_URL?>/login.php"    class="nav-box nav-login-btn"><i class="fas fa-right-to-bracket"></i> Login</a>
    <a href="<?=BASE_URL?>/register.php" class="nav-box nav-register-btn"><i class="fas fa-user-plus"></i> Register</a>
  <?php endif; ?>
  <a href="<?=BASE_URL?>/index.php#shop" class="nav-box"><i class="fas fa-store"></i> Shop</a>
  <a href="<?=BASE_URL?>/team.php"       class="nav-box"><i class="fas fa-users"></i> Our Team</a>
  <a href="<?=BASE_URL?>/cart.php"       class="nav-box"><i class="fas fa-cart-shopping"></i> Cart (<?=$_hTotals['items']?>)</a>
  <?php if(is_logged_in()): ?>
    <a href="<?=BASE_URL?>/favourites.php" class="nav-box"><i class="fas fa-heart" style="color:#f87171"></i> Favourites<?php if($_hFavCount>0): ?> (<?=$_hFavCount?>)<?php endif; ?></a>
    <a href="<?=BASE_URL?>/orders.php"     class="nav-box"><i class="fas fa-receipt"></i> My Orders</a>
    <a href="<?=BASE_URL?>/logout.php"     class="nav-box"><i class="fas fa-right-from-bracket"></i> Logout</a>
  <?php endif; ?>
</div>
<script>
(function(){
  var btn  = document.getElementById('nav-hamburger');
  var menu = document.getElementById('nav-mobile-menu');
  btn.addEventListener('click', function(){
    var open = menu.classList.toggle('open');
    btn.setAttribute('aria-expanded', open);
    btn.classList.toggle('active', open);
  });
})();
</script>
<main>
