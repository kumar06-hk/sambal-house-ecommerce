<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/mailer.php';

if(!is_logged_in()){
  set_flash('error', 'Login Required', 'Please sign in to view your order.');
  redirect(BASE_URL.'/login.php');
}

$orderId = (int)($_GET['order_id'] ?? 0);
if(!$orderId) redirect(BASE_URL.'/index.php');

$uid = (int)current_user_id();

// Check current status before attempting the PENDING -> PAID transition
$chk = $mysqli->prepare("SELECT status FROM orders WHERE id=? AND user_id=?");
$chk->bind_param('ii', $orderId, $uid);
$chk->execute();
$chkRow = $chk->get_result()->fetch_assoc();

if(!$chkRow){
  set_flash('error', 'Not Found', 'Order not found.');
  redirect(BASE_URL.'/orders.php');
}

if($chkRow['status'] === 'PENDING'){
  // Validate pay_token to prevent direct URL bypass
  $givenToken    = $_GET['pay_token'] ?? '';
  $expectedToken = $_SESSION['pay_token_'.$orderId] ?? '';
  if(empty($expectedToken) || !hash_equals($expectedToken, $givenToken)){
    set_flash('error', 'Invalid Request', 'Payment token missing or invalid. Please complete payment through the bank gateway.');
    redirect(BASE_URL.'/orders.php');
  }
  // Consume token so it cannot be reused
  unset($_SESSION['pay_token_'.$orderId]);
}

// Atomically mark PENDING -> PAID (idempotent — safe to call twice on page refresh)
$upd = $mysqli->prepare("UPDATE orders SET status='PAID', paid_at=NOW() WHERE id=? AND user_id=? AND status='PENDING'");
$upd->bind_param('ii', $orderId, $uid);
$upd->execute();
$justPaid = $upd->affected_rows > 0;

