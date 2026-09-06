<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

if (!is_logged_in()) {
    set_flash('error', 'Login Required', 'Please sign in to view your receipt.');
    redirect(BASE_URL.'/login.php');
}

$uid = current_user_id();
$oid = (int)($_GET['id'] ?? 0);
if (!$oid) redirect(BASE_URL.'/orders.php');

$q = $mysqli->prepare("
    SELECT id, full_name, email, phone,
           address_line1, address_line2, city, state_region,
           postcode, country, payment_method, status, total, created_at
    FROM orders
    WHERE id = ? AND user_id = ? AND status IN ('PAID','PROCESSING','SHIPPED','COMPLETED')
");
$q->bind_param('ii', $oid, $uid);
$q->execute();
$order = $q->get_result()->fetch_assoc();

if (!$order) {
    set_flash('error', 'Not Found', 'Receipt not found for this order.');
    redirect(BASE_URL.'/orders.php');
}

$iq = $mysqli->prepare("SELECT name, qty, price FROM order_items WHERE order_id = ?");
$iq->bind_param('i', $oid);
$iq->execute();
$items = $iq->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Receipt #' . $oid;
require_once __DIR__.'/includes/header.php';
?>
<style>
.receipt-wrapper { max-width: 720px; margin: 0 auto; padding: 0 16px 48px; }
.receipt-actions {
  display: flex; gap: 10px; margin-bottom: 22px; flex-wrap: wrap; align-items: center;
}
.receipt-actions a,
.receipt-actions button {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 10px 20px; border-radius: 999px; font-size: .88rem;
  font-weight: 600; cursor: pointer; text-decoration: none;
  border: none; transition: opacity .15s;
}
.receipt-actions a:hover, .receipt-actions button:hover { opacity: .82; }
.btn-back    { background: rgba(255,255,255,.08); color: var(--text); }
.btn-dl      { background: var(--accent); color: #fff; }
.btn-print   { background: rgba(255,255,255,.08); color: var(--text); }

#receipt-card {
  background: #fff;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 24px 60px rgba(0,0,0,.45);
  color: #222;
  font-family: ui-sans-serif, system-ui, Arial, sans-serif;
}
.rc-head {
  background: #1a0c0e;
  padding: 26px 36px;
  text-align: center;
}
.rc-head-brand {
  font-size: 1.5rem; font-weight: 900; color: #ff3838; margin-bottom: 4px;
}
.rc-head-sub { color: #c7aeb2; font-size: .8rem; margin-bottom: 10px; }
.rc-head-label {
  display: inline-block;
  color: #ffecef; font-size: .78rem; font-weight: 700;
  letter-spacing: 3px; text-transform: uppercase;
  background: rgba(255,56,56,.18); padding: 4px 14px; border-radius: 20px;
}
.rc-body { padding: 28px 36px; }
.rc-meta {
  display: flex; justify-content: space-between; align-items: flex-start;
  margin-bottom: 18px;
}
.rc-order-num { font-size: 1.3rem; font-weight: 800; color: #111; margin-bottom: 3px; }
.rc-date      { color: #888; font-size: .84rem; }
.rc-paid-badge {
  background: #4caf50; color: #fff; padding: 4px 14px;
  border-radius: 20px; font-size: .82rem; font-weight: 700; white-space: nowrap;
}
.rc-divider { border: none; border-top: 1px solid #eee; margin: 16px 0; }
.rc-payment { color: #888; font-size: .86rem; margin-bottom: 18px; }
.rc-payment strong { color: #333; }
.rc-addresses {
  display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 22px;
}
.rc-addr-label {
  font-size: .71rem; text-transform: uppercase; letter-spacing: 2px;
  color: #a05050; font-weight: 700; margin-bottom: 7px;
}
.rc-addr-name  { font-weight: 700; color: #222; margin-bottom: 3px; font-size: .9rem; }
.rc-addr-line  { color: #555; font-size: .85rem; line-height: 1.55; }

.rc-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
.rc-table thead tr { background: #f5f5f7; }
.rc-table th {
  text-align: left; padding: 9px 10px;
  font-size: .72rem; text-transform: uppercase; letter-spacing: 1px;
  color: #999; border-bottom: 2px solid #eee; font-weight: 700;
}
.rc-table th:not(:first-child) { text-align: right; }
.rc-table td { padding: 10px 10px; font-size: .9rem; border-bottom: 1px solid #f2f2f2; }
.rc-table td:not(:first-child) { text-align: right; }
.rc-table tbody tr:nth-child(even) { background: #fafafa; }
.rc-item-name  { font-weight: 600; color: #222; }
.rc-item-qty   { color: #666; }
.rc-item-unit  { color: #666; }
.rc-item-total { font-weight: 700; color: #333; }
.rc-total-section {
  display: flex; justify-content: flex-end; margin: 14px 0 20px;
}
.rc-total-table { border-collapse: collapse; }
.rc-total-table td { padding: 5px 0 5px 20px; font-size: .9rem; }
.rc-total-table .rc-total-label  { color: #888; text-align: right; }
.rc-total-table .rc-total-value  { font-weight: 600; text-align: right; min-width: 90px; }
.rc-total-table .rc-grand-label  { font-size: 1rem; font-weight: 800; text-align: right; padding-top: 10px; border-top: 2px solid #eee; }
.rc-total-table .rc-grand-value  { font-size: 1.1rem; font-weight: 900; color: #ff3838; text-align: right; padding-top: 10px; border-top: 2px solid #eee; }
.rc-footer {
  border-top: 1px solid #eee; padding-top: 16px; text-align: center;
  color: #aaa; font-size: .8rem; line-height: 1.7;
}

@media print {
  #main-nav, .nav-mobile-menu, .site-footer, .receipt-actions, .page-header { display: none !important; }
  body, main { background: #fff !important; padding: 0 !important; }
  .receipt-wrapper { padding: 0 !important; max-width: 100% !important; }
  #receipt-card { box-shadow: none !important; }
}
</style>

<div class="receipt-wrapper">

  <div class="receipt-actions">
    <a href="<?=BASE_URL?>/orders.php" class="btn-back">
      <i class="fas fa-arrow-left"></i> Back to Orders
    </a>
    <button class="btn-dl" onclick="downloadPDF()">
      <i class="fas fa-file-arrow-down"></i> Download PDF
    </button>
    <button class="btn-print" onclick="window.print()">
      <i class="fas fa-print"></i> Print
    </button>
  </div>

  <div id="receipt-card">

    <!-- Header -->
    <div class="rc-head">
      <div class="rc-head-brand">&#127798; Sambal House</div>
      <div class="rc-head-sub">Handcrafted Malaysian Chili Pastes</div>
      <span class="rc-head-label">Order Receipt</span>
    </div>

    <!-- Body -->
    <div class="rc-body">

      <div class="rc-meta">
        <div>
          <div class="rc-order-num">Order #<?=e($order['id'])?></div>
          <div class="rc-date"><?=date('d F Y, h:i A', strtotime($order['created_at']))?></div>
        </div>
        <span class="rc-paid-badge"><i class="fas fa-circle-check"></i> <?=e($order['status'])?></span>
      </div>

      <hr class="rc-divider">
      <p class="rc-payment">Payment Method: <strong><?=e($order['payment_method'])?></strong></p>

      <!-- Addresses -->
      <div class="rc-addresses">
        <div>
          <div class="rc-addr-label">Bill To</div>
          <div class="rc-addr-name"><?=e($order['full_name'])?></div>
          <div class="rc-addr-line"><?=e($order['email'])?></div>
          <?php if($order['phone']): ?>
          <div class="rc-addr-line"><?=e($order['phone'])?></div>
          <?php endif; ?>
        </div>
        <div>
          <div class="rc-addr-label">Ship To</div>
          <div class="rc-addr-line"><?=e($order['address_line1'])?></div>
          <?php if($order['address_line2']): ?>
          <div class="rc-addr-line"><?=e($order['address_line2'])?></div>
          <?php endif; ?>
          <div class="rc-addr-line"><?=e($order['city'])?>, <?=e($order['state_region'])?> <?=e($order['postcode'])?></div>
          <div class="rc-addr-line"><?=e($order['country'])?></div>
        </div>
      </div>

      <hr class="rc-divider">

      <!-- Items -->
      <table class="rc-table">
        <thead>
          <tr>
            <th style="width:45%">Item</th>
            <th>Qty</th>
            <th>Unit Price</th>
            <th>Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($items as $item): ?>
          <tr>
            <td class="rc-item-name"><?=e($item['name'])?></td>
            <td class="rc-item-qty">&times;<?=(int)$item['qty']?></td>
            <td class="rc-item-unit">RM <?=number_format($item['price'],2)?></td>
            <td class="rc-item-total">RM <?=number_format($item['price']*$item['qty'],2)?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Total -->
      <div class="rc-total-section">
        <table class="rc-total-table">
          <tr>
            <td class="rc-total-label">Subtotal</td>
            <td class="rc-total-value">RM <?=number_format($order['total'],2)?></td>
          </tr>
          <tr>
            <td class="rc-total-label">Shipping</td>
            <td class="rc-total-value" style="color:#4caf50">FREE</td>
          </tr>
          <tr>
            <td class="rc-grand-label">Total</td>
            <td class="rc-grand-value">RM <?=number_format($order['total'],2)?></td>
          </tr>
        </table>
      </div>

      <!-- Footer note -->
      <div class="rc-footer">
        <div>Thank you for your order! We hope you enjoy the heat.</div>
        <div>&copy; <?=date('Y')?> Sambal House &mdash; Handcrafted Malaysian Chili Pastes</div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
var RECEIPT_DATA = <?= json_encode([
  'id'       => $order['id'],
  'date'     => date('d F Y, h:i A', strtotime($order['created_at'])),
  'full_name'=> $order['full_name'],
  'email'    => $order['email'],
  'phone'    => $order['phone'] ?? '',
  'address1' => $order['address_line1'],
  'address2' => $order['address_line2'] ?? '',
  'city'     => $order['city'],
  'state'    => $order['state_region'],
  'postcode' => $order['postcode'],
  'country'  => $order['country'],
  'payment'  => $order['payment_method'],
  'total'    => (float)$order['total'],
  'items'    => array_map(fn($i) => [
    'name'  => $i['name'],
    'qty'   => (int)$i['qty'],
    'price' => (float)$i['price'],
  ], $items),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function downloadPDF() {
  var d = RECEIPT_DATA;
  var { jsPDF } = window.jspdf;
  var doc = new jsPDF({ unit: 'mm', format: 'a4' });

  var lm = 20, rm = 190, cw = 170;
  var y  = 0;

  // ── Dark header block ──
  doc.setFillColor(26, 12, 14);
  doc.rect(0, 0, 210, 44, 'F');

  doc.setFont('helvetica', 'bold');
  doc.setFontSize(20);
  doc.setTextColor(255, 56, 56);
  doc.text('Sambal House', 105, 17, { align: 'center' });

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8);
  doc.setTextColor(199, 174, 178);
  doc.text('Handcrafted Malaysian Chili Pastes', 105, 24, { align: 'center' });

  doc.setFont('helvetica', 'bold');
  doc.setFontSize(9);
  doc.setTextColor(255, 236, 239);
  doc.text('ORDER RECEIPT', 105, 34, { align: 'center' });

  y = 54;

  // ── Order number + date ──
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(15);
  doc.setTextColor(20, 20, 20);
  doc.text('Order #' + d.id, lm, y);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.setTextColor(130, 130, 130);
  doc.text(d.date, rm, y, { align: 'right' });

  y += 6;

  // PAID badge
  doc.setFillColor(76, 175, 80);
  doc.rect(lm, y - 4, 18, 5.5, 'F');
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(7);
  doc.setTextColor(255, 255, 255);
  doc.text('PAID', lm + 9, y - 0.5, { align: 'center' });

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.setTextColor(80, 80, 80);
  doc.text('Payment: ' + d.payment, lm + 22, y - 0.5);

  y += 9;

  // ── Divider ──
  doc.setDrawColor(220, 220, 220);
  doc.line(lm, y, rm, y);
  y += 8;

  // ── Addresses ──
  var col2 = 115;
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(7.5);
  doc.setTextColor(160, 80, 80);
  doc.text('BILL TO', lm, y);
  doc.text('SHIP TO', col2, y);
  y += 5;

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.setTextColor(50, 50, 50);

  var billLines = [d.full_name, d.email, d.phone].filter(function(v){ return v && v.trim(); });
  var shipParts = [d.address1, d.address2, (d.city && d.state ? d.city + ', ' + d.state : (d.city || d.state)), d.postcode, d.country];
  var shipLines = shipParts.filter(function(v){ return v && v.trim(); });

  var startY = y, leftY = startY, rightY = startY;
  billLines.forEach(function(line) {
    doc.text(line.substring(0, 38), lm, leftY); leftY += 4.5;
  });
  shipLines.forEach(function(line) {
    doc.text(line.substring(0, 38), col2, rightY); rightY += 4.5;
  });

  y = Math.max(leftY, rightY) + 6;

  // ── Divider ──
  doc.setDrawColor(220, 220, 220);
  doc.line(lm, y, rm, y);
  y += 8;

  // ── Items table header ──
  doc.setFillColor(245, 245, 247);
  doc.rect(lm, y - 5, cw, 8, 'F');

  doc.setFont('helvetica', 'bold');
  doc.setFontSize(7.5);
  doc.setTextColor(130, 130, 130);
  doc.text('ITEM', lm + 2, y);
  doc.text('QTY',     148, y, { align: 'right' });
  doc.text('UNIT',    163, y, { align: 'right' });
  doc.text('SUBTOTAL', rm, y, { align: 'right' });
  y += 7;

  // ── Items ──
  d.items.forEach(function(item, idx) {
    if (idx % 2 === 1) {
      doc.setFillColor(250, 250, 252);
      doc.rect(lm, y - 5, cw, 7.5, 'F');
    }
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.setTextColor(30, 30, 30);
    doc.text(item.name.substring(0, 30), lm + 2, y);

    doc.setFont('helvetica', 'normal');
    doc.setTextColor(80, 80, 80);
    doc.text('x' + item.qty, 148, y, { align: 'right' });
    doc.text('RM ' + item.price.toFixed(2), 163, y, { align: 'right' });

    doc.setFont('helvetica', 'bold');
    doc.setTextColor(40, 40, 40);
    doc.text('RM ' + (item.qty * item.price).toFixed(2), rm, y, { align: 'right' });
    y += 7.5;
  });

  // ── Total ──
  y += 4;
  doc.setDrawColor(200, 200, 200);
  doc.line(130, y, rm, y);
  y += 5;

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.setTextColor(130, 130, 130);
  doc.text('Subtotal', 163, y, { align: 'right' });
  doc.setTextColor(60, 60, 60);
  doc.text('RM ' + d.total.toFixed(2), rm, y, { align: 'right' });
  y += 5;

  doc.setTextColor(130, 130, 130);
  doc.text('Shipping', 163, y, { align: 'right' });
  doc.setTextColor(76, 175, 80);
  doc.text('FREE', rm, y, { align: 'right' });
  y += 6;

  doc.setDrawColor(200, 200, 200);
  doc.line(130, y, rm, y);
  y += 6;

  doc.setFont('helvetica', 'bold');
  doc.setFontSize(11);
  doc.setTextColor(30, 30, 30);
  doc.text('TOTAL', 163, y, { align: 'right' });
  doc.setTextColor(255, 56, 56);
  doc.text('RM ' + d.total.toFixed(2), rm, y, { align: 'right' });

  y += 14;

  // ── Footer ──
  doc.setDrawColor(220, 220, 220);
  doc.line(lm, y, rm, y);
  y += 7;

  doc.setFont('helvetica', 'italic');
  doc.setFontSize(8.5);
  doc.setTextColor(160, 160, 160);
  doc.text('Thank you for your order! We hope you enjoy the heat.', 105, y, { align: 'center' });
  y += 5;
  doc.setFontSize(7.5);
  doc.text('Sambal House  -  Handcrafted Malaysian Chili Pastes', 105, y, { align: 'center' });

  doc.save('Sambal-House-Receipt-' + d.id + '.pdf');
}
</script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
