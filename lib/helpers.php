<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function is_logged_in() { return isset($_SESSION['user_id']); }
function current_user_id() { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
function redirect($path) { header("Location: $path"); exit; }
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function escape_output($str): string { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function sanitize_input(string $value): string { return strip_tags(trim($value)); }
function sanitize_name(string $value): string {
  return mb_substr(preg_replace('/[^\p{L}\s\'\-\.]/u', '', $value), 0, 100);
}
function validate_name(string $value, int $min = 2, int $max = 100): bool {
  $len = mb_strlen($value);
  return $len >= $min && $len <= $max && (bool)preg_match('/^[\p{L}\s\'\-\.]+$/u', $value);
}
function validate_address(string $value, int $min = 5, int $max = 120): bool {
  $len = mb_strlen($value);
  return $len >= $min && $len <= $max && (bool)preg_match('/^[\p{L}\d\s\',.\-\/#&()]+$/u', $value);
}

function get_or_create_cart(mysqli $db) {
  // Priority: user cart; fallback: session cart
  $userId = current_user_id();
  if ($userId) {
    // Guard: user may have been deleted — clear stale session if so
    $chk = $db->prepare("SELECT id FROM users WHERE id=?");
    $chk->bind_param('i', $userId);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
      session_unset(); session_destroy(); session_start();
      $userId = null;
    }
    $chk->close();
  }
  if ($userId) {
    $q = $db->prepare("SELECT id FROM carts WHERE user_id=?");
    $q->bind_param('i',$userId);
    $q->execute(); $q->bind_result($cid);
    if ($q->fetch()) { $q->close(); return $cid; }
    $q->close();
    $ins = $db->prepare("INSERT INTO carts (user_id) VALUES (?)");
    $ins->bind_param('i',$userId); $ins->execute();
    return $ins->insert_id;
  }
  // session-based
  if (!isset($_SESSION['session_cart'])) $_SESSION['session_cart'] = session_id();
  $sid = $_SESSION['session_cart'];
  $q = $db->prepare("SELECT id FROM carts WHERE session_id=?");
  $q->bind_param('s',$sid);
  $q->execute(); $q->bind_result($cid);
  if ($q->fetch()) { $q->close(); return $cid; }
  $q->close();
  $ins = $db->prepare("INSERT INTO carts (session_id) VALUES (?)");
  $ins->bind_param('s',$sid); $ins->execute();
  return $ins->insert_id;
}

function cart_totals(mysqli $db, int $cartId){
  $sql="SELECT SUM(ci.qty*p.price) total, COALESCE(SUM(ci.qty),0) items
        FROM cart_items ci JOIN products p ON p.id=ci.product_id
        WHERE ci.cart_id=?";
  $q=$db->prepare($sql); $q->bind_param('i',$cartId); $q->execute();
  $res=$q->get_result()->fetch_assoc();
  return ['total'=> (float)($res['total']??0), 'items'=> (int)($res['items']??0)];
}

// --- CSRF helpers ---
function csrf_token(): string {
  if(empty($_SESSION['csrf_token']))
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf_token'];
}
function csrf_field(): string {
  return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';
}
function csrf_verify(): void {
  $token = $_POST['csrf_token'] ?? '';
  if(!hash_equals($_SESSION['csrf_token'] ?? '', $token)){
    http_response_code(403);
    exit('Invalid request. Please go back and try again.');
  }
}

function is_allowed_email_domain(string $email): bool {
  static $allowed = [
    'gmail.com','yahoo.com','yahoo.com.my','outlook.com','hotmail.com',
    'hotmail.my','icloud.com','me.com','mac.com','live.com',
    'protonmail.com','proton.me','fastmail.com','zoho.com','gmx.com',
    'mail.com','qq.com','163.com','yandex.com','tm.net.my',
    'streamyx.com','naver.com','googlemail.com','msn.com','aol.com',
  ];
  $at = strrpos($email, '@');
  if ($at === false) return false;
  return in_array(strtolower(substr($email, $at + 1)), $allowed, true);
}

// --- Flash helpers for SweetAlert2 ---
function set_flash(string $type, string $title, string $text=''){
  $_SESSION['flash'] = ['type'=>$type, 'title'=>$title, 'text'=>$text];
}
function consume_flash(): ?array {
  if(!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
}

function track_activity(string $action): void {
  global $mysqli;
  if (!is_logged_in() || !isset($mysqli)) return;
  static $initialized = false;
  if (!$initialized) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS user_activity (user_id INT PRIMARY KEY, user_name VARCHAR(100), current_action VARCHAR(150), last_seen DATETIME NOT NULL, INDEX idx_ls (last_seen)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $mysqli->query("CREATE TABLE IF NOT EXISTS activity_log (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, user_name VARCHAR(100), action VARCHAR(150), created_at DATETIME NOT NULL, INDEX idx_c (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $initialized = true;
  }
  $uid  = (int)current_user_id();
  $name = sanitize_name($_SESSION['user_name'] ?? 'User');
  $s1 = $mysqli->prepare("INSERT INTO user_activity (user_id,user_name,current_action,last_seen) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE user_name=VALUES(user_name),current_action=VALUES(current_action),last_seen=NOW()");
  if ($s1) { $s1->bind_param('iss',$uid,$name,$action); $s1->execute(); }
  $s2 = $mysqli->prepare("INSERT INTO activity_log (user_id,user_name,action,created_at) VALUES (?,?,?,NOW())");
  if ($s2) { $s2->bind_param('iss',$uid,$name,$action); $s2->execute(); }
  if (rand(1,100)===1) $mysqli->query("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}

// --- Rate limiter (file-based, no DB schema changes needed) ---
// Stores one Unix timestamp per line in a temp file keyed by IP.
// Blocks the IP for the remainder of the 300-second window after 5 failures.
function _rl_file(string $ip): string {
  return sys_get_temp_dir() . '/sh_rl_' . md5($ip) . '.txt';
}
function rl_check(string $ip, int $max = 5, int $window = 300): bool {
  $file = _rl_file($ip);
  if (!file_exists($file)) return false;
  $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
  $now   = time();
  $recent = array_filter($lines, fn($t) => ($now - (int)$t) < $window);
  return count($recent) >= $max;
}
function rl_record(string $ip): void {
  file_put_contents(_rl_file($ip), time() . "\n", FILE_APPEND | LOCK_EX);
}
function rl_clear(string $ip): void {
  $f = _rl_file($ip);
  if (file_exists($f)) unlink($f);
}

// Returns the URL for a bank logo — prefers PNG/JPG if the file exists, falls back to SVG
function bank_logo(string $bankCode): string {
  $slug = strtolower($bankCode);
  $dir  = __DIR__ . '/../public/assets/images/banks/';
  foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
    if (file_exists($dir . $slug . '.' . $ext)) {
      return BASE_URL . '/assets/images/banks/' . $slug . '.' . $ext;
    }
  }
  return BASE_URL . '/assets/images/banks/' . $slug . '.svg';
}
