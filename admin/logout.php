<?php
require_once __DIR__.'/../lib/helpers.php';
unset($_SESSION['admin_id'], $_SESSION['admin_name']);
redirect('./login.php');
