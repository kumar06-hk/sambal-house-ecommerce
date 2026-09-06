<?php
// Security headers — sent before any output on every page
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' data: https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self';");

// Base URLs (adjust if you use vhosts)
define('BASE_URL',  '/sambal-house/public');
define('ADMIN_URL', '/sambal-house/admin');
define('APP_URL',   'http://localhost');

// Gmail SMTP — generate an App Password at:
// myaccount.google.com → Security → App Passwords
define('GMAIL_USER', 'your.email@gmail.com');
define('GMAIL_PASS', 'xxxx xxxx xxxx xxxx');
