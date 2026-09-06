<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['admin_id'])){
  echo json_encode(['error'=>'unauthorized']);
  exit;
}

$row = $mysqli->query("
  SELECT
    COUNT(*)  AS cnt,
    MAX(id)   AS max_id,
    SUM(CASE WHEN status='RETURNED' AND return_accepted_at IS NULL THEN 1 ELSE 0 END) AS pending_returns
  FROM orders
")->fetch_assoc();

echo json_encode([
  'count'           => (int)$row['cnt'],
  'max_id'          => (int)$row['max_id'],
  'pending_returns' => (int)$row['pending_returns'],
]);
