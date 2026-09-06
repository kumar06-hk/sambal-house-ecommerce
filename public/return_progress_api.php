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

// Auto-advance: ACCEPTED → REFUNDED (acceptance requires admin; refund processing is automatic)
$autoFlow = [
    'step2' => [
        'condition' => "return_accepted_at IS NOT NULL AND return_refunded_at IS NULL",
        'elapsed'   => "TIMESTAMPDIFF(SECOND, return_accepted_at, NOW())",
        'set_col'   => 'return_refunded_at',
        'set_val'   => 'DATE_ADD(return_accepted_at, INTERVAL 60 SECOND)',
    ],
];

foreach ($autoFlow as $info) {
    $cond   = $info['condition'];
    $elExpr = $info['elapsed'];
    $setCol = $info['set_col'];

    $elStmt = $mysqli->prepare(
        "SELECT ({$elExpr}) AS elapsed FROM orders
         WHERE id = ? AND user_id = ? AND status = 'RETURNED' AND {$cond}"
    );
    $elStmt->bind_param('ii', $orderId, $uid);
    $elStmt->execute();
    $elRow = $elStmt->get_result()->fetch_assoc();

    if (!$elRow || $elRow['elapsed'] === null || (int)$elRow['elapsed'] < 60) continue;

    $setVal = $info['set_val'];
    $upd = $mysqli->prepare(
        "UPDATE orders SET {$setCol} = {$setVal}
         WHERE id = ? AND user_id = ? AND status = 'RETURNED' AND {$cond}"
    );
    $upd->bind_param('ii', $orderId, $uid);
    $upd->execute();
}

// Fetch final state
$stmt = $mysqli->prepare(
    "SELECT id, total, payment_method, return_requested_at,
            return_accepted_at, return_refunded_at,
            TIMESTAMPDIFF(SECOND, return_requested_at, NOW()) AS elapsed_requested,
            TIMESTAMPDIFF(SECOND, return_accepted_at,  NOW()) AS elapsed_accepted
     FROM orders WHERE id = ? AND user_id = ? AND status = 'RETURNED'"
);
$stmt->bind_param('ii', $orderId, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['error' => 'Order not found or not completed']);
    exit;
}

$step = 0;
if ($order['return_requested_at']) $step = 1;
if ($order['return_accepted_at'])  $step = 2;
if ($order['return_refunded_at'])  $step = 3;

$steps = [
    ['label' => 'Return Requested', 'icon' => 'fa-rotate-left',  'time' => $order['return_requested_at']],
    ['label' => 'Return Accepted',  'icon' => 'fa-circle-check', 'time' => $order['return_accepted_at']],
    ['label' => 'Refunded',         'icon' => 'fa-wallet',       'time' => $order['return_refunded_at']],
];

foreach ($steps as $i => &$s) {
    $s['done']     = $i < $step;
    $s['current']  = $i === $step - 1 && $step < 3;
    $s['time_fmt'] = $s['time'] ? date('d M Y, g:i A', strtotime($s['time'])) : null;
    unset($s['time']);
}
unset($s);

$nextIn = null;
if ($step === 2 && $order['elapsed_accepted'] !== null) {
    $nextIn = max(0, 60 - (int)$order['elapsed_accepted']);
}

echo json_encode([
    'order_id' => (int)$order['id'],
    'step'     => $step,
    'complete' => $step === 3,
    'steps'    => $steps,
    'next_in'  => $nextIn,
    'total'    => number_format((float)$order['total'], 2),
    'payment'  => $order['payment_method'],
]);
