<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

$id   = (int)($_GET['id'] ?? 0);
$stmt = $mysqli->prepare("SELECT id, status, total, payment_method FROM orders WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if(!$order){ http_response_code(404); exit('Order not found'); }

$pendingFpx = ($order['status'] === 'PENDING' && $order['payment_method'] === 'FPX');

$pageTitle = $pendingFpx ? 'Complete Your Payment' : 'Order Confirmed';
require_once __DIR__.'/includes/header.php';
?>
<div class="container">
  <div class="empty-card">
    <?php if($pendingFpx): ?>
      <div class="big-emoji">⏳</div>
      <h2 style="font-size:1.9rem;margin-bottom:8px;">Payment Pending</h2>
      <p style="color:var(--muted);margin-bottom:6px;">Your order has been created but payment has not been completed yet.</p>
      <p style="margin-bottom:4px;">Order <b>#<?=e($order['id'])?></b> &mdash;
        <b style="color:#ff9800">PENDING</b></p>
      <p style="font-size:1.1rem;margin-bottom:10px;">Total: <b>RM <?=number_format($order['total'],2)?></b></p>
      <p style="color:var(--muted);font-size:.9rem;margin-bottom:28px;">
        Go to <b>My Orders</b> and click <b>Pay Now</b> to complete your FPX payment.
      </p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <a class="btn btn-primary" href="<?=BASE_URL?>/orders.php">Go to My Orders &rarr;</a>
      </div>
    <?php else: ?>
      <div class="big-emoji">🎉</div>
      <h2 style="font-size:1.9rem;margin-bottom:8px;">Order Confirmed!</h2>
      <p style="color:var(--muted);margin-bottom:6px;">Thank you for your order.</p>
      <p style="margin-bottom:4px;">Order <b>#<?=e($order['id'])?></b> &mdash;
        <b style="color:var(--accent)"><?=e($order['status'])?></b></p>
      <p style="font-size:1.1rem;margin-bottom:28px;">Total: <b>RM <?=number_format($order['total'],2)?></b></p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <a class="btn" href="<?=BASE_URL?>/orders.php">View My Orders</a>
        <a class="btn btn-primary" href="<?=BASE_URL?>/index.php#shop">Continue Shopping</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__.'/includes/footer.php'; ?>
