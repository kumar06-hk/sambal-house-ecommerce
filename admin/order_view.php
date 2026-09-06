<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
if(!isset($_SESSION['admin_id'])) redirect('./login.php');

$id  = (int)($_GET['id'] ?? 0);
$h   = $mysqli->prepare("SELECT * FROM orders WHERE id=?");
$h->bind_param('i', $id);
$h->execute();
$ord = $h->get_result()->fetch_assoc();
if(!$ord){ http_response_code(404); exit('Order not found'); }

$it    = $mysqli->prepare("SELECT name, price, qty FROM order_items WHERE order_id=?");
$it->bind_param('i', $id);
$it->execute();
$items = $it->get_result()->fetch_all(MYSQLI_ASSOC);

// Customer purchase history
$cs = $mysqli->prepare("SELECT COUNT(*) total_orders, COALESCE(SUM(total),0) total_spent, MAX(created_at) last_order FROM orders WHERE user_id=? AND status IN ('PAID','PROCESSING','SHIPPED','COMPLETED')");
$cs->bind_param('i', $ord['user_id']);
$cs->execute();
$cust_stats = $cs->get_result()->fetch_assoc();

$statusColors = [
  'PENDING'    => '#f59e0b',
  'PAID'       => '#10b981',
  'PROCESSING' => '#3b82f6',
  'SHIPPED'    => '#8b5cf6',
  'RECEIVED'   => '#22d3ee',
  'COMPLETED'  => '#10b981',
  'CANCELLED'  => '#ef4444',
  'RETURNED'   => '#a855f7',
];
$statusColor = $statusColors[$ord['status']] ?? 'var(--accent)';

$pageTitle = 'Order #'.$id;
$bodyClass = 'admin-dashboard';
require_once __DIR__.'/includes/header.php';
?>
<header class="page-header">
  <div class="page-header-inner" style="justify-content:space-between;width:100%">
    <div style="display:flex;align-items:center;gap:18px">
      <div class="ph-icon"><i class="fas fa-file-invoice"></i></div>
      <div>
        <h1>Order #<?=e($id)?></h1>
        <p>
          <span style="color:<?=$statusColor?>;font-weight:700"><?=e($ord['status'])?></span>
          &nbsp;&middot;&nbsp;
          <?=date('M j, Y · g:i A', strtotime($ord['created_at']))?>
        </p>
      </div>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <button onclick="downloadInvoice()" class="btn btn-secondary" style="border-radius:999px;padding:10px 18px;font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:7px">
        <i class="fas fa-file-arrow-down"></i> Download Invoice
      </button>
      <button onclick="window.print()" class="btn btn-secondary" style="border-radius:999px;padding:10px 18px;font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:7px">
        <i class="fas fa-print"></i> Print
      </button>
      <a href="<?=ADMIN_URL?>/orders.php" class="btn btn-secondary" style="border-radius:999px;padding:10px 20px;font-size:.85rem;font-weight:600;display:inline-flex;align-items:center;gap:8px">
        <i class="fas fa-arrow-left"></i> Back to Orders
      </a>
    </div>
  </div>
</header>

