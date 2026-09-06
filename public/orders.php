<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/mailer.php';

if(!is_logged_in()) redirect(BASE_URL.'/login.php');

$uid = (int)current_user_id();

// Handle cancel
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_order'])){
  csrf_verify();
  $oid = (int)($_POST['order_id'] ?? 0);
  $chk = $mysqli->prepare(
    "SELECT id, email, full_name, total, payment_method,
            address_line1, address_line2, city, state_region, postcode, created_at
     FROM orders WHERE id=? AND user_id=? AND status IN ('PENDING','PAID')"
  );
  $chk->bind_param('ii', $oid, $uid);
  $chk->execute();
  $orderData = $chk->get_result()->fetch_assoc();
  if($orderData){
    $iq = $mysqli->prepare("SELECT name, qty, price FROM order_items WHERE order_id=?");
    $iq->bind_param('i', $oid);
    $iq->execute();
    $cancelItems = $iq->get_result()->fetch_all(MYSQLI_ASSOC);
    $mysqli->begin_transaction();
    try {
      $siq = $mysqli->prepare("SELECT product_id, qty FROM order_items WHERE order_id=?");
      $siq->bind_param('i', $oid);
      $siq->execute();
      foreach($siq->get_result()->fetch_all(MYSQLI_ASSOC) as $item){
        $rst = $mysqli->prepare("UPDATE products SET stock = stock + ? WHERE id=?");
        $rst->bind_param('ii', $item['qty'], $item['product_id']);
        $rst->execute();
      }
      $upd = $mysqli->prepare("UPDATE orders SET status='CANCELLED', cancelled_at=NOW() WHERE id=?");
      $upd->bind_param('i', $oid);
      $upd->execute();
      $mysqli->commit();
      @send_order_cancellation($orderData['email'], $orderData['full_name'], $orderData, $cancelItems);
      set_flash('success', 'Order cancelled', 'Your order #'.$oid.' has been cancelled and a confirmation has been sent to your email.');
    } catch(Exception $ex){
      $mysqli->rollback();
      set_flash('error', 'Cancel failed', $ex->getMessage());
    }
  } else {
    set_flash('error', 'Not allowed', 'This order cannot be cancelled.');
  }
  redirect(BASE_URL.'/orders.php');
}

// Handle review submission
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='submit_review'){
  csrf_verify();
  $oid = (int)($_POST['order_id'] ?? 0);
  $chk = $mysqli->prepare("SELECT id FROM orders WHERE id=? AND user_id=? AND status='COMPLETED'");
  $chk->bind_param('ii', $oid, $uid);
  $chk->execute();
  if($chk->get_result()->fetch_assoc()){
    $reviews = $_POST['reviews'] ?? [];
    foreach($reviews as $pidRaw => $data){
      $pid    = (int)$pidRaw;
      $rating = (int)($data['rating'] ?? 0);
      $text   = trim($data['text'] ?? '');
      if($pid <= 0 || $rating < 1 || $rating > 5) continue;
      $v = $mysqli->prepare("SELECT id FROM order_items WHERE order_id=? AND product_id=?");
      $v->bind_param('ii', $oid, $pid);
      $v->execute();
      if(!$v->get_result()->fetch_assoc()) continue;
      $ins = $mysqli->prepare("INSERT INTO product_reviews (product_id, user_id, order_id, rating, review_text) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating), review_text=VALUES(review_text), order_id=VALUES(order_id)");
      $ins->bind_param('iiiis', $pid, $uid, $oid, $rating, $text);
      $ins->execute();
    }
    set_flash('success', 'Review submitted!', 'Thank you for your feedback.');
  }
  redirect(BASE_URL.'/orders.php?tab=completed');
}

// Handle return request
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='return_order'){
  csrf_verify();
  $oid = (int)($_POST['order_id'] ?? 0);
  $chk = $mysqli->prepare("SELECT id FROM orders WHERE id=? AND user_id=? AND status='COMPLETED'");
  $chk->bind_param('ii', $oid, $uid);
  $chk->execute();
  if($chk->get_result()->fetch_assoc()){
    $upd = $mysqli->prepare("UPDATE orders SET status='RETURNED', return_requested_at=NOW() WHERE id=? AND user_id=? AND status='COMPLETED'");
    $upd->bind_param('ii', $oid, $uid);
    $upd->execute();
  }
  redirect(BASE_URL.'/return_track.php?order_id='.$oid);
}