// Fetch order (works whether we just updated it or it was already PAID)
$stmt = $mysqli->prepare(
  "SELECT o.id, o.full_name, o.email, o.total, o.status, o.payment_method,
          o.created_at, o.address_line1, o.address_line2, o.city,
          o.state_region, o.postcode
   FROM orders o
   WHERE o.id = ? AND o.user_id = ?"
);
$stmt->bind_param('ii', $orderId, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if(!$order){
  set_flash('error', 'Not Found', 'Order not found.');
  redirect(BASE_URL.'/orders.php');
}

// Fetch order items
$iStmt = $mysqli->prepare(
  "SELECT name, qty, price FROM order_items WHERE order_id=?"
);
$iStmt->bind_param('i', $orderId);
$iStmt->execute();
$orderItems = $iStmt->get_result()->fetch_all(MYSQLI_ASSOC);

if($justPaid){
  @send_order_receipt($order['email'], $order['full_name'], $order, $orderItems);
}

track_activity('Payment confirmed for order #'.$orderId);
$pageTitle = 'Payment Successful';
require_once __DIR__.'/includes/header.php';
?>
<style>
.ps-wrap {
  max-width: 600px;
  margin: 40px auto;
  padding: 0 18px 60px;
}
.ps-hero {
  text-align: center;
  padding: 48px 28px 36px;
  background: linear-gradient(180deg, rgba(26,12,14,.95) 0%, rgba(15,7,8,.95) 100%);
  border: 1px solid rgba(255,56,56,.18);
  border-radius: 20px;
  box-shadow: 0 30px 80px -30px rgba(255,56,56,.35);
  margin-bottom: 20px;
}
.ps-icon {
  font-size: 4rem;
  margin-bottom: 14px;
  line-height: 1;
  filter: drop-shadow(0 6px 16px rgba(0,200,100,.4));
}
.ps-hero h2 { font-size: 1.9rem; margin-bottom: 8px; color: #fff; }
.ps-hero p  { color: var(--muted); margin-bottom: 0; }
.ps-badge {
  display: inline-flex; align-items: center; gap: 6px;
  margin: 14px 0 0;
  padding: 5px 14px;
  background: rgba(34,197,94,.15);
  border: 1px solid rgba(34,197,94,.35);
  border-radius: 20px;
  font-size: .82rem; font-weight: 600; color: #4ade80;
}

.ps-card {
  background: var(--card);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 14px;
}
.ps-card-head {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 18px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  font-weight: 600; font-size: .9rem;
}
.ps-card-head i { color: var(--accent); }
.ps-card-body { padding: 16px 18px; }

.ps-detail-row {
  display: flex; justify-content: space-between;
  padding: 7px 0;
  border-bottom: 1px solid rgba(255,255,255,.05);
  font-size: .88rem;
}
.ps-detail-row:last-child { border-bottom: none; }
.ps-detail-label { color: var(--muted); }
.ps-detail-value { font-weight: 500; }

.ps-items-list { list-style: none; }
.ps-item {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid rgba(255,255,255,.05);
  font-size: .88rem;
}
.ps-item:last-child { border-bottom: none; }
.ps-item-name { flex: 1; }
.ps-item-qty  { color: var(--muted); margin: 0 12px; }
.ps-item-price { font-weight: 600; color: var(--accent); }

.ps-total {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px 18px;
  border-top: 1px solid rgba(255,255,255,.08);
  font-weight: 700;
}
.ps-total-amount { font-size: 1.3rem; color: var(--accent); }

.ps-actions {
  display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;
  margin-top: 20px;
}
.ps-actions .btn { padding: 10px 20px; font-size: .88rem; }
</style>

<div class="ps-wrap">

  <!-- Hero -->
  <div class="ps-hero">
    <div class="ps-icon">✅</div>
    <h2>Payment Successful!</h2>
    <p>Thank you, <strong><?=e($order['full_name'])?></strong>. Your payment has been confirmed.</p>
    <div class="ps-badge">
      <i class="fas fa-check-circle"></i> Order #<?=$order['id']?> — PAID
    </div>
  </div>

  <!-- Order details -->
  <div class="ps-card">
    <div class="ps-card-head"><i class="fas fa-receipt"></i> Order Details</div>
    <div class="ps-card-body">
      <div class="ps-detail-row">
        <span class="ps-detail-label">Order Number</span>
        <span class="ps-detail-value">#<?=$order['id']?></span>
      </div>
      <div class="ps-detail-row">
        <span class="ps-detail-label">Status</span>
        <span class="ps-detail-value" style="color:#4ade80">PAID</span>
      </div>
      <div class="ps-detail-row">
        <span class="ps-detail-label">Payment Method</span>
        <span class="ps-detail-value">Online Banking (FPX)</span>
      </div>
      <div class="ps-detail-row">
        <span class="ps-detail-label">Date</span>
        <span class="ps-detail-value"><?=e(date('d M Y, g:i A', strtotime($order['created_at'])))?></span>
      </div>
    </div>
  </div>

  <!-- Items -->
  <?php if($orderItems): ?>
  <div class="ps-card">
    <div class="ps-card-head"><i class="fas fa-box"></i> Items Ordered</div>
    <div class="ps-card-body" style="padding:0 18px">
      <ul class="ps-items-list">
        <?php foreach($orderItems as $item): ?>
        <li class="ps-item">
          <span class="ps-item-name"><?=e($item['name'])?></span>
          <span class="ps-item-qty">× <?=(int)$item['qty']?></span>
          <span class="ps-item-price">RM <?=number_format($item['price']*$item['qty'],2)?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="ps-total">
      <span>Total Paid</span>
      <span class="ps-total-amount">RM <?=number_format($order['total'],2)?></span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Delivery address -->
  <div class="ps-card">
    <div class="ps-card-head"><i class="fas fa-map-marker-alt"></i> Delivery Address</div>
    <div class="ps-card-body">
      <div class="ps-detail-row">
        <span class="ps-detail-label">Address</span>
        <span class="ps-detail-value" style="text-align:right">
          <?=e($order['address_line1'])?>
          <?php if($order['address_line2']): ?><br><?=e($order['address_line2'])?><?php endif; ?>
        </span>
      </div>
      <div class="ps-detail-row">
        <span class="ps-detail-label">City / State</span>
        <span class="ps-detail-value"><?=e($order['city'])?>, <?=e($order['state_region'])?></span>
      </div>
      <div class="ps-detail-row">
        <span class="ps-detail-label">Postcode</span>
        <span class="ps-detail-value"><?=e($order['postcode'])?></span>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div class="ps-actions">
    <a class="btn btn-primary" href="<?=BASE_URL?>/track_order.php?order_id=<?=$order['id']?>">
      <i class="fas fa-truck"></i> Track My Order
    </a>
    <a class="btn" href="<?=BASE_URL?>/orders.php">
      <i class="fas fa-receipt"></i> My Orders
    </a>
    <a class="btn" href="<?=BASE_URL?>/index.php#shop">
      <i class="fas fa-shopping-bag"></i> Continue Shopping
    </a>
  </div>

</div>
<?php require_once __DIR__.'/includes/footer.php'; ?>