<div class="container">
  <div class="order-view-grid">

    <!-- Customer & Address -->
    <div class="ov-card">
      <div class="ov-card-header"><i class="fas fa-user"></i> Customer</div>
      <div class="ov-card-body">
        <div class="ov-row"><span class="ov-label">Name</span><span><?=e($ord['full_name'])?></span></div>
        <div class="ov-row"><span class="ov-label">Email</span><span><?=e($ord['email'])?></span></div>
        <?php if($ord['phone']): ?>
        <div class="ov-row"><span class="ov-label">Phone</span><span><?=e($ord['phone'])?></span></div>
        <?php endif; ?>
        <div class="ov-divider"></div>
        <div class="ov-row"><span class="ov-label">Address</span>
          <span>
            <?=e($ord['address_line1'])?><?= $ord['address_line2'] ? '<br>'.e($ord['address_line2']) : '' ?><br>
            <?=e($ord['city'])?>, <?=e($ord['state_region'])?> <?=e($ord['postcode'])?>
          </span>
        </div>
        <div class="ov-row"><span class="ov-label">Country</span><span><?=e($ord['country'])?></span></div>
      </div>
    </div>

    <!-- Order Summary -->
    <div class="ov-card">
      <div class="ov-card-header"><i class="fas fa-receipt"></i> Summary</div>
      <div class="ov-card-body">
        <div class="ov-row">
          <span class="ov-label">Status</span>
          <span class="order-status-badge" style="background:<?=$statusColor?>22;color:<?=$statusColor?>;border:1px solid <?=$statusColor?>55;padding:3px 12px;border-radius:20px;font-size:.78rem;font-weight:700;text-transform:uppercase"><?=e($ord['status'])?></span>
        </div>
        <div class="ov-row"><span class="ov-label">Payment</span><span><?=e($ord['payment_method'])?></span></div>
        <div class="ov-row"><span class="ov-label">Date</span><span><?=date('M j, Y g:i A', strtotime($ord['created_at']))?></span></div>
        <div class="ov-divider"></div>
        <div class="ov-row ov-total">
          <span class="ov-label">Total</span>
          <span>RM <?=number_format($ord['total'],2)?></span>
        </div>
      </div>
    </div>

    <!-- Customer Purchase Summary -->
    <div class="ov-card" style="grid-column:1/-1">
      <div class="ov-card-header"><i class="fas fa-chart-line"></i> Customer Purchase Summary</div>
      <div class="ov-card-body" style="flex-direction:row;gap:0">
        <div style="flex:1;text-align:center;padding:10px 20px;border-right:1px solid rgba(255,255,255,.06)">
          <div style="font-size:1.6rem;font-weight:900;color:var(--accent)"><?=(int)$cust_stats['total_orders']?></div>
          <div style="font-size:.78rem;color:var(--muted);margin-top:3px">Total Orders</div>
        </div>
        <div style="flex:1;text-align:center;padding:10px 20px;border-right:1px solid rgba(255,255,255,.06)">
          <div style="font-size:1.6rem;font-weight:900;color:#10b981">RM <?=number_format($cust_stats['total_spent'],2)?></div>
          <div style="font-size:.78rem;color:var(--muted);margin-top:3px">Total Spent (paid)</div>
        </div>
        <div style="flex:1;text-align:center;padding:10px 20px">
          <div style="font-size:1.1rem;font-weight:700;color:var(--text)"><?=$cust_stats['last_order'] ? date('M j, Y', strtotime($cust_stats['last_order'])) : 'N/A'?></div>
          <div style="font-size:.78rem;color:var(--muted);margin-top:3px">Most Recent Paid Order</div>
        </div>
      </div>
    </div>

  </div>

  <!-- Items Table -->
  <div class="ov-card" style="margin-top:0">
    <div class="ov-card-header"><i class="fas fa-box-open"></i> Items</div>
    <table class="table" style="border-radius:0;border:none;margin:0">
      <thead>
        <tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr>
      </thead>
      <tbody>
      <?php foreach($items as $x): ?>
        <tr>
          <td><?=e($x['name'])?></td>
          <td>RM <?=number_format($x['price'],2)?></td>
          <td><?=e($x['qty'])?></td>
          <td>RM <?=number_format($x['price']*$x['qty'],2)?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<style>
