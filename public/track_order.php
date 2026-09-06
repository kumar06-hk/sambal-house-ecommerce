<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

if (!is_logged_in()) {
    set_flash('error', 'Login Required', 'Please sign in to track your order.');
    redirect(BASE_URL.'/login.php');
}

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) redirect(BASE_URL.'/orders.php');

$uid = (int)current_user_id();

// Advance this order's status before rendering so the page shows the correct state immediately
$mysqli->query("UPDATE orders SET status='SHIPPED',   shipped_at=DATE_ADD(paid_at,INTERVAL 60 SECOND)      WHERE id=$orderId AND user_id=$uid AND status IN ('PAID','PROCESSING') AND paid_at IS NOT NULL  AND TIMESTAMPDIFF(SECOND,paid_at,NOW())>=60");
$mysqli->query("UPDATE orders SET status='RECEIVED',  received_at=DATE_ADD(shipped_at,INTERVAL 60 SECOND)  WHERE id=$orderId AND user_id=$uid AND status='SHIPPED'                AND shipped_at IS NOT NULL AND TIMESTAMPDIFF(SECOND,shipped_at,NOW())>=60");
$mysqli->query("UPDATE orders SET status='COMPLETED', completed_at=DATE_ADD(received_at,INTERVAL 60 SECOND) WHERE id=$orderId AND user_id=$uid AND status='RECEIVED'              AND received_at IS NOT NULL AND TIMESTAMPDIFF(SECOND,received_at,NOW())>=60");

$stmt = $mysqli->prepare(
    "SELECT o.id, o.status, o.payment_method, o.total, o.full_name,
            o.created_at, o.paid_at, o.shipped_at, o.received_at, o.completed_at,
            o.address_line1, o.address_line2, o.city, o.state_region, o.postcode,
            TIMESTAMPDIFF(SECOND,o.paid_at,NOW())     AS elapsed_paid,
            TIMESTAMPDIFF(SECOND,o.shipped_at,NOW())  AS elapsed_shipped,
            TIMESTAMPDIFF(SECOND,o.received_at,NOW()) AS elapsed_received
     FROM orders o WHERE o.id = ? AND o.user_id = ?"
);
$stmt->bind_param('ii', $orderId, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    set_flash('error', 'Not Found', 'Order not found.');
    redirect(BASE_URL.'/orders.php');
}

$iStmt = $mysqli->prepare("SELECT name, qty, price FROM order_items WHERE order_id = ?");
$iStmt->bind_param('i', $orderId);
$iStmt->execute();
$orderItems = $iStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status = $order['status'];

$doneUpTo = match($status) {
    'PENDING'                => 0,
    'PAID', 'PROCESSING'     => 1,
    'SHIPPED'                => 2,
    'RECEIVED'               => 3,
    'COMPLETED', 'RETURNED'  => 4,
    default                  => -1,
};

$steps = [
    ['label' => 'Order Placed',     'icon' => 'fa-clipboard-list', 'time' => $order['created_at']],
    ['label' => 'Order Paid',        'icon' => 'fa-credit-card',   'time' => $order['paid_at']],
    ['label' => 'Order Shipped Out', 'icon' => 'fa-truck',          'time' => $order['shipped_at']],
    ['label' => 'Order Received',    'icon' => 'fa-box-open',       'time' => $order['received_at']],
    ['label' => 'Order Completed',   'icon' => 'fa-star',           'time' => $order['completed_at']],
];

$elapsedCols = ['PAID' => 'elapsed_paid', 'SHIPPED' => 'elapsed_shipped', 'RECEIVED' => 'elapsed_received'];
$nextIn = null;
if (isset($elapsedCols[$status])) {
    $elapsed = $order[$elapsedCols[$status]];
    if ($elapsed !== null) {
        $nextIn = max(0, 60 - (int)$elapsed);
    }
}

$pageTitle = 'Track Order #'.$orderId;
require_once __DIR__.'/includes/header.php';
?>
<style>
.to-wrap {
  max-width: 720px;
  margin: 0 auto;
  padding: 20px 18px 60px;
}

