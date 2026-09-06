<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

if (!is_logged_in()) {
    set_flash('error', 'Login Required', 'Please sign in to view return status.');
    redirect(BASE_URL.'/login.php');
}

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) redirect(BASE_URL.'/orders.php');

$uid = current_user_id();

// Advance return steps before rendering — only refund step auto-advances (acceptance requires admin)
$mysqli->query("UPDATE orders SET return_refunded_at=DATE_ADD(return_accepted_at,INTERVAL 60 SECOND) WHERE id=$orderId AND user_id=$uid AND status='RETURNED' AND return_accepted_at IS NOT NULL AND return_refunded_at IS NULL AND TIMESTAMPDIFF(SECOND,return_accepted_at,NOW())>=60");

// Verify order belongs to user and is RETURNED
$check = $mysqli->prepare(
    "SELECT id FROM orders WHERE id = ? AND user_id = ? AND status = 'RETURNED'"
);
$check->bind_param('ii', $orderId, $uid);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    set_flash('error', 'Not Found', 'Order not found or no return has been requested.');
    redirect(BASE_URL.'/orders.php');
}

// Fetch order state
$stmt = $mysqli->prepare(
    "SELECT o.id, o.total, o.payment_method, o.full_name, o.created_at,
            o.return_requested_at, o.return_accepted_at, o.return_refunded_at,
            TIMESTAMPDIFF(SECOND, o.return_requested_at, NOW()) AS elapsed_requested,
            TIMESTAMPDIFF(SECOND, o.return_accepted_at,  NOW()) AS elapsed_accepted
     FROM orders o WHERE o.id = ? AND o.user_id = ? AND o.status = 'RETURNED'"
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

// Determine step
$step = 0;
if ($order['return_requested_at']) $step = 1;
if ($order['return_accepted_at'])  $step = 2;
if ($order['return_refunded_at'])  $step = 3;

$steps = [
    ['label' => 'Return Requested', 'icon' => 'fa-rotate-left',  'time' => $order['return_requested_at']],
    ['label' => 'Return Accepted',  'icon' => 'fa-circle-check', 'time' => $order['return_accepted_at']],
    ['label' => 'Refunded',         'icon' => 'fa-wallet',       'time' => $order['return_refunded_at']],
];

// Countdown only for step 2→3 (refund processing after admin approval); step 1→2 waits for admin
$nextIn = null;
if ($step === 2 && $order['elapsed_accepted'] !== null) {
    $nextIn = max(0, 60 - (int)$order['elapsed_accepted']);
}

// Deterministic request ID for this return
$requestId = 'SMBH' . date('Ymd', strtotime($order['return_requested_at']))
           . str_pad($orderId, 4, '0', STR_PAD_LEFT)
           . strtoupper(substr(md5('return-'.$orderId), 0, 4));

$pageTitle = 'Return Details #'.$orderId;
require_once __DIR__.'/includes/header.php';
?>
<style>
.rt-wrap {
  max-width: 680px;
  margin: 0 auto;
  padding: 20px 18px 60px;
}

/* ── Header ─────────────────────────────── */
.rt-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 24px;
}
.rt-head-left {
  display: flex;
  align-items: center;
  gap: 14px;
}
.rt-back {
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
.rt-back:hover { background: rgba(255,255,255,.14); }
.rt-head h2 { font-size: 1.3rem; color: #fff; margin: 0 0 2px; }
.rt-head p  { font-size: .82rem; color: var(--muted); margin: 0; }
.rt-completed-badge {
  padding: 5px 14px;
  border-radius: 20px;
  font-size: .78rem; font-weight: 700;
  letter-spacing: .04em; text-transform: uppercase;
  background: rgba(34,211,238,.12);
  color: #22d3ee;
  border: 1px solid rgba(34,211,238,.3);
}

/* ── Tracker card ────────────────────────── */
.rt-tracker-card {
  background: var(--card);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 18px;
  padding: 28px 20px 22px;
  margin-bottom: 14px;
  overflow-x: auto;
}

/* ── 3-step tracker ──────────────────────── */
.rt-steps {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  min-width: 360px;
  position: relative;
}
.rt-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 0 0 auto;
  width: 90px;
  text-align: center;
}
.rt-step-icon {
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
.rt-step.done .rt-step-icon {
  background: #22d3ee;
  border-color: #22d3ee;
  color: #0e1a1c;
  box-shadow: 0 0 0 4px rgba(34,211,238,.18), 0 4px 16px rgba(34,211,238,.3);
}
.rt-step.current .rt-step-icon {
  animation: rt-pulse 1.8s ease-in-out infinite;
}
@keyframes rt-pulse {
  0%,100% { box-shadow: 0 0 0 4px rgba(34,211,238,.18), 0 4px 16px rgba(34,211,238,.25); }
  50%      { box-shadow: 0 0 0 9px rgba(34,211,238,.1),  0 4px 24px rgba(34,211,238,.45); }
}
@keyframes rt-pop {
  0%   { transform: scale(1); }
  40%  { transform: scale(1.35); box-shadow: 0 0 0 10px rgba(34,211,238,.25), 0 6px 24px rgba(34,211,238,.5); }
  70%  { transform: scale(.92); }
  100% { transform: scale(1); }
}
.rt-step-label {
  margin-top: 10px;
  font-size: .72rem; font-weight: 600;
  color: rgba(255,255,255,.4);
  line-height: 1.3;
  transition: color .35s;
}
.rt-step.done .rt-step-label { color: #fff; }
.rt-step-time {
  margin-top: 4px;
  font-size: .65rem;
  color: var(--muted);
  min-height: 1.2em;
}
.rt-step.done .rt-step-time { color: rgba(34,211,238,.85); }

.rt-connector {
  flex: 1;
  height: 3px;
  background: rgba(255,255,255,.1);
  margin-top: 22px;
  border-radius: 2px;
  transition: background .5s ease;
  min-width: 20px;
}
.rt-connector.done { background: #22d3ee; }

/* ── Status hero ─────────────────────────── */
.rt-status-hero {
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
.rt-hero-text h3 {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0 0 6px;
}
.rt-hero-text h3.complete { color: #22d3ee; }
.rt-hero-text h3.pending  { color: rgba(255,255,255,.75); }
.rt-hero-text p  { color: var(--muted); font-size: .86rem; margin: 0; }
.rt-hero-icon {
  width: 58px; height: 58px; flex-shrink: 0;
  border-radius: 50%;
  border: 2.5px solid #22d3ee;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem;
  color: #22d3ee;
}
.rt-hero-icon.spin { animation: rt-spin 2.5s linear infinite; }
@keyframes rt-spin { to { transform: rotate(360deg); } }

/* ── Countdown ───────────────────────────── */
.rt-countdown {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: rgba(34,211,238,.08);
  border: 1px solid rgba(34,211,238,.2);
  border-radius: 12px;
  padding: 11px 18px;
  font-size: .85rem;
  color: rgba(255,255,255,.75);
  margin-bottom: 14px;
}
.rt-countdown i { color: #22d3ee; }
.rt-countdown b { color: #67e8f9; font-size: .95rem; }

/* ── Info card ───────────────────────────── */
.rt-card {
  background: var(--card);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 14px;
  overflow: hidden;
  margin-bottom: 12px;
}
.rt-card-head {
  display: flex; align-items: center; gap: 8px;
  padding: 11px 16px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  font-size: .85rem; font-weight: 600;
}
.rt-card-head i { color: #22d3ee; }
.rt-card-body { padding: 0 16px; }

.rt-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid rgba(255,255,255,.04);
  font-size: .86rem;
  gap: 12px;
}
.rt-row:last-child { border-bottom: none; }
.rt-row-label { color: var(--muted); white-space: nowrap; }
.rt-row-val   { font-weight: 500; text-align: right; }
.rt-row-val.accent { color: #22d3ee; }

.rt-copy-btn {
  background: none;
  border: 1px solid rgba(34,211,238,.4);
  color: #22d3ee;
  font-size: .72rem; font-weight: 700;
  padding: 2px 8px; border-radius: 6px;
  cursor: pointer; margin-left: 8px;
  transition: background .15s;
}
.rt-copy-btn:hover { background: rgba(34,211,238,.12); }

.rt-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid rgba(255,255,255,.04);
  font-size: .86rem;
}
.rt-item-row:last-child { border-bottom: none; }
.rt-item-name { flex: 1; }
.rt-item-qty  { color: var(--muted); margin: 0 10px; }
.rt-item-price { color: #22d3ee; font-weight: 600; }
.rt-total-row {
  display: flex; justify-content: space-between;
  padding: 13px 16px;
  border-top: 1px solid rgba(255,255,255,.07);
  font-weight: 700;
}
.rt-total-row span:last-child { font-size: 1.15rem; color: #22d3ee; }

.rt-actions {
  display: flex; gap: 10px; flex-wrap: wrap;
  margin-top: 20px;
}
</style>

<div class="rt-wrap">

  <!-- Header -->
  <div class="rt-head">
    <div class="rt-head-left">
      <a href="<?=BASE_URL?>/orders.php" class="rt-back"><i class="fas fa-arrow-left"></i></a>
      <div>
        <h2>Return Details</h2>
        <p>Order #<?=$orderId?> &nbsp;·&nbsp; Completed <?=date('d M Y', strtotime($order['created_at']))?></p>
      </div>
    </div>
    <span class="rt-completed-badge">Return Requested</span>
  </div>

  <!-- 3-step tracker -->
  <div class="rt-tracker-card">
    <div class="rt-steps" id="rt-steps">
      <?php foreach($steps as $i => $s):
        $done    = $i < $step;
        $current = $i === $step - 1 && $step < 3;
      ?>
        <?php if($i > 0): ?>
          <div class="rt-connector <?= $done ? 'done' : '' ?>" id="rt-conn-<?=$i-1?>"></div>
        <?php endif; ?>
        <div class="rt-step <?= $done ? 'done' : '' ?> <?= $current ? 'current' : '' ?>" id="rt-step-<?=$i?>">
          <div class="rt-step-icon"><i class="fas <?=e($s['icon'])?>"></i></div>
          <div class="rt-step-label"><?=e($s['label'])?></div>
          <div class="rt-step-time" id="rt-step-time-<?=$i?>">
            <?= $s['time'] ? date('d M Y, g:i A', strtotime($s['time'])) : '' ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Status hero -->
  <div class="rt-status-hero" id="rt-hero">
    <div class="rt-hero-text">
      <?php if($step === 3): ?>
        <h3 class="complete">Refund Completed</h3>
        <p>RM <?=number_format((float)$order['total'],2)?> has been refunded to your Online Banking.</p>
      <?php elseif($step === 2): ?>
        <h3 class="pending">Return Accepted</h3>
        <p>Your return is accepted. Refund is being processed.</p>
      <?php else: ?>
        <h3 class="pending">Awaiting Admin Review</h3>
        <p>Your return request has been submitted and is pending admin approval.</p>
      <?php endif; ?>
    </div>
    <div class="rt-hero-icon <?= $step < 3 ? 'spin' : '' ?>" id="rt-hero-icon">
      <i class="fas <?= $step === 3 ? 'fa-circle-check' : 'fa-rotate' ?>"></i>
    </div>
  </div>

  <!-- Countdown -->
  <div class="rt-countdown" id="rt-countdown-wrap"
       style="display:<?= ($nextIn !== null && $nextIn > 0) ? '' : 'none' ?>">
    <i class="fas fa-clock"></i>
    <span>Next update in <b id="rt-countdown-val">
      <?php if($nextIn !== null && $nextIn > 0):
        $m = floor($nextIn/60); $s = $nextIn%60;
        echo $m.':'.str_pad($s,2,'0',STR_PAD_LEFT);
      endif; ?>
    </b></span>
  </div>

  <!-- Return details -->
  <div class="rt-card">
    <div class="rt-card-head"><i class="fas fa-circle-info"></i> Return Information</div>
    <div class="rt-card-body">
      <div class="rt-row">
        <span class="rt-row-label">Refund Amount</span>
        <span class="rt-row-val accent">RM <?=number_format((float)$order['total'],2)?></span>
      </div>
      <div class="rt-row">
        <span class="rt-row-label">Refund To</span>
        <span class="rt-row-val">FPX Online Banking</span>
      </div>
      <div class="rt-row">
        <span class="rt-row-label">Requested By</span>
        <span class="rt-row-val">Buyer</span>
      </div>
      <div class="rt-row">
        <span class="rt-row-label">Requested At</span>
        <span class="rt-row-val">
          <?= $order['return_requested_at'] ? date('d-m-Y H:i', strtotime($order['return_requested_at'])) : '—' ?>
        </span>
      </div>
      <div class="rt-row">
        <span class="rt-row-label">Request ID</span>
        <span class="rt-row-val" style="display:flex;align-items:center;gap:4px">
          <span id="request-id-val"><?=e($requestId)?></span>
          <button class="rt-copy-btn" onclick="copyRequestId()">COPY</button>
        </span>
      </div>
      <div class="rt-row">
        <span class="rt-row-label">Reason</span>
        <span class="rt-row-val">Change of mind / return by customer</span>
      </div>
    </div>
  </div>

  <!-- Items -->
  <?php if($orderItems): ?>
  <div class="rt-card">
    <div class="rt-card-head"><i class="fas fa-box"></i> Items</div>
    <div class="rt-card-body">
      <?php foreach($orderItems as $item): ?>
      <div class="rt-item-row">
        <span class="rt-item-name"><?=e($item['name'])?></span>
        <span class="rt-item-qty">× <?=(int)$item['qty']?></span>
        <span class="rt-item-price">RM <?=number_format($item['price']*$item['qty'],2)?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="rt-total-row">
      <span>Total Refunded</span>
      <span>RM <?=number_format((float)$order['total'],2)?></span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Actions -->
  <div class="rt-actions">
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
  var apiBase  = <?=json_encode(BASE_URL.'/return_progress_api.php')?>;
  var nextIn   = <?=json_encode($nextIn)?>;
  var prevStep = <?=(int)$step?>;
  var cdTimer;
  var pollTimer;
  var total    = <?=json_encode('RM '.number_format((float)$order['total'],2))?>;

  var stepToastsRt = [
    null,
    { icon: '✅', title: 'Return Accepted',   text: 'Your refund is being processed.',           color: '#22d3ee' },
    { icon: '💰', title: 'Refund Completed!', text: total + ' refunded to your Online Banking.', color: '#22c55e' },
  ];

  // Show toast on page load for current step
  var loadToastRt = stepToastsRt[prevStep - 1];
  if (loadToastRt) {
    setTimeout(function() {
      Swal.fire({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 4000, timerProgressBar: true,
        icon: prevStep === 3 ? 'success' : 'info',
        title: loadToastRt.icon + ' ' + loadToastRt.title,
        text: loadToastRt.text,
        background: '#1a1a1a', color: '#fff',
        iconColor: loadToastRt.color,
      });
    }, 600);
  }

  if (prevStep === 3) return;

  var heroText = [
    null,
    { title: 'Awaiting Admin Review', body: 'Your return request has been submitted and is pending admin approval.' },
    { title: 'Return Accepted',       body: 'Your return is accepted. Refund is being processed.' },
    { title: 'Refund Completed',      body: total + ' has been refunded to your Online Banking.' },
  ];

  function fmt(s) {
    var m = Math.floor(s / 60), sec = s % 60;
    return m + ':' + (sec < 10 ? '0' : '') + sec;
  }

  function startCountdown(secs) {
    clearInterval(cdTimer);
    var wrap = document.getElementById('rt-countdown-wrap');
    var val  = document.getElementById('rt-countdown-val');
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
    var el = document.getElementById('rt-step-' + i);
    if (!el) return;
    var icon = el.querySelector('.rt-step-icon');
    if (!icon) return;
    icon.style.animation = 'none';
    icon.offsetHeight;
    icon.style.animation = 'rt-pop .5s ease';
  }

  function updateHero(step) {
    var heroDiv = document.getElementById('rt-hero');
    if (!heroDiv) return;
    var t = heroText[step];
    if (!t) return;
    var titleEl = heroDiv.querySelector('.rt-hero-text h3');
    var bodyEl  = heroDiv.querySelector('.rt-hero-text p');
    var iconEl  = document.getElementById('rt-hero-icon');
    if (titleEl) {
      titleEl.textContent = t.title;
      titleEl.className   = step === 3 ? 'complete' : 'pending';
    }
    if (bodyEl) bodyEl.textContent = t.body;
    if (iconEl) {
      iconEl.className = 'rt-hero-icon' + (step < 3 ? ' spin' : '');
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
            title:'✅ Return Accepted', text:'Your refund is being processed.',
            background:'#1a1a1a', color:'#fff', iconColor:'#22d3ee' });
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

    data.steps.forEach(function(s, i) {
      var el = document.getElementById('rt-step-' + i);
      if (!el) return;
      el.className = 'rt-step' + (s.done ? ' done' : '') + (s.current ? ' current' : '');
      var t = document.getElementById('rt-step-time-' + i);
      if (t) t.textContent = s.time_fmt || '';
      if (i > 0) {
        var conn = document.getElementById('rt-conn-' + (i-1));
        if (conn) conn.className = 'rt-connector' + (s.done ? ' done' : '');
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
