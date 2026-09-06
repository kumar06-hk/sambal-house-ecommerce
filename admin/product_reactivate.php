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
  $stmt = $mysqli->prepare("UPDATE products SET is_active=1 WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
}

redirect(ADMIN_URL.'/products.php');