// Auto-advance all in-progress orders for this user
$mysqli->query("UPDATE orders SET status='SHIPPED',   shipped_at=DATE_ADD(paid_at,INTERVAL 60 SECOND)       WHERE user_id=$uid AND status IN ('PAID','PROCESSING') AND paid_at IS NOT NULL               AND TIMESTAMPDIFF(SECOND,paid_at,NOW())>=60");
$mysqli->query("UPDATE orders SET status='RECEIVED',  received_at=DATE_ADD(shipped_at,INTERVAL 60 SECOND)   WHERE user_id=$uid AND status='SHIPPED'                AND shipped_at IS NOT NULL             AND TIMESTAMPDIFF(SECOND,shipped_at,NOW())>=60");
$mysqli->query("UPDATE orders SET status='COMPLETED', completed_at=DATE_ADD(received_at,INTERVAL 60 SECOND) WHERE user_id=$uid AND status='RECEIVED'               AND received_at IS NOT NULL            AND TIMESTAMPDIFF(SECOND,received_at,NOW())>=60");
$mysqli->query("UPDATE orders SET refunded_at=DATE_ADD(cancellation_accepted_at,INTERVAL 60 SECOND) WHERE user_id=$uid AND status='CANCELLED' AND paid_at IS NOT NULL AND cancellation_accepted_at IS NOT NULL AND refunded_at IS NULL AND TIMESTAMPDIFF(SECOND,cancellation_accepted_at,NOW())>=60");
$mysqli->query("UPDATE orders SET return_refunded_at=DATE_ADD(return_accepted_at,INTERVAL 60 SECOND)  WHERE user_id=$uid AND status='RETURNED' AND return_accepted_at IS NOT NULL  AND return_refunded_at IS NULL  AND TIMESTAMPDIFF(SECOND,return_accepted_at,NOW())>=60");

// Tab filtering
$tab = $_GET['tab'] ?? 'all';
if(!in_array($tab, ['all','topay','toship','toreceive','completed','cancelled','returnrefund'])) $tab = 'all';

