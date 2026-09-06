<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

if(!is_logged_in()) redirect(BASE_URL.'/login.php');

$uid = (int)current_user_id();

$favs = $mysqli->query("
  SELECT p.id, p.name, p.slug, p.price, p.image, p.stock, uf.created_at AS saved_at
  FROM user_favourites uf
  JOIN products p ON p.id = uf.product_id
  WHERE uf.user_id = $uid AND p.is_active = 1
  ORDER BY uf.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'My Favourites';
require_once __DIR__.'/includes/header.php';
?>
<style>
.favs-wrap{max-width:980px;margin:0 auto;padding:0 16px 60px}
.favs-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:24px}
.fav-card{background:var(--card);border:1px solid rgba(255,255,255,.06);border-radius:14px;overflow:hidden;position:relative;transition:transform .18s,border-color .18s,box-shadow .18s;display:flex;flex-direction:column}
.fav-card:hover{transform:translateY(-4px);border-color:rgba(255,56,56,.3);box-shadow:0 10px 28px rgba(0,0,0,.4)}
.fav-card-img{height:210px;display:flex;align-items:center;justify-content:center;padding:12px;background:rgba(255,255,255,.02);overflow:hidden}
.fav-card-img img{width:100%;height:100%;object-fit:contain;transition:transform .3s}
.fav-card:hover .fav-card-img img{transform:scale(1.05)}
.fav-card-body{padding:14px 16px 16px;border-top:1px solid rgba(255,255,255,.05);flex:1;display:flex;flex-direction:column;gap:6px}
.fav-card-name{font-size:.88rem;font-weight:600;color:var(--text);line-height:1.4}
.fav-card-price{font-size:1.1rem;font-weight:800;color:var(--accent)}
.fav-card-saved{font-size:.72rem;color:rgba(255,255,255,.3);margin-top:2px}
.fav-card-actions{display:flex;gap:8px;margin-top:10px}
.fav-btn-view{flex:1;padding:8px;border-radius:8px;background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#2b090a;font-size:.82rem;font-weight:700;text-decoration:none;text-align:center;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;transition:opacity .15s}
.fav-btn-view:hover{opacity:.88}
.fav-btn-remove{padding:8px 10px;border-radius:8px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#f87171;font-size:.82rem;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:4px}
.fav-btn-remove:hover{background:rgba(239,68,68,.2)}
.fav-badge{position:absolute;top:10px;left:10px}
.favs-empty{text-align:center;padding:70px 20px;color:var(--muted)}
.favs-empty i{font-size:3rem;margin-bottom:16px;display:block;color:rgba(255,255,255,.15)}
.favs-empty h3{color:var(--text);margin-bottom:8px}
@media(max-width:768px){.favs-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:420px){.favs-grid{grid-template-columns:1fr}}
</style>

<header class="page-header">
  <div class="page-header-inner">
    <div class="ph-icon"><i class="fas fa-heart" style="color:#f87171"></i></div>
    <div><h1>My Favourites</h1><p>Products you've saved</p></div>
  </div>
</header>

<div class="container">
<div class="favs-wrap">

<?php if(!$favs): ?>
<div class="favs-empty">
  <i class="far fa-heart"></i>
  <h3>No favourites yet</h3>
  <p style="margin-bottom:20px">Tap the heart on any product to save it here.</p>
  <a href="<?=BASE_URL?>/index.php#shop" class="btn btn-primary">Browse Products</a>
</div>
<?php else: ?>

<div class="favs-grid" id="favsGrid">
<?php foreach($favs as $p):
  $stock = (int)$p['stock'];
  $badgeClass = $stock > 10 ? 'green' : ($stock > 0 ? 'low' : 'oos');
  $badgeText  = $stock > 10 ? 'In Stock' : ($stock > 0 ? 'Low Stock' : 'Out of Stock');
?>
<div class="fav-card" id="fav-<?=(int)$p['id']?>">
  <div class="fav-badge"><span class="badge <?=$badgeClass?>" style="font-size:.72rem;padding:3px 8px"><?=$badgeText?></span></div>
  <div class="fav-card-img">
    <img src="<?=e($p['image'])?>" alt="<?=e($p['name'])?>">
  </div>
  <div class="fav-card-body">
    <div class="fav-card-name"><?=e($p['name'])?></div>
    <div class="fav-card-price">RM <?=number_format($p['price'],2)?></div>
    <div class="fav-card-saved"><i class="fas fa-heart" style="color:#f87171;margin-right:4px"></i>Saved <?=date('d M Y', strtotime($p['saved_at']))?></div>
    <div class="fav-card-actions">
      <a href="<?=BASE_URL?>/product.php?slug=<?=urlencode($p['slug'])?>" class="fav-btn-view">
        <i class="fas fa-eye"></i> View
      </a>
      <button class="fav-btn-remove" onclick="removeFav(<?=(int)$p['id']?>,this)" title="Remove from Favourites">
        <i class="fas fa-heart-crack"></i>
      </button>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<p style="text-align:center;color:rgba(255,255,255,.25);font-size:.8rem;margin-top:24px">
  <?=count($favs)?> saved product<?=count($favs)!==1?'s':''?>
</p>
<?php endif; ?>

</div>
</div>

<script>
var favToggleUrl = <?=json_encode(BASE_URL.'/favourite_toggle.php')?>;
function removeFav(productId, btn){
  fetch(favToggleUrl, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'product_id='+productId
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    if(data.error) return;
    var card = document.getElementById('fav-'+productId);
    if(card){
      card.style.transition = 'opacity .3s, transform .3s';
      card.style.opacity = '0';
      card.style.transform = 'scale(.92)';
      setTimeout(function(){
        card.remove();
        var grid = document.getElementById('favsGrid');
        if(grid && grid.children.length === 0) location.reload();
      }, 300);
    }
    var badge = document.getElementById('navFavCount');
    if(badge) badge.textContent = data.count > 0 ? data.count : '';
  });
}
</script>

<?php require_once __DIR__.'/includes/footer.php'; ?>
