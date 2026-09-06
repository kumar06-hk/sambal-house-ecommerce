<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

if (!is_logged_in()) {
    set_flash('error', 'Login Required', 'Please sign in to view refund status.');
    redirect(BASE_URL.'/login.php');
}

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) redirect(BASE_URL.'/orders.php');

$uid = (int)current_user_id();

// Advance refund steps before rendering — acceptance requires admin; refund auto-processes after approval
$mysqli->query("UPDATE orders SET refunded_at=DATE_ADD(cancellation_accepted_at,INTERVAL 60 SECOND) WHERE id=$orderId AND user_id=$uid AND status='CANCELLED' AND paid_at IS NOT NULL AND cancellation_accepted_at IS NOT NULL AND refunded_at IS NULL AND TIMESTAMPDIFF(SECOND,cancellation_accepted_at,NOW())>=60");

$stmt = $mysqli->prepare(
    "SELECT o.id, o.total, o.payment_method, o.full_name, o.created_at,
            o.cancelled_at, o.cancellation_accepted_at, o.refunded_at,
            o.address_line1, o.address_line2, o.city, o.state_region, o.postcode,
            TIMESTAMPDIFF(SECOND, o.cancelled_at, NOW())             AS elapsed_cancelled,
            TIMESTAMPDIFF(SECOND, o.cancellation_accepted_at, NOW()) AS elapsed_accepted
     FROM orders o WHERE o.id = ? AND o.user_id = ? AND o.status = 'CANCELLED' AND o.paid_at IS NOT NULL"
);
$stmt->bind_param('ii', $orderId, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    set_flash('error', 'Not Found', 'Order not found or not cancelled.');
    redirect(BASE_URL.'/orders.php');
}

$iStmt = $mysqli->prepare("SELECT name, qty, price FROM order_items WHERE order_id = ?");
$iStmt->bind_param('i', $orderId);
$iStmt->execute();
$orderItems = $iStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Determine refund step
$step = 0;
if ($order['cancelled_at'])             $step = 1;
if ($order['cancellation_accepted_at']) $step = 2;
if ($order['refunded_at'])              $step = 3;

$steps = [
    ['label' => 'Cancellation Requested', 'icon' => 'fa-xmark',        'time' => $order['cancelled_at']],
    ['label' => 'Cancellation Accepted',  'icon' => 'fa-circle-check', 'time' => $order['cancellation_accepted_at']],
    ['label' => 'Refunded',               'icon' => 'fa-rotate-left',  'time' => $order['refunded_at']],
];

// Countdown only for step 2→3 (refund processing); step 1→2 waits for admin approval
$nextIn = null;
if ($step === 2 && $order['elapsed_accepted'] !== null) {
    $nextIn = max(0, 60 - (int)$order['elapsed_accepted']);
}

// Deterministic request ID based on order id
$requestId = 'SMBH' . date('Ymd', strtotime($order['cancelled_at'] ?? $order['created_at']))
           . str_pad($orderId, 4, '0', STR_PAD_LEFT)
           . strtoupper(substr(md5('refund-'.$orderId), 0, 4));

$pageTitle = 'Refund Details #'.$orderId;
require_once __DIR__.'/includes/header.php';
?>
<style>
.rf-wrap {
  max-width: 680px;
  margin: 0 auto;
  padding: 20px 18px 60px;
}