// Counts per tab
$countRows = $mysqli->query("SELECT status, COUNT(*) AS cnt FROM orders WHERE user_id=$uid GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$cs = [];
foreach($countRows as $r) $cs[$r['status']] = (int)$r['cnt'];
$tabCounts = [
  'all'          => array_sum($cs),
  'topay'        => $cs['PENDING'] ?? 0,
  'toship'       => ($cs['PAID'] ?? 0) + ($cs['PROCESSING'] ?? 0),
  'toreceive'    => $cs['SHIPPED'] ?? 0,
  'completed'    => ($cs['COMPLETED'] ?? 0) + ($cs['RECEIVED'] ?? 0),
  'cancelled'    => $cs['CANCELLED'] ?? 0,
  'returnrefund' => $cs['RETURNED'] ?? 0,
];

$statusFilter = match($tab) {
  'topay'        => "AND o.status IN ('PENDING')",
  'toship'       => "AND o.status IN ('PAID','PROCESSING')",
  'toreceive'    => "AND o.status IN ('SHIPPED')",
  'completed'    => "AND o.status IN ('COMPLETED','RECEIVED')",
  'cancelled'    => "AND o.status IN ('CANCELLED')",
  'returnrefund' => "AND o.status IN ('RETURNED')",
  default        => ''
};

$orders = $mysqli->query("
  SELECT o.id, o.status, o.total, o.created_at, o.payment_method,
         o.paid_at, o.shipped_at, o.received_at, o.completed_at,
         o.cancelled_at, o.cancellation_accepted_at, o.refunded_at,
         o.return_requested_at, o.return_accepted_at, o.return_refunded_at
  FROM orders o
  WHERE o.user_id = $uid $statusFilter
  ORDER BY o.id DESC
")->fetch_all(MYSQLI_ASSOC);

$orderItems = [];
if($orders){
  $orderIds = array_column($orders, 'id');
  $ph = implode(',', array_fill(0, count($orderIds), '?'));
  $iq = $mysqli->prepare("SELECT order_id, product_id, name, qty, price FROM order_items WHERE order_id IN ($ph)");
  $iq->bind_param(str_repeat('i', count($orderIds)), ...$orderIds);
  $iq->execute();
  $iqRes = $iq->get_result();
  while($row = $iqRes->fetch_assoc())
    $orderItems[$row['order_id']][] = $row;
}

// Which product_ids has this user already reviewed? Also fetch review data for display
$reviewedProductIds = [];
$reviewsData = [];
if($orders){
  $allPids = [];
  foreach($orderItems as $rows){ foreach($rows as $r) $allPids[] = (int)$r['product_id']; }
  $allPids = array_values(array_unique($allPids));
  if($allPids){
    $ph = implode(',', array_fill(0, count($allPids), '?'));
    $rq = $mysqli->prepare("SELECT pr.product_id, pr.rating, pr.review_text, pr.created_at, p.image AS product_image FROM product_reviews pr JOIN products p ON p.id=pr.product_id WHERE pr.user_id=? AND pr.product_id IN ($ph)");
    $rq->bind_param('i'.str_repeat('i', count($allPids)), $uid, ...$allPids);
    $rq->execute();
    $rqRes = $rq->get_result();
    while($r = $rqRes->fetch_assoc()){
      $reviewedProductIds[$r['product_id']] = true;
      $reviewsData[$r['product_id']] = $r;
    }
  }
}

// Build reviewed items per order for the "My Review" modal
$jsReviewedItems = [];
foreach($orderItems as $oidKey => $rows){
  foreach($rows as $r){
    $pid = (int)$r['product_id'];
    if(isset($reviewedProductIds[$pid])){
      $rev = $reviewsData[$pid];
      $jsReviewedItems[$oidKey][] = [
        'product_id' => $pid,
        'name'       => $r['name'],
        'image'      => $rev['product_image'] ?? '',
        'rating'     => (int)$rev['rating'],
        'text'       => $rev['review_text'] ?? '',
        'date'       => date('d M Y', strtotime($rev['created_at'])),
      ];
    }
  }
}

$pageTitle = 'My Orders';
require_once __DIR__.'/includes/header.php';
?>
<style>
/* ===== ORDERS PAGE ===== */
.orders-wrap { max-width: 820px; margin: 0 auto; padding: 0 16px 60px; }

/* Tabs */
.orders-tabs {
  display: flex;
  background: var(--card);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 14px;
  overflow-x: auto;
  margin-bottom: 20px;
  scrollbar-width: none;
  padding: 0 4px;
}
.orders-tabs::-webkit-scrollbar { display: none; }
.orders-tab {
  flex: 0 0 auto;
  padding: 15px 16px;
  font-size: .84rem;
  font-weight: 500;
  color: var(--muted);
  text-decoration: none;
  white-space: nowrap;
  border-bottom: 2px solid transparent;
  transition: color .2s, border-color .2s;
  display: flex;
  align-items: center;
  gap: 7px;
  letter-spacing: .01em;
}
.orders-tab:hover { color: var(--text); }
.orders-tab.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }
.tab-badge {
  background: var(--accent);
  color: #fff;
  border-radius: 999px;
  font-size: .68rem;
  font-weight: 700;
  padding: 2px 8px;
  min-width: 20px;
  text-align: center;
  line-height: 1.5;
}

/* Order Card */
.order-card {
  background: var(--card);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 14px;
  transition: border-color .2s;
}
.order-card:hover { border-color: rgba(255,255,255,.12); }

/* Card header */
.oc-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 18px;
  border-bottom: 1px solid rgba(255,255,255,.05);
  gap: 10px;
}
.oc-seller {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  font-size: .88rem;
}
.oc-seller i { color: var(--accent); }
.oc-seller-divider { color: rgba(255,255,255,.2); font-weight: 300; }
.oc-order-ref { color: rgba(255,255,255,.4); font-weight: 400; font-size: .8rem; }
.oc-status-badge {
  font-size: .76rem;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 20px;
  white-space: nowrap;
}

/* Items section */
.oc-items { padding: 4px 18px; }
.oc-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid rgba(255,255,255,.04);
  gap: 12px;
}
.oc-item:last-child { border-bottom: none; }
.oc-item-name { flex: 1; font-size: .88rem; font-weight: 500; }
.oc-item-qty  { color: var(--muted); font-size: .82rem; white-space: nowrap; }
.oc-item-price { font-weight: 600; color: var(--accent); font-size: .88rem; white-space: nowrap; min-width: 70px; text-align: right; }

/* Total row */
.oc-total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 18px;
  border-top: 1px solid rgba(255,255,255,.05);
  font-size: .83rem;
}
.oc-meta { color: rgba(255,255,255,.35); }
.oc-total-label { color: var(--muted); }
.oc-total-amount { font-size: 1rem; font-weight: 700; color: var(--text); }