@media print {
  nav, .page-header, .site-footer { display: none !important; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
var INV_DATA = <?= json_encode([
  'id'       => $ord['id'],
  'status'   => $ord['status'],
  'date'     => date('d F Y, h:i A', strtotime($ord['created_at'])),
  'full_name'=> $ord['full_name'],
  'email'    => $ord['email'],
  'phone'    => $ord['phone'] ?? '',
  'address1' => $ord['address_line1'],
  'address2' => $ord['address_line2'] ?? '',
  'city'     => $ord['city'],
  'state'    => $ord['state_region'],
  'postcode' => $ord['postcode'],
  'country'  => $ord['country'],
  'payment'  => $ord['payment_method'],
  'total'    => (float)$ord['total'],
  'items'    => array_map(fn($i) => ['name'=>$i['name'],'qty'=>(int)$i['qty'],'price'=>(float)$i['price']], $items),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function downloadInvoice() {
  var d = INV_DATA;
  var { jsPDF } = window.jspdf;
  var doc = new jsPDF({ unit: 'mm', format: 'a4' });
  var lm = 20, rm = 190, cw = 170, y = 0;

  doc.setFillColor(26, 12, 14);
  doc.rect(0, 0, 210, 44, 'F');
  doc.setFont('helvetica', 'bold'); doc.setFontSize(20); doc.setTextColor(255, 56, 56);
  doc.text('Sambal House', 105, 17, { align: 'center' });
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(199, 174, 178);
  doc.text('Handcrafted Malaysian Chili Pastes', 105, 24, { align: 'center' });
  doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(255, 236, 239);
  doc.text('INVOICE', 105, 34, { align: 'center' });
  y = 54;

  doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(20, 20, 20);
  doc.text('Order #' + d.id, lm, y);
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(130, 130, 130);
  doc.text(d.date, rm, y, { align: 'right' });
  y += 6;

  var statusColor = d.status === 'PAID' || d.status === 'COMPLETED' ? [16,185,129] : d.status === 'CANCELLED' ? [239,68,68] : d.status === 'RETURNED' ? [168,85,247] : d.status === 'SHIPPED' ? [139,92,246] : d.status === 'RECEIVED' ? [34,211,238] : [59,130,246];
  doc.setFillColor(statusColor[0], statusColor[1], statusColor[2]);
  doc.rect(lm, y - 4, 22, 5.5, 'F');
  doc.setFont('helvetica', 'bold'); doc.setFontSize(7); doc.setTextColor(255, 255, 255);
  doc.text(d.status, lm + 11, y - 0.5, { align: 'center' });
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(80, 80, 80);
  doc.text('Payment: ' + d.payment, lm + 26, y - 0.5);
  y += 9;

  doc.setDrawColor(220, 220, 220); doc.line(lm, y, rm, y); y += 8;

  var col2 = 115;
  doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(160, 80, 80);
  doc.text('BILL TO', lm, y); doc.text('SHIP TO', col2, y); y += 5;
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(50, 50, 50);
  var billLines = [d.full_name, d.email, d.phone].filter(function(v){ return v && v.trim(); });
  var shipParts = [d.address1, d.address2, (d.city && d.state ? d.city+', '+d.state : (d.city||d.state)), d.postcode, d.country];
  var shipLines = shipParts.filter(function(v){ return v && v.trim(); });
  var startY = y, leftY = startY, rightY = startY;
  billLines.forEach(function(line){ doc.text(line.substring(0,38), lm, leftY); leftY += 4.5; });
  shipLines.forEach(function(line){ doc.text(line.substring(0,38), col2, rightY); rightY += 4.5; });
  y = Math.max(leftY, rightY) + 6;

  doc.setDrawColor(220, 220, 220); doc.line(lm, y, rm, y); y += 8;
  doc.setFillColor(245, 245, 247); doc.rect(lm, y - 5, cw, 8, 'F');
  doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(130, 130, 130);
  doc.text('ITEM', lm + 2, y); doc.text('QTY', 148, y, { align: 'right' });
  doc.text('UNIT', 163, y, { align: 'right' }); doc.text('SUBTOTAL', rm, y, { align: 'right' });
  y += 7;

  d.items.forEach(function(item, idx) {
    if (idx % 2 === 1) { doc.setFillColor(250,250,252); doc.rect(lm, y-5, cw, 7.5, 'F'); }
    doc.setFont('helvetica','bold'); doc.setFontSize(9); doc.setTextColor(30,30,30);
    doc.text(item.name.substring(0,30), lm+2, y);
    doc.setFont('helvetica','normal'); doc.setTextColor(80,80,80);
    doc.text('x'+item.qty, 148, y, { align:'right' });
    doc.text('RM '+item.price.toFixed(2), 163, y, { align:'right' });
    doc.setFont('helvetica','bold'); doc.setTextColor(40,40,40);
    doc.text('RM '+(item.qty*item.price).toFixed(2), rm, y, { align:'right' });
    y += 7.5;
  });

  y += 4; doc.setDrawColor(200,200,200); doc.line(130, y, rm, y); y += 5;
  doc.setFont('helvetica','normal'); doc.setFontSize(8.5);
  doc.setTextColor(130,130,130); doc.text('Subtotal', 163, y, { align:'right' });
  doc.setTextColor(60,60,60); doc.text('RM '+d.total.toFixed(2), rm, y, { align:'right' }); y += 5;
  doc.setTextColor(130,130,130); doc.text('Shipping', 163, y, { align:'right' });
  doc.setTextColor(76,175,80); doc.text('FREE', rm, y, { align:'right' }); y += 6;
  doc.setDrawColor(200,200,200); doc.line(130, y, rm, y); y += 6;
  doc.setFont('helvetica','bold'); doc.setFontSize(11); doc.setTextColor(30,30,30);
  doc.text('TOTAL', 163, y, { align:'right' });
  doc.setTextColor(255,56,56); doc.text('RM '+d.total.toFixed(2), rm, y, { align:'right' });
  y += 14;
  doc.setDrawColor(220,220,220); doc.line(lm, y, rm, y); y += 7;
  doc.setFont('helvetica','italic'); doc.setFontSize(8); doc.setTextColor(160,160,160);
  doc.text('Sambal House  -  Handcrafted Malaysian Chili Pastes', 105, y, { align:'center' });

  doc.save('Sambal-House-Invoice-' + d.id + '.pdf');
}
</script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