/* ── Header ─────────────────────────────── */
.rf-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 24px;
}
.rf-head-left {
  display: flex;
  align-items: center;
  gap: 14px;
}
.rf-back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px; height: 38px;
  border-radius: 50%;
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.1);
  color: #fff;
  text-decoration: none;
  font-size: .95rem;
  transition: background .2s;
}
.rf-back:hover { background: rgba(255,255,255,.14); }
.rf-head h2 { font-size: 1.3rem; color: #fff; margin: 0 0 2px; }
.rf-head p  { font-size: .82rem; color: var(--muted); margin: 0; }
.rf-cancelled-badge {
  padding: 5px 14px;
  border-radius: 20px;
  font-size: .78rem; font-weight: 700;
  letter-spacing: .04em; text-transform: uppercase;
  background: rgba(249,115,22,.15);
  color: #fb923c;
  border: 1px solid rgba(249,115,22,.3);
}

/* ── Tracker card ────────────────────────── */
.rf-tracker-card {
  background: var(--card);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 18px;
  padding: 28px 20px 22px;
  margin-bottom: 14px;
  overflow-x: auto;
}

/* ── 3-step tracker ──────────────────────── */
.rf-steps {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  min-width: 360px;
  position: relative;
}
.rf-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 0 0 auto;
  width: 90px;
  text-align: center;
}
.rf-step-icon {
  width: 48px; height: 48px;
  border-radius: 50%;
  border: 2.5px solid rgba(255,255,255,.15);
  background: rgba(255,255,255,.05);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
  color: rgba(255,255,255,.3);
  transition: all .35s ease;
  position: relative; z-index: 1;
}
.rf-step.done .rf-step-icon {
  background: #f97316;
  border-color: #f97316;
  color: #fff;
  box-shadow: 0 0 0 4px rgba(249,115,22,.18), 0 4px 16px rgba(249,115,22,.3);
}
.rf-step.current .rf-step-icon {
  animation: rf-pulse 1.8s ease-in-out infinite;
}
@keyframes rf-pulse {
  0%,100% { box-shadow: 0 0 0 4px rgba(249,115,22,.18), 0 4px 16px rgba(249,115,22,.25); }
  50%      { box-shadow: 0 0 0 9px rgba(249,115,22,.1),  0 4px 24px rgba(249,115,22,.45); }
}
@keyframes rf-pop {
  0%   { transform: scale(1); }
  40%  { transform: scale(1.35); box-shadow: 0 0 0 10px rgba(249,115,22,.25), 0 6px 24px rgba(249,115,22,.5); }
  70%  { transform: scale(.92); }
  100% { transform: scale(1); }
}
.rf-step-label {
  margin-top: 10px;
  font-size: .72rem; font-weight: 600;
  color: rgba(255,255,255,.4);
  line-height: 1.3;
  transition: color .35s;
}
.rf-step.done .rf-step-label { color: #fff; }
.rf-step-time {
  margin-top: 4px;
  font-size: .65rem;
  color: var(--muted);
  min-height: 1.2em;
}
.rf-step.done .rf-step-time { color: rgba(249,115,22,.85); }

.rf-connector {
  flex: 1;
  height: 3px;
  background: rgba(255,255,255,.1);
  margin-top: 22px;
  border-radius: 2px;
  transition: background .5s ease;
  min-width: 20px;
}
.rf-connector.done { background: #f97316; }

/* ── Status hero ─────────────────────────── */
.rf-status-hero {
  background: var(--card);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 14px;
  padding: 20px 20px;
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.rf-hero-text h3 {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0 0 6px;
}
.rf-hero-text h3.complete { color: #f97316; }
.rf-hero-text h3.pending  { color: rgba(255,255,255,.75); }
.rf-hero-text p  { color: var(--muted); font-size: .86rem; margin: 0; }
.rf-hero-icon {
  width: 58px; height: 58px; flex-shrink: 0;
  border-radius: 50%;
  border: 2.5px solid #f97316;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem;
  color: #f97316;
}
.rf-hero-icon.spin { animation: rf-spin 2.5s linear infinite; }
@keyframes rf-spin { to { transform: rotate(360deg); } }

/* ── Countdown ───────────────────────────── */
.rf-countdown {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: rgba(249,115,22,.08);
  border: 1px solid rgba(249,115,22,.2);
  border-radius: 12px;
  padding: 11px 18px;
  font-size: .85rem;
  color: rgba(255,255,255,.75);
  margin-bottom: 14px;
}
.rf-countdown i { color: #f97316; }
.rf-countdown b { color: #fb923c; font-size: .95rem; }

/* ── Info card ───────────────────────────── */
.rf-card {
  background: var(--card);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 14px;
  overflow: hidden;
  margin-bottom: 12px;
}
.rf-card-head {
  display: flex; align-items: center; gap: 8px;
  padding: 11px 16px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  font-size: .85rem; font-weight: 600;
}
.rf-card-head i { color: #f97316; }
.rf-card-body { padding: 0 16px; }

.rf-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid rgba(255,255,255,.04);
  font-size: .86rem;
  gap: 12px;
}
.rf-row:last-child { border-bottom: none; }
.rf-row-label { color: var(--muted); white-space: nowrap; }
.rf-row-val   { font-weight: 500; text-align: right; }
.rf-row-val.accent { color: #f97316; }

.rf-copy-btn {
  background: none;
  border: 1px solid rgba(249,115,22,.4);
  color: #f97316;
  font-size: .72rem; font-weight: 700;
  padding: 2px 8px; border-radius: 6px;
  cursor: pointer; margin-left: 8px;
  transition: background .15s;
}
.rf-copy-btn:hover { background: rgba(249,115,22,.12); }

.rf-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid rgba(255,255,255,.04);
  font-size: .86rem;
}
.rf-item-row:last-child { border-bottom: none; }
.rf-item-name { flex: 1; }
.rf-item-qty  { color: var(--muted); margin: 0 10px; }
.rf-item-price { color: #f97316; font-weight: 600; }
.rf-total-row {
  display: flex; justify-content: space-between;
  padding: 13px 16px;
  border-top: 1px solid rgba(255,255,255,.07);
  font-weight: 700;
}
.rf-total-row span:last-child { font-size: 1.15rem; color: #f97316; }

.rf-actions {
  display: flex; gap: 10px; flex-wrap: wrap;
  margin-top: 20px;
}
</style>

<div class="rf-wrap">

  <!-- Header -->
  <div class="rf-head">
    <div class="rf-head-left">
      <a href="<?=BASE_URL?>/orders.php" class="rf-back"><i class="fas fa-arrow-left"></i></a>
      <div>
        <h2>Refund Details</h2>
        <p>Order #<?=$orderId?> &nbsp;·&nbsp; Cancelled <?=date('d M Y', strtotime($order['cancelled_at'] ?? $order['created_at']))?></p>
      </div>
    </div>
    <span class="rf-cancelled-badge">Cancelled</span>
  </div>

  <!-- 3-step tracker -->
  <div class="rf-tracker-card">
    <div class="rf-steps" id="rf-steps">
      <?php foreach($steps as $i => $s):
        $done    = $i < $step;
        $current = $i === $step - 1 && $step < 3;
      ?>
        <?php if($i > 0): ?>
          <div class="rf-connector <?= $done ? 'done' : '' ?>" id="rf-conn-<?=$i-1?>"></div>
        <?php endif; ?>
        <div class="rf-step <?= $done ? 'done' : '' ?> <?= $current ? 'current' : '' ?>" id="rf-step-<?=$i?>">
          <div class="rf-step-icon"><i class="fas <?=e($s['icon'])?>"></i></div>
          <div class="rf-step-label"><?=e($s['label'])?></div>
          <div class="rf-step-time" id="rf-step-time-<?=$i?>">
            <?= $s['time'] ? date('d M Y, g:i A', strtotime($s['time'])) : '' ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Status hero -->
  <div class="rf-status-hero" id="rf-hero">
    <div class="rf-hero-text">
      <?php if($step === 3): ?>
        <h3 class="complete">Refund Completed</h3>
        <p>RM <?=number_format((float)$order['total'],2)?> has been refunded to your Online Banking.</p>
      <?php elseif($step === 2): ?>
        <h3 class="pending">Refund Processing</h3>
        <p>Your cancellation is accepted. Refund is being processed.</p>
      <?php else: ?>
        <h3 class="pending">Awaiting Admin Approval</h3>
        <p>Your cancellation refund request is pending admin review.</p>
      <?php endif; ?>
    </div>
    <div class="rf-hero-icon <?= $step < 3 ? 'spin' : '' ?>" id="rf-hero-icon">
      <i class="fas <?= $step === 3 ? 'fa-circle-check' : 'fa-rotate' ?>"></i>
    </div>
  </div>

  <!-- Countdown -->
  <div class="rf-countdown" id="rf-countdown-wrap"
       style="display:<?= ($nextIn !== null && $nextIn > 0) ? '' : 'none' ?>">
    <i class="fas fa-clock"></i>
    <span>Next update in <b id="rf-countdown-val">
      <?php if($nextIn !== null && $nextIn > 0):
        $m = floor($nextIn/60); $s = $nextIn%60;
        echo $m.':'.str_pad($s,2,'0',STR_PAD_LEFT);
      endif; ?>
    </b></span>
  </div>

  <!-- Refund details -->
  <div class="rf-card">
    <div class="rf-card-head"><i class="fas fa-circle-info"></i> Refund Information</div>
    <div class="rf-card-body">
      <div class="rf-row">
        <span class="rf-row-label">Refund Amount</span>
        <span class="rf-row-val accent">RM <?=number_format((float)$order['total'],2)?></span>
      </div>
      <div class="rf-row">
        <span class="rf-row-label">Refund To</span>
        <span class="rf-row-val">FPX Online Banking</span>
      </div>
      <div class="rf-row">
        <span class="rf-row-label">Requested By</span>
        <span class="rf-row-val">Buyer</span>
      </div>
      <div class="rf-row">
        <span class="rf-row-label">Requested At</span>
        <span class="rf-row-val">
          <?= $order['cancelled_at'] ? date('d-m-Y H:i', strtotime($order['cancelled_at'])) : '—' ?>
        </span>
      </div>
      <div class="rf-row">
        <span class="rf-row-label">Request ID</span>
        <span class="rf-row-val" style="display:flex;align-items:center;gap:4px">
          <span id="request-id-val"><?=e($requestId)?></span>
          <button class="rf-copy-btn" onclick="copyRequestId()">COPY</button>
        </span>
      </div>
      <div class="rf-row">
        <span class="rf-row-label">Reason</span>
        <span class="rf-row-val">Cancelled by customer</span>
      </div>
    </div>
  </div>

  <!-- Items -->
  <?php if($orderItems): ?>
  <div class="rf-card">
    <div class="rf-card-head"><i class="fas fa-box"></i> Items</div>
    <div class="rf-card-body">
      <?php foreach($orderItems as $item): ?>
      <div class="rf-item-row">
        <span class="rf-item-name"><?=e($item['name'])?></span>
        <span class="rf-item-qty">× <?=(int)$item['qty']?></span>
        <span class="rf-item-price">RM <?=number_format($item['price']*$item['qty'],2)?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="rf-total-row">
      <span>Total Refunded</span>
      <span>RM <?=number_format((float)$order['total'],2)?></span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Actions -->
  <div class="rf-actions">
    <a href="<?=BASE_URL?>/orders.php" class="btn">
      <i class="fas fa-receipt"></i> My Orders
    </a>
    <a href="<?=BASE_URL?>/index.php#shop" class="btn btn-primary">
      <i class="fas fa-shopping-bag"></i> Continue Shopping
    </a>
  </div>

</div>

<script>
(function() {
  var orderId  = <?=(int)$orderId?>;
  var apiBase  = <?=json_encode(BASE_URL.'/refund_progress_api.php')?>;
  var nextIn   = <?=json_encode($nextIn)?>;
  var prevStep = <?=(int)$step?>;
  var cdTimer;
  var pollTimer;
  var total    = <?=json_encode('RM '.number_format((float)$order['total'],2))?>;

  var stepToastsRf = [
    null,
    { icon: '✅', title: 'Cancellation Accepted', text: 'Your refund is being processed.',           color: '#f97316' },
    { icon: '💰', title: 'Refund Completed!',      text: total + ' refunded to your Online Banking.', color: '#22c55e' },
  ];

  // Show toast on page load for current step
  var loadToastRf = stepToastsRf[prevStep - 1];
  if (loadToastRf) {
    setTimeout(function() {
      Swal.fire({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 4000, timerProgressBar: true,
        icon: prevStep === 3 ? 'success' : 'info',
        title: loadToastRf.icon + ' ' + loadToastRf.title,
        text: loadToastRf.text,
        background: '#1a1a1a', color: '#fff',
        iconColor: loadToastRf.color,
      });
    }, 600);
  }

  if (prevStep === 3) return;

  var heroText = [
    null,
    { title: 'Awaiting Admin Approval', body: 'Your cancellation refund request is pending admin review.' },
    { title: 'Refund Processing',        body: 'Your cancellation is accepted. Refund is being processed.' },
    { title: 'Refund Completed',         body: total + ' has been refunded to your Online Banking.' },
  ];

  function fmt(s) {
    var m = Math.floor(s / 60), sec = s % 60;
    return m + ':' + (sec < 10 ? '0' : '') + sec;
  }

  function startCountdown(secs) {
    clearInterval(cdTimer);
    var wrap = document.getElementById('rf-countdown-wrap');
    var val  = document.getElementById('rf-countdown-val');
    if (secs === null || secs <= 0) { if (wrap) wrap.style.display = 'none'; return; }
    if (wrap) wrap.style.display = '';
    var rem = Math.ceil(secs);
    if (val) val.textContent = fmt(rem);
    cdTimer = setInterval(function() {
      rem--;
      if (rem <= 0) { clearInterval(cdTimer); if (val) val.textContent = '0:00'; poll(); }
      else if (val) val.textContent = fmt(rem);
    }, 1000);
  }

  function popStep(i) {
    var el = document.getElementById('rf-step-' + i);
    if (!el) return;
    var icon = el.querySelector('.rf-step-icon');
    if (!icon) return;
    icon.style.animation = 'none';
    icon.offsetHeight;
    icon.style.animation = 'rf-pop .5s ease';
  }

  function updateHero(step) {
    var heroDiv = document.getElementById('rf-hero');
    if (!heroDiv) return;
    var t = heroText[step];
    if (!t) return;
    var titleEl = heroDiv.querySelector('.rf-hero-text h3');
    var bodyEl  = heroDiv.querySelector('.rf-hero-text p');
    var iconEl  = document.getElementById('rf-hero-icon');
    if (titleEl) {
      titleEl.textContent = t.title;
      titleEl.className   = step === 3 ? 'complete' : 'pending';
    }
    if (bodyEl) bodyEl.textContent = t.body;
    if (iconEl) {
      iconEl.className = 'rf-hero-icon' + (step < 3 ? ' spin' : '');
      var i = iconEl.querySelector('i');
      if (i) i.className = 'fas ' + (step === 3 ? 'fa-circle-check' : 'fa-rotate');
    }
  }

  function applyData(data) {
    if (!data || data.error) return;

    if (data.step > prevStep) {
      for (var i = prevStep; i < data.step; i++) {
        popStep(i);
        if (i === 1) {
          Swal.fire({ toast:true, position:'top-end', showConfirmButton:false,
            timer:4000, timerProgressBar:true, icon:'info',
            title:'✅ Cancellation Accepted', text:'Your refund is being processed.',
            background:'#1a1a1a', color:'#fff', iconColor:'#f97316' });
        }
        if (i === 2) {
          Swal.fire({ toast:true, position:'top-end', showConfirmButton:false,
            timer:5000, timerProgressBar:true, icon:'success',
            title:'💰 Refund Completed!', text: total + ' refunded to your Online Banking.',
            background:'#1a1a1a', color:'#fff', iconColor:'#22c55e' });
        }
      }
      prevStep = data.step;
    }

    // Update steps
    data.steps.forEach(function(s, i) {
      var el = document.getElementById('rf-step-' + i);
      if (!el) return;
      el.className = 'rf-step' + (s.done ? ' done' : '') + (s.current ? ' current' : '');
      var t = document.getElementById('rf-step-time-' + i);
      if (t) t.textContent = s.time_fmt || '';
      if (i > 0) {
        var conn = document.getElementById('rf-conn-' + (i-1));
        if (conn) conn.className = 'rf-connector' + (s.done ? ' done' : '');
      }
    });

    updateHero(data.step);
    startCountdown(data.next_in);

    if (data.complete) clearInterval(pollTimer);
  }

  function poll() {
    fetch(apiBase + '?order_id=' + orderId)
      .then(function(r){ return r.json(); })
      .then(applyData)
      .catch(function(){});
  }

  startCountdown(nextIn);
  if (nextIn !== null && nextIn <= 0) poll();
  pollTimer = setInterval(poll, 20000);
})();

function copyRequestId() {
  var val = document.getElementById('request-id-val');
  if (!val) return;
  navigator.clipboard.writeText(val.textContent.trim()).then(function() {
    Swal.fire({ toast:true, position:'top-end', showConfirmButton:false,
      timer:2000, icon:'success', title:'Copied!',
      background:'#1a1a1a', color:'#fff' });
  });
}
</script>

<?php require_once __DIR__.'/includes/footer.php'; ?>
