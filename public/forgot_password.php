<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/mailer.php';

if (is_logged_in()) redirect(BASE_URL.'/index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!is_allowed_email_domain($email)) {
        $error = 'Please use a recognised email provider (Gmail, Outlook, Yahoo, etc.).';
    } else {
        $q = $mysqli->prepare("SELECT id, name FROM users WHERE email = ?");
        $q->bind_param('s', $email);
        $q->execute();
        $user = $q->get_result()->fetch_assoc();

        if ($user) {
            // Delete any existing unused tokens for this email
            $del = $mysqli->prepare("DELETE FROM password_resets WHERE email = ?");
            $del->bind_param('s', $email);
            $del->execute();

            $otp        = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $token      = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + 900);

            $ins = $mysqli->prepare("INSERT INTO password_resets (user_id, email, token, otp, expires_at) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param('issss', $user['id'], $email, $token, $otp, $expires_at);
            $ins->execute();

            $sent = send_password_reset_email($email, $user['name'], $otp, $token);

            if ($sent) {
                $_SESSION['reset_email'] = $email;
                set_flash('success', 'Email Sent!', 'If that email is registered, you\'ll receive a reset code shortly.');
                redirect(BASE_URL.'/verify_otp.php');
            } else {
                $error = 'Failed to send email. Please try again later.';
            }
        } else {
            // Don't reveal whether email exists — same response either way
            $_SESSION['reset_email'] = $email;
            set_flash('success', 'Email Sent!', 'If that email is registered, you\'ll receive a reset code shortly.');
            redirect(BASE_URL.'/verify_otp.php');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Sambal House</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/assets/style.css?v=<?=time()?>">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .admin-login-card { padding: 36px 40px; max-width: 420px; }
    .login-header { margin-bottom: 20px; }
    .login-header h1 { font-size: 1.6rem; }
    .login-header p { font-size: .85rem; }
    .admin-login-form { gap: 10px; margin-bottom: 18px; }
    .admin-login-form .form-group { margin-bottom: 0; }
    .admin-login-form input { padding: 11px 16px; font-size: .92rem; }
    .admin-login-form label { font-size: .82rem; margin-bottom: 5px; }
    .admin-login-btn { padding: 13px 20px; font-size: .95rem; margin-top: 4px; }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <div class="admin-login-container">
    <div class="bg-pattern"></div>
    <div class="floating-shapes">
      <div class="shape shape-1"></div>
      <div class="shape shape-2"></div>
      <div class="shape shape-3"></div>
    </div>

    <div class="admin-login-card">
      <div class="login-header">
        <div class="logo-container">
          <i class="fas fa-pepper-hot"></i>
          <span>Sambal House</span>
        </div>
        <h1>Forgot Password?</h1>
        <p>Enter your email to receive a reset code</p>
      </div>

      <?php if($error): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-triangle"></i>
        <?= e($error) ?>
      </div>
      <?php endif; ?>

      <form method="post" class="admin-login-form" id="forgotForm" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="email">
            <i class="fas fa-envelope"></i>
            Email Address
          </label>
          <input type="email" id="email" name="email" placeholder="your.email@example.com" autocomplete="email">
          <span class="field-error" id="emailError"></span>
        </div>
        <button type="submit" class="admin-login-btn">
          <i class="fas fa-paper-plane"></i>
          <span>Send Reset Code</span>
        </button>
      </form>

      <div class="login-footer">
        <p style="text-align:center;color:var(--muted);font-size:.9rem">
          Remember your password?
          <a href="<?=BASE_URL?>/login.php" style="color:var(--accent);font-weight:600">Sign in here</a>
        </p>
      </div>
    </div>

    <div class="back-to-shop">
      <a href="<?=BASE_URL?>/index.php">
        <i class="fas fa-arrow-left"></i>
        <span>Back to Shop</span>
      </a>
    </div>
  </div>

  <script>
  const AC_DOMAINS = [
    "gmail.com","yahoo.com","yahoo.com.my","outlook.com","hotmail.com",
    "hotmail.my","icloud.com","me.com","mac.com","live.com",
    "protonmail.com","proton.me","fastmail.com","zoho.com","gmx.com",
    "mail.com","qq.com","163.com","yandex.com","tm.net.my",
    "streamyx.com","naver.com","googlemail.com","msn.com","aol.com"
  ];
  function allowedEmailDomain(email) {
    var at = email.lastIndexOf('@');
    return at !== -1 && AC_DOMAINS.indexOf(email.slice(at + 1).toLowerCase()) !== -1;
  }

  document.getElementById('forgotForm').addEventListener('submit', function(e) {
    const email    = document.getElementById('email');
    const emailErr = document.getElementById('emailError');
    emailErr.textContent = '';
    if (!email.value.trim()) {
      emailErr.textContent = 'Email is required.';
      e.preventDefault();
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      emailErr.textContent = 'Please enter a valid email address.';
      e.preventDefault();
    } else if (!allowedEmailDomain(email.value.trim())) {
      emailErr.textContent = 'Please use a recognised email provider (Gmail, Outlook, Yahoo, etc.).';
      e.preventDefault();
    }
  });
  </script>
  <script src="<?=BASE_URL?>/assets/email-autocomplete.js"></script>
  <?php if($f = consume_flash()): ?>
  <script>
  Swal.fire({
    icon: <?=json_encode($f['type'])?>,
    title: <?=json_encode($f['title'])?>,
    text: <?=json_encode($f['text'])?>,
    confirmButtonColor: '#ff3838'
  });
  </script>
  <?php endif; ?>
</body>
</html>
