<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
if(!isset($_SESSION['admin_id'])) redirect('./login.php');

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
  http_response_code(405);
  exit('Method not allowed.');
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);

if($id){
  // Remove FK references first so the product can be hard-deleted
  $ci = $mysqli->prepare("DELETE FROM cart_items WHERE product_id=?");
  $ci->bind_param('i', $id);
  $ci->execute();

  $oi = $mysqli->prepare("DELETE FROM order_items WHERE product_id=?");
  $oi->bind_param('i', $id);
  $oi->execute();

  $del = $mysqli->prepare("DELETE FROM products WHERE id=?");
  $del->bind_param('i', $id);
  $del->execute();
}

redirect(ADMIN_URL.'/products.php');
