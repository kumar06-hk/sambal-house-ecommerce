<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
if (!isset($_SESSION['admin_id'])) { http_response_code(403); echo '{}'; exit; }
header('Content-Type: application/json; charset=utf-8');

$has_ua  = $mysqli->query("SHOW TABLES LIKE 'user_activity'")->num_rows > 0;
$has_log = $mysqli->query("SHOW TABLES LIKE 'activity_log'")->num_rows > 0;

$users = [];
$log   = [];

if ($has_ua) {
    $users = $mysqli->query("
        SELECT user_id, user_name, current_action, last_seen,
               CASE
                 WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 3 MINUTE)  THEN 'online'
                 WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN 'idle'
                 ELSE 'offline'
               END AS status
        FROM user_activity
        WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ORDER BY last_seen DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

if ($has_log) {
    $log = $mysqli->query("
        SELECT user_name, action, created_at
        FROM activity_log
        ORDER BY created_at DESC
        LIMIT 25
    ")->fetch_all(MYSQLI_ASSOC);
}

echo json_encode(['users' => $users, 'log' => $log], JSON_HEX_TAG | JSON_HEX_AMP);
