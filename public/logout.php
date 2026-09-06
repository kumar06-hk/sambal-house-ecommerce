<?php
require_once __DIR__.'/../lib/helpers.php';

// destroy all session data
session_destroy();
session_start(); // restart so we can set a flash

// set SweetAlert2 flash
set_flash('success','Logged out','You have been logged out successfully.');

// redirect to home
redirect('./index.php');
