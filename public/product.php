<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

$slug = $_GET['slug'] ?? '';
$stmt = $mysqli->prepare("SELECT id, name, description, price, stock, image, origin_location FROM products WHERE slug=? AND is_active=1");
$stmt->bind_param('s', $slug);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
if(!$product){ http_response_code(404); exit('Product not found'); }
if (is_logged_in()) track_activity('Viewing: '.$product['name']);

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!is_logged_in()){
    set_flash('error', 'Login Required', 'Please sign in to add items to your cart.');
    redirect(BASE_URL.'/login.php');
  }
  if((int)$product['stock'] <= 0) redirect(BASE_URL.'/product.php?slug='.urlencode($slug));

  $qty    = max(1, (int)($_POST['qty'] ?? 1));
  if($qty > $product['stock']) $qty = (int)$product['stock'];
  $cartId = get_or_create_cart($mysqli);

  $q = $mysqli->prepare("SELECT id, qty FROM cart_items WHERE cart_id=? AND product_id=?");
  $q->bind_param('ii', $cartId, $product['id']);
  $q->execute();
  $existing = $q->get_result()->fetch_assoc();

  if($existing){
    $newQty = min($existing['qty'] + $qty, (int)$product['stock']);
    $u = $mysqli->prepare("UPDATE cart_items SET qty=? WHERE id=?");
    $u->bind_param('ii', $newQty, $existing['id']);
    $u->execute();
  } else {
    $i = $mysqli->prepare("INSERT INTO cart_items (cart_id, product_id, qty) VALUES (?, ?, ?)");
    $i->bind_param('iii', $cartId, $product['id'], $qty);
    $i->execute();
  }
  set_flash('success', 'Added to Cart!', e($product['name']).' was added successfully.');
  redirect(BASE_URL.'/cart.php');
}

