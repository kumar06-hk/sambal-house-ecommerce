<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
if(!isset($_SESSION['admin_id'])) redirect('./login.php');

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  $id = (int)($_POST['id'] ?? 0);

  if(isset($_POST['approve_return'])){
    $upd = $mysqli->prepare("UPDATE orders SET return_accepted_at=NOW() WHERE id=? AND status='RETURNED' AND return_accepted_at IS NULL");
    $upd->bind_param('i', $id);
    $upd->execute();
    redirect(ADMIN_URL.'/orders.php');
  }

  if(isset($_POST['approve_refund'])){
    $upd = $mysqli->prepare("UPDATE orders SET cancellation_accepted_at=NOW() WHERE id=? AND status='CANCELLED' AND paid_at IS NOT NULL AND cancellation_accepted_at IS NULL");
    $upd->bind_param('i', $id);
    $upd->execute();
    redirect(ADMIN_URL.'/orders.php');
  }

  $status = $_POST['status'] ?? 'PENDING';
  $allowed = ['PENDING','PAID','PROCESSING','SHIPPED','RECEIVED','COMPLETED','CANCELLED','RETURNED'];
  if(in_array($status, $allowed, true)){
    // Never allow editing CANCELLED or RETURNED orders
    $stmt = $mysqli->prepare("UPDATE orders SET status=? WHERE id=? AND status NOT IN ('CANCELLED','RETURNED')");
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
  }
}

$rows = $mysqli->query("SELECT id, full_name, email, status, total, created_at, paid_at, return_accepted_at, cancellation_accepted_at FROM orders ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Orders';
$bodyClass = 'admin-orders';
require_once __DIR__.'/includes/header.php';
?>
<header class="page-header">
  <div class="page-header-inner" style="justify-content:space-between;width:100%">
    <div style="display:flex;align-items:center;gap:18px">
      <div class="ph-icon"><i class="fas fa-shopping-cart"></i></div>
      <div><h1>Orders</h1><p>Process customer orders &amp; update status</p></div>
    </div>
    <div id="liveIndicator" style="display:flex;align-items:center;gap:8px;font-size:.8rem;color:rgba(255,255,255,.45)">
      <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block;animation:livePulse 2s ease-in-out infinite"></span>
      Live &nbsp;·&nbsp; refreshing in <b id="liveCountdown" style="color:#fff;min-width:18px;display:inline-block">15</b>s
    </div>
  </div>
</header>
<style>
@keyframes livePulse{0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(34,197,94,.5)}50%{opacity:.7;box-shadow:0 0 0 5px rgba(34,197,94,0)}}
</style>
<div class="container">
  <table class="table">
    <thead>
      <tr><th>#</th><th>Customer</th><th>Email</th><th>Status</th><th>Total</th><th>Date</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?=e($r['id'])?></td>
        <td><?=e($r['full_name'])?></td>
        <td><?=e($r['email'])?></td>
        <td>
          <?php if($r['status'] === 'CANCELLED'): ?>
            <span style="display:inline-block;background:rgba(244,67,54,.15);color:#f87171;border:1px solid rgba(244,67,54,.35);padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;letter-spacing:.5px">
              CANCELLED
            </span>
          <?php elseif($r['status'] === 'RETURNED'): ?>
            <span style="display:inline-block;background:rgba(168,85,247,.15);color:#c084fc;border:1px solid rgba(168,85,247,.35);padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;letter-spacing:.5px">
              RETURNED
            </span>
          <?php else: ?>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?=$r['id']?>">
              <select name="status" onchange="this.form.submit()">
                <?php foreach(['PENDING','PAID','PROCESSING','SHIPPED','RECEIVED','COMPLETED'] as $s): ?>
                  <option value="<?=$s?>" <?=$s===$r['status']?'selected':''?>><?=$s?></option>
                <?php endforeach; ?>
              </select>
            </form>
          <?php endif; ?>
        </td>
        <td>RM <?=number_format($r['total'],2)?></td>
        <td><?=e($r['created_at'])?></td>
        <td style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <a class="btn" href="<?=ADMIN_URL?>/order_view.php?id=<?=$r['id']?>">View</a>
          <?php if($r['status']==='RETURNED' && $r['return_accepted_at'] === null): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Approve return for order #<?=(int)$r['id']?>? This will begin the refund process.')">
            <?=csrf_field()?>
            <input type="hidden" name="id" value="<?=(int)$r['id']?>">
            <button type="submit" name="approve_return" value="1"
              style="background:linear-gradient(135deg,#0c4a6e,#0891b2);color:#fff;border:none;padding:6px 14px;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer">
              <i class="fas fa-circle-check"></i> Approve Return
            </button>
          </form>
          <?php elseif($r['status']==='RETURNED' && $r['return_accepted_at'] !== null): ?>
          <span style="font-size:.75rem;color:#22d3ee;font-weight:600"><i class="fas fa-check"></i> Approved</span>
          <?php endif; ?>
          <?php if($r['status']==='CANCELLED' && $r['paid_at'] !== null && $r['cancellation_accepted_at'] === null): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Approve refund for order #<?=(int)$r['id']?>? This will begin the refund process.')">
            <?=csrf_field()?>
            <input type="hidden" name="id" value="<?=(int)$r['id']?>">
            <button type="submit" name="approve_refund" value="1"
              style="background:linear-gradient(135deg,#78350f,#f97316);color:#fff;border:none;padding:6px 14px;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer">
              <i class="fas fa-rotate-left"></i> Approve Refund
            </button>
          </form>
          <?php elseif($r['status']==='CANCELLED' && $r['paid_at'] !== null && $r['cancellation_accepted_at'] !== null): ?>
          <span style="font-size:.75rem;color:#f97316;font-weight:600"><i class="fas fa-check"></i> Refund Approved</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script>
(function(){
  var pollUrl      = <?=json_encode(ADMIN_URL.'/orders_poll.php')?>;
  var snapshot     = { count: <?=count($rows)?>, max_id: <?=$rows ? (int)max(array_column($rows,'id')) : 0?>, pending_returns: <?=(int)count(array_filter($rows, fn($r)=>$r['status']==='RETURNED'&&$r['return_accepted_at']===null))?> };
  var INTERVAL     = 15;
  var cdEl         = document.getElementById('liveCountdown');
  var remaining    = INTERVAL;

  function tick(){
    remaining--;
    if(cdEl) cdEl.textContent = remaining;
    if(remaining <= 0){ remaining = INTERVAL; poll(); }
  }

  function poll(){
    fetch(pollUrl)
      .then(function(r){ return r.json(); })
      .then(function(data){
        if(data.error) return;
        var newOrder   = data.max_id   > snapshot.max_id;
        var newReturn  = data.pending_returns > snapshot.pending_returns;
        if(newOrder || newReturn){
          if(newOrder){
            Swal.fire({ toast:true, position:'top-end', showConfirmButton:false,
              timer:3000, icon:'info', title:'🛒 New order received!',
              background:'#1a0c0e', color:'#fff' });
          }
          if(newReturn){
            Swal.fire({ toast:true, position:'top-end', showConfirmButton:false,
              timer:3000, icon:'warning', title:'↩️ New return request!',
              background:'#1a0c0e', color:'#fff' });
          }
          setTimeout(function(){ location.reload(); }, 1800);
        }
      })
      .catch(function(){});
  }

  setInterval(tick, 1000);
  poll();
})();
</script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