/* ── Header ─────────────────────────────── */
.to-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 24px;
}
.to-head-left {
  display: flex;
  align-items: center;
  gap: 14px;
}
.to-back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.1);
  color: #fff;
  text-decoration: none;
  font-size: .95rem;
  transition: background .2s;
}
.to-back:hover { background: rgba(255,255,255,.14); }
.to-head h2 { font-size: 1.3rem; color: #fff; margin: 0 0 2px; }
.to-head p  { font-size: .82rem; color: var(--muted); margin: 0; }

.to-status {
  padding: 5px 14px;
  border-radius: 20px;
  font-size: .78rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
}
.to-status[data-s="PENDING"]   { background:rgba(251,146,60,.15);  color:#fb923c; border:1px solid rgba(251,146,60,.3); }
.to-status[data-s="PAID"]      { background:rgba(74,222,128,.15);  color:#4ade80; border:1px solid rgba(74,222,128,.3); }
.to-status[data-s="PROCESSING"]{ background:rgba(96,165,250,.15);  color:#60a5fa; border:1px solid rgba(96,165,250,.3); }
.to-status[data-s="SHIPPED"]   { background:rgba(167,139,250,.15); color:#a78bfa; border:1px solid rgba(167,139,250,.3); }
.to-status[data-s="RECEIVED"]  { background:rgba(34,211,238,.15);  color:#22d3ee; border:1px solid rgba(34,211,238,.3); }
.to-status[data-s="COMPLETED"] { background:rgba(16,185,129,.15);  color:#10b981; border:1px solid rgba(16,185,129,.3); }
.to-status[data-s="CANCELLED"] { background:rgba(248,113,113,.15); color:#f87171; border:1px solid rgba(248,113,113,.3); }

/* ── Tracker card ────────────────────────── */
.to-tracker-card {
  background: var(--card);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 18px;
  padding: 28px 20px 22px;
  margin-bottom: 14px;
  overflow-x: auto;
}

/* ── Steps ───────────────────────────────── */
.tracker-steps {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  min-width: 480px;
  position: relative;
}

.tracker-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 0 0 auto;
  width: 80px;
  text-align: center;
}

.step-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  border: 2.5px solid rgba(255,255,255,.15);
  background: rgba(255,255,255,.05);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  color: rgba(255,255,255,.3);
  transition: all .35s ease;
  position: relative;
  z-index: 1;
}
.tracker-step.done .step-icon {
  background: #22c55e;
  border-color: #22c55e;
  color: #fff;
  box-shadow: 0 0 0 4px rgba(34,197,94,.18), 0 4px 16px rgba(34,197,94,.3);
}
.tracker-step.current .step-icon {
  animation: ring-pulse 1.8s ease-in-out infinite;
}
@keyframes ring-pulse {
  0%,100% { box-shadow: 0 0 0 4px rgba(34,197,94,.18), 0 4px 16px rgba(34,197,94,.25); }
  50%      { box-shadow: 0 0 0 9px rgba(34,197,94,.1),  0 4px 24px rgba(34,197,94,.4); }
}
@keyframes step-pop {
  0%   { transform: scale(1); }
  40%  { transform: scale(1.35); box-shadow: 0 0 0 10px rgba(34,197,94,.25), 0 6px 24px rgba(34,197,94,.5); }
  70%  { transform: scale(.92); }
  100% { transform: scale(1); }
}

.step-label {
  margin-top: 10px;
  font-size: .72rem;
  font-weight: 600;
  color: rgba(255,255,255,.45);
  line-height: 1.25;
  transition: color .35s;
}
.tracker-step.done .step-label {
  color: #fff;
}
.step-time {
  margin-top: 4px;
  font-size: .65rem;
  color: var(--muted);
  min-height: 1.2em;
  transition: color .35s;
}
.tracker-step.done .step-time {
  color: rgba(34,197,94,.8);
}

/* ── Connectors ──────────────────────────── */
.step-connector {
  flex: 1;
  height: 3px;
  background: rgba(255,255,255,.1);
  margin-top: 22px;
  border-radius: 2px;
  transition: background .5s ease;
  min-width: 20px;
}
.step-connector.done {
  background: #22c55e;
}

/* ── Countdown ───────────────────────────── */
.to-countdown {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: rgba(34,197,94,.08);
  border: 1px solid rgba(34,197,94,.2);
  border-radius: 12px;
  padding: 11px 18px;
  font-size: .85rem;
  color: rgba(255,255,255,.75);
  margin-bottom: 14px;
}
.to-countdown i { color: #22c55e; }
.to-countdown b { color: #4ade80; font-size: .95rem; }

/* ── Cancelled ───────────────────────────── */
.to-cancelled {
  text-align: center;
  padding: 40px 20px;
  background: rgba(239,68,68,.07);
  border: 1px solid rgba(239,68,68,.2);
  border-radius: 18px;
  margin-bottom: 14px;
}
.to-cancelled i { font-size: 2.8rem; color: #f87171; margin-bottom: 12px; }
.to-cancelled h3 { color: #fff; margin: 0 0 6px; }
.to-cancelled p  { color: var(--muted); margin: 0; }

/* ── Info grid ───────────────────────────── */
.to-card {
  background: var(--card);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 14px;
  overflow: hidden;
  margin-bottom: 12px;
}
.to-card-head {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 11px 16px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  font-size: .85rem;
  font-weight: 600;
}
.to-card-head i { color: var(--accent); }
.to-card-body { padding: 14px 16px; }

.to-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding: 6px 0;
  border-bottom: 1px solid rgba(255,255,255,.04);
  font-size: .86rem;
  gap: 12px;
}
.to-row:last-child { border-bottom: none; }
.to-row-label { color: var(--muted); white-space: nowrap; }
.to-row-val   { font-weight: 500; text-align: right; }

.to-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 7px 0;
  border-bottom: 1px solid rgba(255,255,255,.04);
  font-size: .86rem;
}
.to-item-row:last-child { border-bottom: none; }
.to-item-name { flex: 1; }
.to-item-qty  { color: var(--muted); margin: 0 10px; }
.to-item-price { color: var(--accent); font-weight: 600; }

.to-total {
  display: flex;
  justify-content: space-between;
  padding: 13px 16px;
  border-top: 1px solid rgba(255,255,255,.07);
  font-weight: 700;
}
.to-total-amt { font-size: 1.2rem; color: var(--accent); }

.to-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 20px;
}
</style>

<div class="to-wrap">

  <!-- Header -->
  <div class="to-head">
    <div class="to-head-left">
      <a href="<?=BASE_URL?>/orders.php" class="to-back"><i class="fas fa-arrow-left"></i></a>
      <div>
        <h2>Order #<?=$orderId?></h2>
        <p>Placed on <?=date('d M Y, g:i A', strtotime($order['created_at']))?></p>
      </div>
    </div>
    <span class="to-status" id="status-badge" data-s="<?=e($status)?>"><?=e($status)?></span>
  </div>

  <?php if($status === 'CANCELLED'): ?>
  <!-- Cancelled — redirect to refund tracker -->
  <div class="to-cancelled">
    <i class="fas fa-times-circle"></i>
    <h3>Order Cancelled</h3>
    <p style="margin-bottom:18px">This order has been cancelled. Check your refund status below.</p>
    <a href="<?=BASE_URL?>/refund_track.php?order_id=<?=$orderId?>" class="btn" style="background:#f97316;color:#fff;border:none">
      <i class="fas fa-rotate-left"></i> View Refund Details
    </a>
  </div>

  <?php else: ?>

  <!-- Tracker -->
  <div class="to-tracker-card">
    <div class="tracker-steps" id="tracker-steps">
      <?php foreach($steps as $i => $step):
        $done    = $doneUpTo >= 0 && $i <= $doneUpTo;
        $current = $i === $doneUpTo && $status !== 'COMPLETED';
      ?>
        <?php if($i > 0): ?>
          <div class="step-connector <?= $done ? 'done' : '' ?>" id="conn-<?= $i-1 ?>"></div>
        <?php endif; ?>
        <div class="tracker-step <?= $done ? 'done' : '' ?> <?= $current ? 'current' : '' ?>" id="step-<?=$i?>">
          <div class="step-icon"><i class="fas <?=e($step['icon'])?>"></i></div>
          <div class="step-label"><?=e($step['label'])?></div>
          <div class="step-time" id="step-time-<?=$i?>">
            <?= $step['time'] ? date('d M Y, g:i A', strtotime($step['time'])) : '' ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Countdown — always visible while order is in an active auto-advance state -->
  <?php
  $countdownLabel = match($status) {
    'PAID', 'PROCESSING' => '🚚 Currently Shipping Out...',
    'SHIPPED'            => '📦 Parcel on the Way...',
    'RECEIVED'           => '⭐ Order Completing Soon...',
    default              => '',
  };
  $showCountdown = $countdownLabel !== '';
  ?>
  <?php if($showCountdown): ?>
  <div class="to-countdown" id="countdown-wrap">
    <i class="fas fa-clock"></i>
    <span id="countdown-label"><?= $countdownLabel ?></span>
    <?php if($nextIn !== null && $nextIn > 0):
      $m = floor($nextIn/60); $s = $nextIn%60; ?>
      &nbsp;<b id="countdown-val"><?= $m.':'.str_pad($s,2,'0',STR_PAD_LEFT) ?></b>
    <?php else: ?>
      &nbsp;<b id="countdown-val">...</b>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php endif; // not cancelled ?>

  <!-- Items -->
  <?php if($orderItems): ?>
  <div class="to-card">
    <div class="to-card-head"><i class="fas fa-box"></i> Items Ordered</div>
    <div class="to-card-body" style="padding:0 16px">
      <?php foreach($orderItems as $item): ?>
      <div class="to-item-row">
        <span class="to-item-name"><?=e($item['name'])?></span>
        <span class="to-item-qty">× <?=(int)$item['qty']?></span>
        <span class="to-item-price">RM <?=number_format($item['price']*$item['qty'],2)?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="to-total">
      <span>Total</span>
      <span class="to-total-amt">RM <?=number_format($order['total'],2)?></span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Delivery address -->
  <div class="to-card">
    <div class="to-card-head"><i class="fas fa-map-marker-alt"></i> Delivery Address</div>
    <div class="to-card-body">
      <div class="to-row">
        <span class="to-row-label">Name</span>
        <span class="to-row-val"><?=e($order['full_name'])?></span>
      </div>
      <div class="to-row">
        <span class="to-row-label">Address</span>
        <span class="to-row-val">
          <?=e($order['address_line1'])?>
          <?php if($order['address_line2']): ?>, <?=e($order['address_line2'])?><?php endif; ?>
        </span>
      </div>
      <div class="to-row">
        <span class="to-row-label">City / State</span>
        <span class="to-row-val"><?=e($order['city'])?>, <?=e($order['state_region'])?></span>
      </div>
      <div class="to-row">
        <span class="to-row-label">Postcode</span>
        <span class="to-row-val"><?=e($order['postcode'])?></span>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div class="to-actions">
    <a href="<?=BASE_URL?>/orders.php" class="btn">
      <i class="fas fa-receipt"></i> My Orders
    </a>
    <?php if(in_array($status, ['PAID','PROCESSING','SHIPPED','RECEIVED','COMPLETED'])): ?>
    <a href="<?=BASE_URL?>/receipt.php?id=<?=$orderId?>" class="btn btn-primary">
      <i class="fas fa-file-arrow-down"></i> Receipt
    </a>
    <?php endif; ?>
  </div>

</div>

<script>
(function() {
  var orderId    = <?=(int)$orderId?>;
  var apiBase    = <?=json_encode(BASE_URL.'/order_progress_api.php')?>;
  var nextIn     = <?=json_encode($nextIn)?>;
  var prevDoneUpTo = <?=json_encode($doneUpTo)?>;
  var cdTimer;
  var pollTimer;
  var isDone     = <?=json_encode(in_array($status, ['COMPLETED','CANCELLED']))?>;

  var stepToasts = [
    null,
    null,
    { icon: '🚚', title: 'Order Shipped Out!',  text: 'Your order is on its way.' },
    { icon: '📦', title: 'Order Received!',      text: 'Your order has been delivered.' },
    { icon: '⭐', title: 'Order Completed!',     text: 'Thanks for shopping with Sambal House!' },
  ];

  // Show toast on page load for current step so you always see a notification when you open the tracker
  var loadToast = stepToasts[prevDoneUpTo];
  if (loadToast) {
    setTimeout(function() {
      Swal.fire({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 4000, timerProgressBar: true,
        icon: 'success',
        title: loadToast.icon + ' ' + loadToast.title,
        text: loadToast.text,
        background: '#1a1a1a', color: '#fff',
        iconColor: '#22c55e',
      });
    }, 600);
  }

  if (isDone) return;

  // Label shown in the countdown bar at each active status
  var countdownLabels = {
    'PAID':       '🚚 Currently Shipping Out...',
    'PROCESSING': '🚚 Currently Shipping Out...',
    'SHIPPED':    '📦 Parcel on the Way...',
    'RECEIVED':   '⭐ Order Completing Soon...',
  };

  function fmt(seconds) {
    var m = Math.floor(seconds / 60);
    var s = seconds % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  function startCountdown(secs) {
    clearInterval(cdTimer);
    var cdVal = document.getElementById('countdown-val');
    if (secs === null || secs <= 0) {
      if (cdVal) cdVal.textContent = '...';
      return;
    }
    var remaining = Math.ceil(secs);
    if (cdVal) cdVal.textContent = fmt(remaining);

    cdTimer = setInterval(function() {
      remaining--;
      if (remaining <= 0) {
        clearInterval(cdTimer);
        if (cdVal) cdVal.textContent = '...';
        poll();
      } else {
        if (cdVal) cdVal.textContent = fmt(remaining);
      }
    }, 1000);
  }

  function popStep(i) {
    var el = document.getElementById('step-' + i);
    if (!el) return;
    var icon = el.querySelector('.step-icon');
    if (!icon) return;
    icon.style.animation = 'none';
    icon.offsetHeight; // reflow
    icon.style.animation = 'step-pop .5s ease';
  }

  function applyData(data) {
    if (!data || data.error) return;

    // Detect newly completed steps and show toast
    if (data.done_up_to > prevDoneUpTo) {
      for (var i = prevDoneUpTo + 1; i <= data.done_up_to; i++) {
        popStep(i);
        var t = stepToasts[i];
        if (t) {
          Swal.fire({
            toast: true, position: 'top-end', showConfirmButton: false,
            timer: 4000, timerProgressBar: true,
            icon: 'success',
            title: t.icon + ' ' + t.title,
            text: t.text,
            background: '#1a1a1a', color: '#fff',
            iconColor: '#22c55e',
          });
        }
      }
      prevDoneUpTo = data.done_up_to;
    }

    // Update status badge
    var badge = document.getElementById('status-badge');
    if (badge) {
      badge.textContent = data.status;
      badge.setAttribute('data-s', data.status);
    }

    // Update steps and connectors
    data.steps.forEach(function(step, i) {
      var el = document.getElementById('step-' + i);
      if (!el) return;
      el.className = 'tracker-step' +
        (step.done    ? ' done'    : '') +
        (step.current ? ' current' : '');

      var timeEl = document.getElementById('step-time-' + i);
      if (timeEl) timeEl.textContent = step.time_fmt || '';

      if (i > 0) {
        var conn = document.getElementById('conn-' + (i - 1));
        if (conn) conn.className = 'step-connector' + (step.done ? ' done' : '');
      }
    });

    // Update countdown label to match current status
    var lbl = document.getElementById('countdown-label');
    if (lbl && countdownLabels[data.status]) {
      lbl.textContent = countdownLabels[data.status];
    }

    startCountdown(data.next_in);

    if (data.status === 'COMPLETED' || data.status === 'CANCELLED') {
      clearInterval(pollTimer);
      clearInterval(cdTimer);
      var cdWrap = document.getElementById('countdown-wrap');
      if (cdWrap) cdWrap.style.display = 'none';
    }
  }

  function poll() {
    fetch(apiBase + '?order_id=' + orderId)
      .then(function(r) { return r.json(); })
      .then(applyData)
      .catch(function() {});
  }

  // Start countdown from server-rendered value
  // If nextIn is 0 the status is overdue — poll immediately to advance it
  if (nextIn !== null && nextIn <= 0) {
    poll();
  } else {
    startCountdown(nextIn);
  }

  // Background poll every 20 seconds as safety net
  pollTimer = setInterval(poll, 20000);
})();
</script>

<?php require_once __DIR__.'/includes/footer.php'; ?>
