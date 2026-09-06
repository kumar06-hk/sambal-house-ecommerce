<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) {
    echo json_encode(['error' => 'Missing order_id']);
    exit;
}

$uid = current_user_id();

// Auto-advance: PAID → SHIPPED → RECEIVED → COMPLETED every 60 seconds
// All time comparisons done in MySQL to avoid PHP/MySQL timezone mismatches
$autoFlow = [
    'PAID'     => ['next' => 'SHIPPED',   'elapsed_expr' => 'TIMESTAMPDIFF(SECOND,paid_at,NOW())',     'set_col' => 'shipped_at',   'set_val' => 'DATE_ADD(paid_at,INTERVAL 60 SECOND)'],
    'SHIPPED'  => ['next' => 'RECEIVED',  'elapsed_expr' => 'TIMESTAMPDIFF(SECOND,shipped_at,NOW())',  'set_col' => 'received_at',  'set_val' => 'DATE_ADD(shipped_at,INTERVAL 60 SECOND)'],
    'RECEIVED' => ['next' => 'COMPLETED', 'elapsed_expr' => 'TIMESTAMPDIFF(SECOND,received_at,NOW())', 'set_col' => 'completed_at', 'set_val' => 'DATE_ADD(received_at,INTERVAL 60 SECOND)'],
];

for ($pass = 0; $pass < 3; $pass++) {
    $chk = $mysqli->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $chk->bind_param('ii', $orderId, $uid);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    if (!$row) break;

    $info = $autoFlow[$row['status']] ?? null;
    if (!$info) break;

    // Check elapsed time entirely in MySQL — immune to PHP timezone setting
    $elExpr = $info['elapsed_expr'];
    $elStmt = $mysqli->prepare("SELECT ({$elExpr}) AS elapsed FROM orders WHERE id = ? AND user_id = ?");
    $elStmt->bind_param('ii', $orderId, $uid);
    $elStmt->execute();
    $elRow = $elStmt->get_result()->fetch_assoc();
    if (!$elRow || $elRow['elapsed'] === null || (int)$elRow['elapsed'] < 60) break;

    $next   = $info['next'];
    $setCol = $info['set_col'];
    $setVal = $info['set_val'];
    $upd    = $mysqli->prepare(
        "UPDATE orders SET status = ?, {$setCol} = {$setVal}
         WHERE id = ? AND user_id = ? AND status = ?"
    );
    $upd->bind_param('siis', $next, $orderId, $uid, $row['status']);
    $upd->execute();
    if ($upd->affected_rows === 0) break;
}

// Fetch final state — include MySQL-computed elapsed seconds to avoid timezone issues
$stmt = $mysqli->prepare(
    "SELECT id, status, payment_method, created_at,
            paid_at, shipped_at, received_at, completed_at,
            TIMESTAMPDIFF(SECOND,paid_at,NOW())     AS elapsed_paid,
            TIMESTAMPDIFF(SECOND,shipped_at,NOW())  AS elapsed_shipped,
            TIMESTAMPDIFF(SECOND,received_at,NOW()) AS elapsed_received
     FROM orders WHERE id = ? AND user_id = ?"
);
$stmt->bind_param('ii', $orderId, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['error' => 'Order not found']);
    exit;
}

$status = $order['status'];

$doneUpTo = match($status) {
    'PENDING'                => 0,
    'PAID', 'PROCESSING'     => 1,
    'SHIPPED'                => 2,
    'RECEIVED'               => 3,
    'COMPLETED'              => 4,
    default                  => -1,
};

$steps = [
    ['label' => 'Order Placed',     'icon' => 'fa-clipboard-list', 'time' => $order['created_at']],
    ['label' => 'Order Paid',        'icon' => 'fa-credit-card',   'time' => $order['paid_at']],
    ['label' => 'Order Shipped Out', 'icon' => 'fa-truck',          'time' => $order['shipped_at']],
    ['label' => 'Order Received',    'icon' => 'fa-box-open',       'time' => $order['received_at']],
    ['label' => 'Order Completed',   'icon' => 'fa-star',           'time' => $order['completed_at']],
];

foreach ($steps as $i => &$s) {
    $s['done']    = $doneUpTo >= 0 && $i <= $doneUpTo;
    $s['current'] = $i === $doneUpTo && $status !== 'COMPLETED';
    $s['time_fmt'] = $s['time'] ? date('d M Y, g:i A', strtotime($s['time'])) : null;
    unset($s['time']);
}
unset($s);

$elapsedCols = ['PAID' => 'elapsed_paid', 'SHIPPED' => 'elapsed_shipped', 'RECEIVED' => 'elapsed_received'];
$nextIn = null;
if (isset($elapsedCols[$status])) {
    $elapsed = $order[$elapsedCols[$status]];
    if ($elapsed !== null) {
        $nextIn = max(0, 60 - (int)$elapsed);
    }
}

echo json_encode([
    'order_id'   => (int)$order['id'],
    'status'     => $status,
    'cancelled'  => $status === 'CANCELLED',
    'done_up_to' => $doneUpTo,
    'steps'      => $steps,
    'next_in'    => $nextIn,
]);
