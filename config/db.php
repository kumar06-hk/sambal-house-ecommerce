<?php
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';        // XAMPP default empty
$DB_NAME = 'sambal_house';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  error_log('DB Connection failed: '.$mysqli->connect_error);
  http_response_code(503);
  exit('Service temporarily unavailable. Please try again later.');
}
$mysqli->set_charset('utf8mb4');