/* Actions row */
.oc-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-top: 1px solid rgba(255,255,255,.05);
  flex-wrap: wrap;
}
.oc-btn {
  padding: 7px 14px;
  border-radius: 8px;
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: opacity .15s, transform .1s;
}
.oc-btn:hover { opacity: .85; transform: translateY(-1px); }
.oc-btn-pay    { background: linear-gradient(135deg,#14532d,#16a34a); color:#fff; }
.oc-btn-cancel { background:rgba(220,38,38,.12); color:#f87171; border:1px solid rgba(220,38,38,.25); }
.oc-btn-receipt{ background:rgba(255,255,255,.07); color:var(--text); border:1px solid rgba(255,255,255,.1); }
.oc-btn-track  { background:linear-gradient(135deg,#1e1b4b,#4338ca); color:#fff; }
.oc-btn-refund { background:linear-gradient(135deg,#7c2d12,#ea580c); color:#fff; }
.oc-btn-return { background:linear-gradient(135deg,#0c4a6e,#0891b2); color:#fff; }
.oc-btn-retstatus{ background:linear-gradient(135deg,#581c87,#a855f7); color:#fff; }
.oc-btn-rate       { background:linear-gradient(135deg,#78350f,#f59e0b); color:#fff; }
.oc-btn-viewreview { background:rgba(99,102,241,.15); color:#a5b4fc; border:1px solid rgba(99,102,241,.3); }

/* Review modal */
.review-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:flex-start;justify-content:center;overflow-y:auto;padding:20px 0}
.review-modal-overlay.active{display:flex}
.review-modal-box{background:#1c0d0f;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:28px;max-width:500px;width:92%;position:relative;margin:auto}
.review-product-block{border-top:1px solid rgba(255,255,255,.07);padding:18px 0 4px}
.review-product-block:first-child{border-top:none;padding-top:0}
.review-product-name{font-weight:600;font-size:.92rem;margin-bottom:12px;color:var(--text)}
.star-selector{display:flex;gap:6px;margin-bottom:12px;flex-direction:row-reverse;justify-content:flex-end}
.star-selector input{display:none}
.star-selector label{font-size:1.6rem;color:rgba(255,255,255,.2);cursor:pointer;transition:color .15s}
.star-selector input:checked ~ label,.star-selector label:hover,.star-selector label:hover ~ label{color:#f59e0b}
.review-textarea{width:100%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:var(--text);padding:10px 12px;font-size:.84rem;resize:vertical;min-height:72px;font-family:inherit}
.review-textarea:focus{outline:none;border-color:rgba(245,158,11,.4)}
.review-submit-btn{width:100%;margin-top:18px;padding:11px;border-radius:10px;background:linear-gradient(135deg,#78350f,#f59e0b);color:#fff;font-weight:700;font-size:.9rem;border:none;cursor:pointer;transition:opacity .15s}
.review-submit-btn:hover{opacity:.88}

/* Info banners */
.oc-info-bar {
  border-radius: 10px;
  padding: 10px 16px;
  margin-bottom: 14px;
  font-size: .83rem;
  display: flex;
  align-items: center;
  gap: 8px;
}
.oc-info-bar.warn { background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.25); color:#fbbf24; }
.oc-info-bar.info { background:rgba(59,130,246,.1);  border:1px solid rgba(59,130,246,.25);  color:#93c5fd; }

/* Empty state */
.orders-empty { text-align:center; padding:60px 20px; color:var(--muted); }
.orders-empty .big-emoji { font-size:3.5rem; margin-bottom:14px; }
.orders-empty h3 { color:var(--text); margin-bottom:8px; }
</style>

<header class="page-header">
  <div class="page-header-inner" style="justify-content:space-between;width:100%">
    <div style="display:flex;align-items:center;gap:18px">
      <div class="ph-icon"><i class="fas fa-receipt"></i></div>
      <div><h1>My Orders</h1><p>Track every spicy delivery</p></div>
    </div>
    <a class="btn btn-primary" href="<?=BASE_URL?>/index.php#shop"
       style="border-radius:999px;padding:12px 22px;font-weight:600;background:var(--gradient-fire);color:#2b090a">
      Continue Shopping
    </a>
  </div>
</header>

<div class="container">
<div class="orders-wrap">

  <!-- Status Tabs -->
  <div class="orders-tabs">
    <?php
    $tabDefs = [
      'all'          => 'All',
      'topay'        => 'To Pay',
      'toship'       => 'To Ship',
      'toreceive'    => 'To Receive',
      'completed'    => 'Completed',
      'cancelled'    => 'Cancelled',
      'returnrefund' => 'Return / Refund',
    ];
    foreach($tabDefs as $key => $lbl):
      $cnt = $tabCounts[$key];
    ?>
    <a href="?tab=<?=$key?>" class="orders-tab <?= $tab===$key ? 'active' : '' ?>">
      <?=$lbl?>
      <?php if($cnt > 0): ?><span class="tab-badge"><?=$cnt?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Context banners -->
  <?php if($tab==='topay' && $orders): ?>
  <div class="oc-info-bar warn">
    <i class="fas fa-clock"></i>
    Complete your payment to confirm your order. Unpaid orders may be cancelled.
  </div>
  <?php elseif($tab==='toreceive' && $orders): ?>
  <div class="oc-info-bar info">
    <i class="fas fa-truck"></i>
    Your order is on the way. It will auto-confirm as received shortly.
  </div>
  <?php endif; ?>

  <?php if(!$orders): ?>
  <div class="orders-empty">
    <div class="big-emoji">📦</div>
    <h3><?= $tab==='all' ? 'No orders yet' : 'Nothing here' ?></h3>
    <p style="margin-bottom:20px">
      <?= $tab==='all' ? "You haven't placed any orders yet." : "No orders in this category." ?>
    </p>
    <a href="<?=BASE_URL?>/index.php#shop" class="btn btn-primary">Shop Now</a>
  </div>
  <?php else: ?>

  <?php foreach($orders as $o):
    $items = $orderItems[$o['id']] ?? [];

    $statusCfg = match($o['status']) {
      'PENDING'    => ['label'=>'To Pay',       'color'=>'#f59e0b', 'bg'=>'rgba(245,158,11,.15)'],
      'PAID'       => ['label'=>'To Ship',      'color'=>'#3b82f6', 'bg'=>'rgba(59,130,246,.15)'],
      'PROCESSING' => ['label'=>'Processing',   'color'=>'#3b82f6', 'bg'=>'rgba(59,130,246,.15)'],
      'SHIPPED'    => ['label'=>'To Receive',   'color'=>'#8b5cf6', 'bg'=>'rgba(139,92,246,.15)'],
      'RECEIVED'   => ['label'=>'Delivered',    'color'=>'#22d3ee', 'bg'=>'rgba(34,211,238,.15)'],
      'COMPLETED'  => ['label'=>'Completed',    'color'=>'#10b981', 'bg'=>'rgba(16,185,129,.15)'],
      'CANCELLED'  => ['label'=>'Cancelled',    'color'=>'#ef4444', 'bg'=>'rgba(239,68,68,.15)'],
      'RETURNED'   => ['label'=>'Return/Refund','color'=>'#a855f7', 'bg'=>'rgba(168,85,247,.15)'],
      default      => ['label'=>$o['status'],   'color'=>'#888',    'bg'=>'rgba(100,100,100,.15)'],
    };
  ?>

  <div class="order-card">

    <!-- Header: seller + status -->
    <div class="oc-header">
      <div class="oc-seller">
        <i class="fas fa-pepper-hot"></i>
        <span>Sambal House</span>
        <span class="oc-seller-divider">|</span>
        <span class="oc-order-ref">Order #<?=(int)$o['id']?></span>
      </div>
      <span class="oc-status-badge"
            style="color:<?=$statusCfg['color']?>;background:<?=$statusCfg['bg']?>">
        <?=$statusCfg['label']?>
      </span>
    </div>

    <!-- Items -->
    <div class="oc-items">
      <?php foreach($items as $item): ?>
      <div class="oc-item">
        <span class="oc-item-name"><?=e($item['name'])?></span>
        <span class="oc-item-qty">× <?=(int)$item['qty']?></span>
        <span class="oc-item-price">RM <?=number_format($item['price'],2)?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Total + meta -->
    <div class="oc-total-row">
      <span class="oc-meta">
        <?=date('d M Y, g:i A', strtotime($o['created_at']))?>
        &nbsp;·&nbsp; <?=e($o['payment_method'])?>
      </span>
      <span>
        <span class="oc-total-label">Order Total: </span>
        <span class="oc-total-amount">RM <?=number_format($o['total'],2)?></span>
      </span>
    </div>

    <!-- Action buttons -->
    <div class="oc-actions">
      <?php if($o['status']==='PENDING' && $o['payment_method']==='FPX'): ?>
        <button onclick="openPayNow(<?=(int)$o['id']?>)" class="oc-btn oc-btn-pay">
          <i class="fas fa-credit-card"></i> Pay Now
        </button>
      <?php endif; ?>

      <?php if(in_array($o['status'], ['PENDING','PAID'])): ?>
        <form method="post" style="display:inline"
              onsubmit="return confirm('Cancel order #<?=(int)$o['id']?>?\n\nThis cannot be undone.')">
          <?=csrf_field()?>
          <input type="hidden" name="order_id" value="<?=(int)$o['id']?>">
          <button type="submit" name="cancel_order" value="1" class="oc-btn oc-btn-cancel">
            <i class="fas fa-times"></i> Cancel
          </button>
        </form>
      <?php endif; ?>

      <?php if(in_array($o['status'], ['PAID','PROCESSING','SHIPPED','RECEIVED','COMPLETED'])): ?>
        <a href="<?=BASE_URL?>/receipt.php?id=<?=(int)$o['id']?>" class="oc-btn oc-btn-receipt">
          <i class="fas fa-file-arrow-down"></i> Receipt
        </a>
      <?php endif; ?>

      <?php if(in_array($o['status'], ['PENDING','PAID','PROCESSING','SHIPPED','RECEIVED','COMPLETED'])): ?>
        <a href="<?=BASE_URL?>/track_order.php?order_id=<?=(int)$o['id']?>" class="oc-btn oc-btn-track">
          <i class="fas fa-truck"></i> Track
        </a>
      <?php endif; ?>

      <?php if($o['status']==='CANCELLED' && $o['paid_at'] !== null): ?>
        <a href="<?=BASE_URL?>/refund_track.php?order_id=<?=(int)$o['id']?>" class="oc-btn oc-btn-refund">
          <i class="fas fa-rotate-left"></i> Refund Status
        </a>
      <?php endif; ?>

      <?php if($o['status']==='COMPLETED'):
        $unreviewedItems = array_filter($items, fn($i) => !isset($reviewedProductIds[(int)$i['product_id']]));
        $reviewedItems   = array_filter($items, fn($i) =>  isset($reviewedProductIds[(int)$i['product_id']]));
      ?>
        <?php if($unreviewedItems): ?>
        <button onclick="openReviewModal(<?=(int)$o['id']?>)" class="oc-btn oc-btn-rate">
          <i class="fas fa-star"></i> Rate
        </button>
        <?php endif; ?>
        <?php if($reviewedItems): ?>
        <button onclick="openViewReviewModal(<?=(int)$o['id']?>)" class="oc-btn oc-btn-viewreview">
          <i class="fas fa-eye"></i> My Review
        </button>
        <?php endif; ?>
        <button onclick="confirmReturn(<?=(int)$o['id']?>)" class="oc-btn oc-btn-return">
          <i class="fas fa-rotate-left"></i> Return Item
        </button>
      <?php endif; ?>

      <?php if($o['status']==='RETURNED'): ?>
        <a href="<?=BASE_URL?>/return_track.php?order_id=<?=(int)$o['id']?>" class="oc-btn oc-btn-retstatus">
          <i class="fas fa-arrow-rotate-left"></i> Return Status
        </a>
      <?php endif; ?>
    </div>

  </div><!-- /order-card -->
  <?php endforeach; ?>
  <?php endif; ?>

</div><!-- /orders-wrap -->
</div><!-- /container -->

<!-- Review modal -->
<div id="reviewModalOverlay" class="review-modal-overlay" role="dialog" aria-modal="true">
  <div class="review-modal-box">
    <button onclick="closeReviewModal()" style="position:absolute;top:14px;right:16px;background:none;border:none;color:var(--muted);font-size:1.3rem;cursor:pointer" aria-label="Close">
      <i class="fas fa-times"></i>
    </button>
    <h3 style="color:#fff;margin-bottom:4px"><i class="fas fa-star" style="color:#f59e0b"></i> Rate Your Order</h3>
    <p id="reviewModalRef" style="color:var(--muted);font-size:.83rem;margin-bottom:18px"></p>
    <form id="reviewForm" method="post">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="submit_review">
      <input type="hidden" name="order_id" id="reviewFormOrderId" value="">
      <div id="reviewProductsContainer"></div>
      <button type="submit" class="review-submit-btn"><i class="fas fa-paper-plane"></i> Submit Review</button>
    </form>
  </div>
</div>

<!-- View review modal -->
<div id="viewReviewOverlay" class="review-modal-overlay" role="dialog" aria-modal="true">
  <div class="review-modal-box">
    <button onclick="closeViewReviewModal()" style="position:absolute;top:14px;right:16px;background:none;border:none;color:var(--muted);font-size:1.3rem;cursor:pointer" aria-label="Close">
      <i class="fas fa-times"></i>
    </button>
    <h3 style="color:#fff;margin-bottom:4px"><i class="fas fa-eye" style="color:#a5b4fc"></i> My Reviews</h3>
    <p id="viewReviewRef" style="color:var(--muted);font-size:.83rem;margin-bottom:18px"></p>
    <div id="viewReviewContainer"></div>
    <button onclick="closeViewReviewModal()" style="width:100%;margin-top:18px;padding:10px;border-radius:10px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);color:var(--text);font-size:.88rem;font-weight:600;cursor:pointer">
      Close
    </button>
  </div>
</div>

<!-- Hidden return form -->
<form id="returnForm" method="post" style="display:none">
  <?=csrf_field()?>
  <input type="hidden" name="action" value="return_order">
  <input type="hidden" name="order_id" id="returnFormOrderId" value="">
</form>

<!-- Pay Now bank picker modal -->
<div id="payNowOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center">
  <div style="background:#1c0d0f;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:32px;max-width:440px;width:90%;position:relative">
    <button onclick="closePayNow()" style="position:absolute;top:14px;right:16px;background:none;border:none;color:var(--muted);font-size:1.3rem;cursor:pointer">
      <i class="fas fa-times"></i>
    </button>
    <h3 style="color:#fff;margin-bottom:6px">
      <i class="fas fa-credit-card" style="color:var(--accent)"></i> Complete Your Payment
    </h3>
    <p style="color:var(--muted);font-size:.88rem;margin-bottom:20px">
      Select your bank for order <b id="payNowOrderRef" style="color:#fff"></b>.
    </p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <?php
      $banks = [
        'MAYBANK'   => 'Maybank',
        'CIMB'      => 'CIMB Bank',
        'PUBLIC'    => 'Public Bank',
        'RHB'       => 'RHB Bank',
        'HONGLEONG' => 'Hong Leong Bank',
        'AMBANK'    => 'AmBank',
        'BANKISLAM' => 'Bank Islam',
        'TOUCHNGO'  => "Touch 'n Go",
      ];
      foreach($banks as $code => $label): ?>
        <button onclick="goToBank('<?=e($code)?>')"
          style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#fff;padding:10px 14px;border-radius:10px;font-size:.85rem;cursor:pointer;text-align:left;transition:background .2s"
          onmouseover="this.style.background='rgba(255,56,56,.15)'"
          onmouseout="this.style.background='rgba(255,255,255,.06)'">
          <i class="fas fa-university" style="color:var(--accent);margin-right:7px"></i><?=e($label)?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
// Reviewed items per order for "My Review" modal
var reviewedItemsData = <?= json_encode($jsReviewedItems) ?>;

// Order items keyed by order_id (only unreviewed products)
var orderItemsData = <?php
  $jsItems = [];
  foreach($orderItems as $oid => $rows){
    foreach($rows as $r){
      if(!isset($reviewedProductIds[(int)$r['product_id']])){
        $jsItems[$oid][] = ['product_id'=>(int)$r['product_id'],'name'=>$r['name']];
      }
    }
  }
  echo json_encode($jsItems);
?>;

function openReviewModal(orderId){
  var items = orderItemsData[orderId];
  if(!items || !items.length) return;
  document.getElementById('reviewFormOrderId').value = orderId;
  document.getElementById('reviewModalRef').textContent = 'Order #' + orderId;
  var container = document.getElementById('reviewProductsContainer');
  container.innerHTML = '';
  items.forEach(function(item){
    var pid = item.product_id;
    var block = document.createElement('div');
    block.className = 'review-product-block';
    var starHtml = '<div class="star-selector" id="stars-'+pid+'">';
    for(var i=5;i>=1;i--){
      starHtml += '<input type="radio" name="reviews['+pid+'][rating]" id="star'+i+'_'+pid+'" value="'+i+'"'+(i===5?' required':'')+'>'+
                  '<label for="star'+i+'_'+pid+'">&#9733;</label>';
    }
    starHtml += '</div>';
    block.innerHTML = '<div class="review-product-name">'+item.name+'</div>'+
      starHtml+
      '<textarea class="review-textarea" name="reviews['+pid+'][text]" placeholder="Share your thoughts about this product... (optional)" rows="3"></textarea>';
    container.appendChild(block);
  });
  document.getElementById('reviewModalOverlay').classList.add('active');
  document.body.style.overflow = 'hidden';
}
function closeReviewModal(){
  document.getElementById('reviewModalOverlay').classList.remove('active');
  document.body.style.overflow = '';
}
document.getElementById('reviewModalOverlay').addEventListener('click', function(e){
  if(e.target === this) closeReviewModal();
});

function openViewReviewModal(orderId){
  var items = reviewedItemsData[orderId];
  if(!items || !items.length) return;
  document.getElementById('viewReviewRef').textContent = 'Order #' + orderId;
  var container = document.getElementById('viewReviewContainer');
  container.innerHTML = '';
  items.forEach(function(item, idx){
    var stars = '';
    for(var i=1;i<=5;i++){
      stars += '<i class="'+(i<=item.rating?'fas':'far')+' fa-star" style="color:'+(i<=item.rating?'#f59e0b':'rgba(255,255,255,.2)')+';font-size:1rem"></i>';
    }
    var block = document.createElement('div');
    block.style.cssText = (idx>0?'border-top:1px solid rgba(255,255,255,.07);padding-top:16px;margin-top:16px':'');
    block.innerHTML =
      '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">'+
        (item.image ? '<img src="'+item.image+'" alt="" style="width:52px;height:52px;object-fit:contain;border-radius:8px;background:rgba(255,255,255,.05);flex-shrink:0">' : '')+
        '<div style="font-weight:600;font-size:.92rem;color:var(--text);line-height:1.4">'+item.name+'</div>'+
      '</div>'+
      '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">'+
        '<span style="display:flex;gap:3px">'+stars+'</span>'+
        '<span style="font-size:.78rem;color:rgba(255,255,255,.35)">'+item.date+'</span>'+
      '</div>'+
      (item.text
        ? '<div style="font-size:.84rem;color:var(--muted);line-height:1.55;background:rgba(255,255,255,.04);border-radius:8px;padding:10px 12px">'+item.text.replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</div>'
        : '<div style="font-size:.82rem;color:rgba(255,255,255,.25);font-style:italic">No written review.</div>'
      );
    container.appendChild(block);
  });
  document.getElementById('viewReviewOverlay').classList.add('active');
  document.body.style.overflow = 'hidden';
}
function closeViewReviewModal(){
  document.getElementById('viewReviewOverlay').classList.remove('active');
  document.body.style.overflow = '';
}
document.getElementById('viewReviewOverlay').addEventListener('click', function(e){
  if(e.target === this) closeViewReviewModal();
});
document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ closeReviewModal(); closeViewReviewModal(); } });

var payNowOrderId = null;
var gatewayBase   = <?=json_encode(BASE_URL.'/bank_gateway.php')?>;

function openPayNow(orderId){
  payNowOrderId = orderId;
  document.getElementById('payNowOrderRef').textContent = '#' + orderId;
  var o = document.getElementById('payNowOverlay');
  o.style.display = 'flex';
}
function closePayNow(){
  document.getElementById('payNowOverlay').style.display = 'none';
  payNowOrderId = null;
}
function goToBank(bankCode){
  if(!payNowOrderId) return;
  var url = gatewayBase + '?bank=' + bankCode + '&order_id=' + payNowOrderId;
  var gw = window.open(url, 'fpx_gateway', 'width=520,height=680,resizable=yes');
  if(!gw || gw.closed) window.location.href = url;
  closePayNow();
}
document.getElementById('payNowOverlay').addEventListener('click', function(e){
  if(e.target === this) closePayNow();
});

function confirmReturn(orderId){
  Swal.fire({
    title: 'Request a Return?',
    html: 'You are about to request a return for order <b>#' + orderId + '</b>.<br><br>Once submitted, the refund process will begin automatically.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, Return It',
    cancelButtonText: 'Not Now',
    confirmButtonColor: '#a855f7',
    cancelButtonColor: '#6b7280',
    background: '#1c0d0f',
    color: '#fff',
  }).then(function(result){
    if(result.isConfirmed){
      document.getElementById('returnFormOrderId').value = orderId;
      document.getElementById('returnForm').submit();
    }
  });
}
</script>

<?php require_once __DIR__.'/includes/footer.php'; ?>