// Fetch review summary
$rSum = $mysqli->prepare("SELECT COUNT(*) AS total, AVG(rating) AS avg_rating,
  SUM(rating=5) AS s5, SUM(rating=4) AS s4, SUM(rating=3) AS s3, SUM(rating=2) AS s2, SUM(rating=1) AS s1
  FROM product_reviews WHERE product_id=?");
$rSum->bind_param('i', $product['id']);
$rSum->execute();
$rSummary = $rSum->get_result()->fetch_assoc();

// Fetch individual reviews (join users for masked name)
$rList = $mysqli->prepare("
  SELECT pr.rating, pr.review_text, pr.created_at, u.name AS full_name
  FROM product_reviews pr
  JOIN users u ON u.id = pr.user_id
  WHERE pr.product_id = ?
  ORDER BY pr.created_at DESC
  LIMIT 20
");
$rList->bind_param('i', $product['id']);
$rList->execute();
$reviews = $rList->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if current user has favourited this product
$isFav = false;
if(is_logged_in()){
  $fq = $mysqli->prepare("SELECT id FROM user_favourites WHERE user_id=? AND product_id=?");
  $uid_fav = (int)current_user_id();
  $fq->bind_param('ii', $uid_fav, $product['id']);
  $fq->execute();
  $isFav = (bool)$fq->get_result()->fetch_assoc();
}

// Fetch "You May Also Like" products
$ymal = $mysqli->prepare("
  SELECT id, name, price, slug, image, stock
  FROM products
  WHERE is_active=1 AND id != ?
  ORDER BY RAND()
  LIMIT 8
");
$ymal->bind_param('i', $product['id']);
$ymal->execute();
$ymalProducts = $ymal->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = $product['name'];
$stock     = (int)$product['stock'];
$total     = (int)($rSummary['total'] ?? 0);
$avg       = round((float)($rSummary['avg_rating'] ?? 0), 1);
$badgeClass = $stock > 10 ? 'green' : ($stock > 0 ? 'low' : 'oos');
$badgeText  = $stock > 10 ? "In stock: $stock" : ($stock > 0 ? "Low stock: $stock" : "Out of stock");
require_once __DIR__.'/includes/header.php';
?>
<style>
/* ===== PRODUCT DETAIL REDESIGN ===== */
.pd-wrap{max-width:980px;margin:0 auto;padding:24px 16px 60px}
.pd-breadcrumb{display:flex;align-items:center;gap:8px;font-size:.8rem;color:rgba(255,255,255,.35);margin-bottom:20px;flex-wrap:wrap}
.pd-breadcrumb a{color:rgba(255,255,255,.45);text-decoration:none}.pd-breadcrumb a:hover{color:var(--accent)}
.pd-breadcrumb .sep{color:rgba(255,255,255,.2)}
.pd-card{display:grid;grid-template-columns:420px 1fr;gap:0;background:var(--card);border:1px solid rgba(255,255,255,.07);border-radius:20px;overflow:hidden;box-shadow:0 16px 48px rgba(0,0,0,.5)}

/* Image column */
.pd-img-col{background:radial-gradient(ellipse at center,rgba(200,50,20,.15) 0%,rgba(10,4,5,.98) 75%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:36px 28px;min-height:460px;border-right:1px solid rgba(255,255,255,.05);position:relative}
.pd-img{max-width:100%;max-height:380px;object-fit:contain;border-radius:10px;filter:drop-shadow(0 12px 32px rgba(200,60,20,.3));transition:transform .3s,filter .3s;cursor:zoom-in}
.pd-img:hover{transform:scale(1.03);filter:drop-shadow(0 18px 44px rgba(200,60,20,.48))}
.pd-zoom-hint{font-size:.73rem;color:rgba(255,255,255,.3);margin-top:10px;display:flex;align-items:center;gap:5px}

/* Info column */
.pd-info-col{display:flex;flex-direction:column;padding:32px 36px;gap:0}
.pd-title{font-size:1.45rem;font-weight:700;color:var(--text);line-height:1.3;margin:0 0 14px}

/* Rating row */
.pd-rating-row{display:flex;align-items:center;gap:10px;padding-bottom:16px;border-bottom:1px solid rgba(255,255,255,.06);flex-wrap:wrap}
.pd-stars-inline{color:#f59e0b;font-size:1rem;letter-spacing:1px}
.pd-avg-num{font-weight:700;color:#f59e0b;font-size:.95rem}
.pd-rating-sep{color:rgba(255,255,255,.2);font-size:.85rem}
.pd-rev-count{color:rgba(255,255,255,.4);font-size:.82rem;cursor:pointer;text-decoration:underline;text-underline-offset:2px}
.pd-rev-count:hover{color:var(--accent)}

/* Price box */
.pd-price-box{background:rgba(255,56,56,.06);border-left:3px solid var(--accent);padding:14px 16px;margin:16px 0;border-radius:0 10px 10px 0}
.pd-price-label{font-size:.75rem;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
.pd-price{font-size:2.2rem;font-weight:800;color:var(--accent);line-height:1}

/* Info rows */
.pd-info-rows{display:flex;flex-direction:column;gap:0;margin:4px 0 18px}
.pd-info-row{display:flex;align-items:center;gap:0;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.pd-info-row:last-child{border-bottom:none}
.pd-row-label{min-width:90px;font-size:.82rem;color:rgba(255,255,255,.35);flex-shrink:0}
.pd-row-val{font-size:.88rem;color:var(--muted)}
.pd-desc{font-size:.88rem;color:rgba(255,255,255,.55);line-height:1.65;padding:10px 0 4px}

/* Qty + actions */
.pd-qty-row{display:flex;align-items:center;gap:12px;padding:10px 0}
.pd-qty-label{min-width:90px;font-size:.82rem;color:rgba(255,255,255,.35)}
.pd-actions{margin-top:auto;padding-top:16px;border-top:1px solid rgba(255,255,255,.06)}
.pd-add-btn{width:100%;padding:13px;font-size:1rem;font-weight:700;border-radius:10px;border:none;background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#2b090a;cursor:pointer;transition:all .2s;text-transform:uppercase;letter-spacing:.05em}
.pd-add-btn:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 8px 22px rgba(255,56,56,.4);filter:brightness(1.08)}
.pd-add-btn:disabled{opacity:.45;cursor:not-allowed;transform:none}

/* Favourite button */
.pd-fav-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.04);color:rgba(255,255,255,.5);font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s}
.pd-fav-btn:hover,.pd-fav-btn.active{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.4);color:#f87171}
.pd-fav-btn.active i{color:#f87171}
.pd-fav-btn i{font-size:1rem;transition:transform .2s}
.pd-fav-btn.active i{animation:heartPop .3s ease}
@keyframes heartPop{0%{transform:scale(1)}50%{transform:scale(1.4)}100%{transform:scale(1)}}

/* Login prompt */
.pd-login-box{border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:22px;text-align:center;margin-top:16px}
.pd-login-box p{font-size:.87rem;color:var(--muted);margin-bottom:14px}
.pd-login-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}

/* Responsive */
@media(max-width:768px){
  .pd-card{grid-template-columns:1fr}
  .pd-img-col{min-height:280px;border-right:none;border-bottom:1px solid rgba(255,255,255,.06);padding:24px 20px}
  .pd-info-col{padding:24px 20px}
  .pd-title{font-size:1.2rem}
  .pd-price{font-size:1.8rem}
}
</style>

<div class="container">
<div class="pd-wrap">

  <!-- Breadcrumb -->
  <div class="pd-breadcrumb">
    <a href="<?=BASE_URL?>/index.php">Home</a>
    <span class="sep">/</span>
    <a href="<?=BASE_URL?>/index.php#shop">Products</a>
    <span class="sep">/</span>
    <span><?=e($product['name'])?></span>
  </div>

  <!-- Main card -->
  <div class="pd-card">

    <!-- Image -->
    <div class="pd-img-col">
      <img src="<?=e($product['image'])?>" alt="<?=e($product['name'])?>" class="pd-img lightbox-trigger">
      <span class="pd-zoom-hint"><i class="fas fa-search-plus"></i> Click to zoom</span>
    </div>

    <!-- Lightbox (outside grid flow) -->
    <div id="lightbox" class="lightbox-overlay" role="dialog" aria-modal="true">
      <button class="lightbox-close" aria-label="Close">&times;</button>
      <img src="<?=e($product['image'])?>" alt="<?=e($product['name'])?>" class="lightbox-img">
    </div>

    <!-- Info -->
    <div class="pd-info-col">
      <h1 class="pd-title"><?=e($product['name'])?></h1>

      <!-- Rating summary -->
      <div class="pd-rating-row">
        <?php if($total > 0): ?>
          <span class="pd-avg-num"><?=number_format($avg,1)?></span>
          <span class="pd-stars-inline"><?=str_repeat('★', (int)round($avg)).str_repeat('☆', 5-(int)round($avg))?></span>
          <span class="pd-rating-sep">|</span>
          <a href="#reviews" class="pd-rev-count"><?=$total?> Rating<?=$total!==1?'s':''?></a>
        <?php else: ?>
          <span class="pd-stars-inline" style="color:rgba(255,255,255,.2)">☆☆☆☆☆</span>
          <span class="pd-rating-sep">|</span>
          <span style="color:rgba(255,255,255,.3);font-size:.82rem">No ratings yet</span>
        <?php endif; ?>
      </div>

      <!-- Favourite button -->
      <?php if(is_logged_in()): ?>
      <div style="margin:14px 0 2px">
        <button id="favBtn" onclick="toggleFav(<?=(int)$product['id']?>)"
          class="pd-fav-btn <?=$isFav?'active':''?>"
          title="<?=$isFav?'Remove from Favourites':'Add to Favourites'?>">
          <i class="<?=$isFav?'fas':'far'?> fa-heart"></i>
          <span id="favLabel"><?=$isFav?'Saved to Favourites':'Add to Favourites'?></span>
        </button>
      </div>
      <?php endif; ?>

      <!-- Price -->
      <div class="pd-price-box">
        <div class="pd-price-label">Price</div>
        <div class="pd-price">RM <?=number_format($product['price'],2)?></div>
      </div>

      <!-- Info rows -->
      <div class="pd-info-rows">
        <?php if(!empty($product['origin_location'])): ?>
        <div class="pd-info-row">
          <span class="pd-row-label"><i class="fas fa-map-marker-alt" style="color:var(--accent);margin-right:5px"></i>Origin</span>
          <span class="pd-row-val"><?=e($product['origin_location'])?></span>
        </div>
        <?php endif; ?>
        <div class="pd-info-row">
          <span class="pd-row-label"><i class="fas fa-box" style="color:rgba(255,255,255,.3);margin-right:5px"></i>Stock</span>
          <span class="pd-row-val"><span class="badge <?=$badgeClass?>"><?=$badgeText?></span></span>
        </div>
        <div class="pd-info-row" style="align-items:flex-start;padding-top:12px">
          <span class="pd-row-label" style="padding-top:2px"><i class="fas fa-align-left" style="color:rgba(255,255,255,.3);margin-right:5px"></i>Details</span>
          <span class="pd-desc"><?=nl2br(e($product['description']))?></span>
        </div>
      </div>

      <!-- Quantity + Add to Cart -->
      <?php if(is_logged_in()): ?>
      <form method="post">
        <div class="pd-qty-row">
          <span class="pd-qty-label"><i class="fas fa-cubes" style="color:rgba(255,255,255,.3);margin-right:5px"></i>Quantity</span>
          <div class="qty-stepper">
            <button type="button" class="qty-btn" id="qty-minus" <?=$stock<=0?'disabled':''?>>−</button>
            <input type="number" id="qty" name="qty" class="quantity-input"
                   min="1" max="<?=e($product['stock'])?>"
                   value="<?=$stock>0?1:0?>"
                   <?=$stock<=0?'disabled':''?> readonly>
            <button type="button" class="qty-btn" id="qty-plus" <?=$stock<=0?'disabled':''?>>+</button>
          </div>
        </div>
        <div class="pd-actions">
          <button class="pd-add-btn" type="submit" <?=$stock<=0?'disabled':''?>>
            <i class="fas fa-cart-plus" style="margin-right:7px"></i><?=$stock<=0?'Out of Stock':'Add to Cart'?>
          </button>
        </div>
      </form>
      <?php else: ?>
      <div class="pd-login-box">
        <p>Sign in to add this item to your cart.</p>
        <div class="pd-login-actions">
          <a href="<?=BASE_URL?>/login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Sign In</a>
          <a href="<?=BASE_URL?>/register.php" class="btn btn-secondary"><i class="fas fa-user-plus"></i> Register</a>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /pd-info-col -->
  </div><!-- /pd-card -->

</div><!-- /pd-wrap -->
</div><!-- /container -->
<?php
function render_stars(float $r, bool $small=false): string {
  $s = $small ? 'font-size:.95rem' : 'font-size:1.25rem';
  $out = '<span style="'.$s.';color:#f59e0b;letter-spacing:1px">';
  for($i=1;$i<=5;$i++){
    if($r >= $i - 0.25)      $out .= '&#9733;';
    elseif($r >= $i - 0.75)  $out .= '&#189;';
    else                      $out .= '<span style="color:rgba(255,255,255,.15)">&#9733;</span>';
  }
  return $out.'</span>';
}
function mask_name(string $n): string {
  $n = trim($n);
  if(strlen($n) <= 2) return $n[0].'****';
  return $n[0] . str_repeat('*', min(4, strlen($n)-2)) . $n[strlen($n)-1];
}
?>
<style>
.reviews-section{max-width:980px;margin:0 auto 60px;padding:0 16px}
.reviews-section h2{font-size:1.05rem;font-weight:700;margin-bottom:18px;color:var(--text)}
.ratings-summary{background:var(--card);border:1px solid rgba(255,255,255,.06);border-radius:16px;padding:22px 24px;margin-bottom:20px;display:flex;gap:28px;align-items:center;flex-wrap:wrap}
.ratings-big{text-align:center;min-width:90px}
.ratings-big .big-num{font-size:3rem;font-weight:800;color:var(--accent);line-height:1}
.ratings-big .out-of{font-size:.82rem;color:var(--muted);margin-top:4px}
.ratings-bars{flex:1;min-width:180px}
.bar-row{display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:.8rem;color:var(--muted)}
.bar-track{flex:1;height:7px;background:rgba(255,255,255,.08);border-radius:999px;overflow:hidden}
.bar-fill{height:100%;background:linear-gradient(90deg,#f59e0b,#fbbf24);border-radius:999px;transition:width .4s}
.bar-count{min-width:28px;text-align:right;color:var(--muted);font-size:.78rem}
.review-card{background:var(--card);border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:18px 20px;margin-bottom:12px}
.review-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;gap:10px;flex-wrap:wrap}
.review-user{font-weight:600;font-size:.87rem;color:var(--text)}
.review-date{font-size:.76rem;color:rgba(255,255,255,.3)}
.review-text{font-size:.87rem;color:var(--muted);margin-top:8px;line-height:1.6}
.no-reviews{text-align:center;padding:30px;color:var(--muted);font-size:.9rem}
</style>

<div class="reviews-section" id="reviews">
  <h2><i class="fas fa-star" style="color:#f59e0b;margin-right:6px"></i>Product Ratings</h2>
  <?php $total = (int)($rSummary['total'] ?? 0); $avg = round((float)($rSummary['avg_rating'] ?? 0), 1); ?>
  <div class="ratings-summary">
    <div class="ratings-big">
      <div class="big-num" style="color:var(--accent)"><?=number_format($avg,1)?></div>
      <div style="margin:6px 0"><?=render_stars($avg)?></div>
      <div class="out-of">out of 5</div>
      <div style="color:rgba(255,255,255,.3);font-size:.75rem;margin-top:3px"><?=$total?> review<?=$total!==1?'s':''?></div>
    </div>
    <div class="ratings-bars">
      <?php foreach([5,4,3,2,1] as $star):
        $cnt = (int)($rSummary['s'.$star] ?? 0);
        $pct = $total > 0 ? round($cnt/$total*100) : 0;
      ?>
      <div class="bar-row">
        <span><?=$star?> &#9733;</span>
        <div class="bar-track"><div class="bar-fill" style="width:<?=$pct?>%"></div></div>
        <span class="bar-count"><?=$cnt?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if(!$reviews): ?>
  <div class="no-reviews">
    <i class="fas fa-comment-slash" style="font-size:1.8rem;margin-bottom:10px;display:block;opacity:.3"></i>
    No reviews yet. Be the first to rate this product!
  </div>
  <?php else: ?>
  <?php foreach($reviews as $rv): ?>
  <div class="review-card">
    <div class="review-header">
      <div>
        <div class="review-user"><?=e(mask_name($rv['full_name']))?></div>
        <div style="margin-top:4px"><?=render_stars((float)$rv['rating'], true)?></div>
      </div>
      <div class="review-date"><?=date('d M Y', strtotime($rv['created_at']))?></div>
    </div>
    <?php if(!empty(trim($rv['review_text']))): ?>
    <div class="review-text"><?=nl2br(e($rv['review_text']))?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if($ymalProducts): ?>
<div class="ymal-section">
  <h2 class="ymal-title">You May Also Like</h2>
  <div class="ymal-grid">
    <?php foreach($ymalProducts as $yp):
      $ys = (int)$yp['stock'];
      $yBadgeClass = $ys > 10 ? 'green' : ($ys > 0 ? 'low' : 'oos');
      $yBadgeText  = $ys > 10 ? 'In Stock' : ($ys > 0 ? 'Low Stock' : 'Out of Stock');
    ?>
    <a href="<?=BASE_URL?>/product.php?slug=<?=urlencode($yp['slug'])?>" class="ymal-card">
      <div class="ymal-badge-wrap">
        <span class="badge <?=$yBadgeClass?>" style="font-size:.7rem;padding:3px 8px"><?=$yBadgeText?></span>
      </div>
      <div class="ymal-img-wrap">
        <img src="<?=e($yp['image'])?>" alt="<?=e($yp['name'])?>">
      </div>
      <div class="ymal-body">
        <div class="ymal-name"><?=e($yp['name'])?></div>
        <div class="ymal-price">RM <?=number_format($yp['price'],2)?></div>
        <div class="ymal-view-btn"><span><i class="fas fa-eye"></i> View</span></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<style>
.ymal-section{max-width:980px;margin:0 auto 56px;padding:0 16px}
.ymal-title{font-size:1rem;font-weight:700;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.08em;margin-bottom:20px}
.ymal-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
.ymal-card{background:var(--card);border:1px solid rgba(255,255,255,.06);border-radius:14px;overflow:hidden;text-decoration:none;color:inherit;transition:transform .18s,border-color .18s,box-shadow .18s;position:relative;display:flex;flex-direction:column}
.ymal-card:hover{transform:translateY(-4px);border-color:rgba(255,56,56,.35);box-shadow:0 10px 28px rgba(0,0,0,.45)}
.ymal-badge-wrap{position:absolute;top:10px;left:10px;z-index:1}
.ymal-img-wrap{background:rgba(255,255,255,.02);height:210px;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:12px}
.ymal-img-wrap img{width:100%;height:100%;object-fit:contain;transition:transform .3s}
.ymal-card:hover .ymal-img-wrap img{transform:scale(1.06)}
.ymal-body{padding:14px 16px 16px;border-top:1px solid rgba(255,255,255,.05);display:flex;flex-direction:column;flex:1}
.ymal-name{font-size:.88rem;font-weight:500;color:var(--muted);line-height:1.45;margin-bottom:6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:calc(1.45em * 2)}
.ymal-price{font-size:1.1rem;font-weight:800;color:var(--accent)}
.ymal-view-btn{display:block;margin-top:auto;padding-top:10px;padding-bottom:0}
.ymal-view-btn span{display:block;padding:8px;border-radius:8px;background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#2b090a;font-size:.82rem;font-weight:700;text-align:center;transition:opacity .15s}
.ymal-card:hover .ymal-view-btn span{opacity:.88}
@media(max-width:768px){.ymal-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:420px){.ymal-grid{grid-template-columns:1fr}}
</style>
<?php endif; ?>

<script>
var favToggleUrl = <?=json_encode(BASE_URL.'/favourite_toggle.php')?>;
function toggleFav(productId){
  var btn = document.getElementById('favBtn');
  fetch(favToggleUrl, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'product_id='+productId
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    if(data.error) return;
    var label = document.getElementById('favLabel');
    if(data.favourited){
      btn.classList.add('active');
      btn.querySelector('i').className = 'fas fa-heart';
      label.textContent = 'Saved to Favourites';
      btn.title = 'Remove from Favourites';
    } else {
      btn.classList.remove('active');
      btn.querySelector('i').className = 'far fa-heart';
      label.textContent = 'Add to Favourites';
      btn.title = 'Add to Favourites';
    }
    // Update navbar count if present
    var badge = document.getElementById('navFavCount');
    if(badge) badge.textContent = data.count > 0 ? data.count : '';
  });
}

(function(){
  var input = document.getElementById('qty');
  if(!input) return;
  var max = parseInt(input.max);
  document.getElementById('qty-minus').addEventListener('click', function(){
    var v = parseInt(input.value);
    if(v > 1) input.value = v - 1;
  });
  document.getElementById('qty-plus').addEventListener('click', function(){
    var v = parseInt(input.value);
    if(v < max) input.value = v + 1;
  });
})();
(function(){
  var overlay = document.getElementById('lightbox');
  document.querySelector('.lightbox-trigger').addEventListener('click', function(){
    overlay.classList.add('active');
  });
  overlay.addEventListener('click', function(e){
    if(e.target !== document.querySelector('.lightbox-img')) overlay.classList.remove('active');
  });
  document.querySelector('.lightbox-close').addEventListener('click', function(){
    overlay.classList.remove('active');
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') overlay.classList.remove('active');
  });
})();
</script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
