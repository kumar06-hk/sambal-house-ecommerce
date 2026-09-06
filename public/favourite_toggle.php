<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

header('Content-Type: application/json');

if(!is_logged_in()){
  echo json_encode(['error'=>'Login required']);
  exit;
}

$pid = (int)($_POST['product_id'] ?? 0);
if($pid <= 0){ echo json_encode(['error'=>'Invalid product']); exit; }

$uid = (int)current_user_id();

// Check if already favourited
$chk = $mysqli->prepare("SELECT id FROM user_favourites WHERE user_id=? AND product_id=?");
$chk->bind_param('ii', $uid, $pid);
$chk->execute();
$exists = $chk->get_result()->fetch_assoc();

if($exists){
  $del = $mysqli->prepare("DELETE FROM user_favourites WHERE user_id=? AND product_id=?");
  $del->bind_param('ii', $uid, $pid);
  $del->execute();
  $favourited = false;
} else {
  $ins = $mysqli->prepare("INSERT INTO user_favourites (user_id, product_id) VALUES (?,?)");
  $ins->bind_param('ii', $uid, $pid);
  $ins->execute();
  $favourited = true;
}

// Get new count
$cnt = $mysqli->prepare("SELECT COUNT(*) AS c FROM user_favourites WHERE user_id=?");
$cnt->bind_param('i', $uid);
$cnt->execute();
$count = (int)$cnt->get_result()->fetch_assoc()['c'];

echo json_encode(['favourited'=>$favourited, 'count'=>$count]);
