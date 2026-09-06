<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

if(!is_logged_in()){
  set_flash('error', 'Login Required', 'Please sign in to view your cart.');
  redirect(BASE_URL.'/login.php');
}
track_activity('Managing cart');

$cartId = get_or_create_cart($mysqli);

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  $goCheckout = isset($_POST['checkout']);
  if(isset($_POST['update']) || $goCheckout){
    $adjusted = false;
    foreach($_POST['qty'] as $itemId => $qty){
      $qty = max(0, (int)$qty);
      $info = $mysqli->prepare("SELECT ci.product_id, p.stock FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.id=? AND ci.cart_id=?");
      $info->bind_param('ii', $itemId, $cartId);
      $info->execute();
      $row = $info->get_result()->fetch_assoc();
      if(!$row) continue;
      $stock = (int)$row['stock'];
      if($qty > $stock){ $qty = $stock; $adjusted = true; }
      if($qty <= 0){
        $d = $mysqli->prepare("DELETE FROM cart_items WHERE id=? AND cart_id=?");
        $d->bind_param('ii', $itemId, $cartId);
        $d->execute();
      } else {
        $u = $mysqli->prepare("UPDATE cart_items SET qty=? WHERE id=? AND cart_id=?");
        $u->bind_param('iii', $qty, $itemId, $cartId);
        $u->execute();
      }
    }
    if($adjusted)
      set_flash('error', 'Quantities adjusted', 'Some items were reduced to available stock.');
    elseif(!$goCheckout)
      set_flash('success', 'Cart updated', 'Your cart has been refreshed.');
  }
  redirect($goCheckout ? BASE_URL.'/checkout.php' : BASE_URL.'/cart.php');
}

$q = $mysqli->prepare("SELECT ci.id, p.name, p.price, p.stock, ci.qty FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.cart_id=?");
$q->bind_param('i', $cartId);
$q->execute();
$items  = $q->get_result()->fetch_all(MYSQLI_ASSOC);
$totals = cart_totals($mysqli, $cartId);

$pageTitle = 'Your Cart';
require_once __DIR__.'/includes/header.php';
?>
<header class="page-header">
  <div class="page-header-inner">
    <div class="ph-icon"><i class="fas fa-cart-shopping"></i></div>
    <div>
      <h1>Your Cart</h1>
      <p>Review your spicy picks before checkout</p>
    </div>
  </div>
</header>
<div class="container">
  <?php if(!$items): ?>
    <div class="empty-card">
      <div class="big-emoji">🛒</div>
      <h3>Your cart is empty</h3>
      <p style="color:var(--muted);margin-bottom:24px">Looks like you haven't added anything yet.</p>
      <a href="<?=BASE_URL?>/index.php#shop" class="btn btn-primary">Browse Products</a>
    </div>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <table class="table">
        <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
        <tbody>
        <?php foreach($items as $it): ?>
          <tr>
            <td data-label="Product"><?=e($it['name'])?></td>
            <td data-label="Price">RM <?=number_format($it['price'],2)?></td>
            <td data-label="Qty">
              <div class="qty-stepper">
                <button type="button" class="qty-btn" onclick="stepQty(this,-1)">−</button>
                <input type="number" name="qty[<?=$it['id']?>]"
                       min="0" max="<?=e($it['stock'])?>"
                       value="<?=min((int)$it['qty'], (int)$it['stock'])?>">
                <button type="button" class="qty-btn" onclick="stepQty(this,1)" data-max="<?=e($it['stock'])?>">+</button>
              </div>
              <?php if((int)$it['stock'] === 0): ?>
                <div class="muted" style="font-size:.85rem">Out of stock</div>
              <?php elseif((int)$it['qty'] > (int)$it['stock']): ?>
                <div class="muted" style="font-size:.85rem">Capped at <?=e($it['stock'])?></div>
              <?php endif; ?>
            </td>
            <td data-label="Subtotal">RM <?=number_format($it['price'] * min((int)$it['qty'], (int)$it['stock']), 2)?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td colspan="3" style="text-align:right"><b>Total</b></td>
          <td><b>RM <?=number_format($totals['total'],2)?></b></td>
        </tr>
        </tbody>
      </table>
      <button class="btn" name="update" value="1">Update Cart</button>
      <button class="btn btn-primary" name="checkout" value="1">Checkout</button>
    </form>
  <?php endif; ?>
</div>
<style>
.qty-stepper {
  display: inline-flex;
  align-items: center;
  border: 1px solid rgba(255,255,255,.15);
  border-radius: 8px;
  overflow: hidden;
}
.qty-stepper input[type=number] {
  width: 48px;
  text-align: center;
  border: none;
  border-left: 1px solid rgba(255,255,255,.1);
  border-right: 1px solid rgba(255,255,255,.1);
  background: transparent;
  color: var(--text);
  font-size: 1rem;
  padding: 6px 0;
  -moz-appearance: textfield;
  appearance: textfield;
}
.qty-stepper input[type=number]::-webkit-outer-spin-button,
.qty-stepper input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; }
.qty-btn {
  background: transparent;
  color: var(--text);
  border: none;
  padding: 6px 12px;
  font-size: 1.1rem;
  cursor: pointer;
  transition: background .15s;
}
.qty-btn:hover { background: rgba(255,255,255,.08); }
</style>
<script>
function stepQty(btn, dir) {
  const input = btn.parentElement.querySelector('input');
  const max   = parseInt(btn.dataset.max ?? input.max ?? 9999);
  const min   = parseInt(input.min ?? 0);
  let val = parseInt(input.value) + dir;
  input.value = Math.min(max, Math.max(min, val));
}
</script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
