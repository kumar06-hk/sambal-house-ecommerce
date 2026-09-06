<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/mailer.php';

if (!is_logged_in()) {
    set_flash('error', 'Login Required', 'Please sign in to continue.');
    redirect(BASE_URL.'/login.php');
}

if (empty($_SESSION['email_change_uid']) || empty($_SESSION['email_change_new'])) {
    redirect(BASE_URL.'/profile.php');
}

$uid      = (int)$_SESSION['email_change_uid'];
$newEmail = $_SESSION['email_change_new'];
$error    = '';

if ($uid !== current_user_id()) {
    unset($_SESSION['email_change_uid'], $_SESSION['email_change_new']);
    redirect(BASE_URL.'/profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['resend'])) {
        $q = $mysqli->prepare("SELECT name FROM users WHERE id = ?");
        $q->bind_param('i', $uid);
        $q->execute();
        $uname = $q->get_result()->fetch_assoc()['name'] ?? '';

        $del = $mysqli->prepare("DELETE FROM email_changes WHERE user_id = ?");
        $del->bind_param('i', $uid);
        $del->execute();

        $otp        = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', time() + 900);

        $ins = $mysqli->prepare("INSERT INTO email_changes (user_id, new_email, otp, expires_at) VALUES (?, ?, ?, ?)");
        $ins->bind_param('isss', $uid, $newEmail, $otp, $expires_at);
        $ins->execute();

        send_email_change_otp($newEmail, $uname, $otp);
        set_flash('success', 'Code Resent', 'A fresh verification code has been sent.');
        redirect(BASE_URL.'/verify_email_change.php');
    }

    $otp_input = trim($_POST['otp'] ?? '');

    if (!preg_match('/^\d{6}$/', $otp_input)) {
        $error = 'Please enter the 6-digit code from your email.';
    } else {
        $now = date('Y-m-d H:i:s');
        $q   = $mysqli->prepare("SELECT id FROM email_changes WHERE user_id = ? AND new_email = ? AND otp = ? AND expires_at > ? AND used = 0");
        $q->bind_param('isss', $uid, $newEmail, $otp_input, $now);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();

        if ($row) {
            // Check new email not already taken (race condition guard)
            $chk = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->bind_param('si', $newEmail, $uid);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $error = 'This email has just been registered by someone else. Please go back and choose another.';
            } else {
                $upd = $mysqli->prepare("UPDATE users SET email = ? WHERE id = ?");
                $upd->bind_param('si', $newEmail, $uid);
                $upd->execute();

                $done = $mysqli->prepare("UPDATE email_changes SET used = 1 WHERE user_id = ?");
                $done->bind_param('i', $uid);
                $done->execute();

                unset($_SESSION['email_change_uid'], $_SESSION['email_change_new']);
                set_flash('success', 'Email Updated!', 'Your email address has been changed successfully.');
                redirect(BASE_URL.'/profile.php');
            }
        } else {
            $error = 'Invalid or expired code. Please try again or resend.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Email Change - Sambal House</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/assets/style.css?v=<?=time()?>">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .admin-login-card { padding: 36px 40px; max-width: 420px; }
    .login-header { margin-bottom: 20px; }
    .login-header h1 { font-size: 1.6rem; }
    .login-header p { font-size: .85rem; }
    .admin-login-form { gap: 10px; margin-bottom: 18px; }
    .admin-login-form .form-group { margin-bottom: 0; }
    .admin-login-form label { font-size: .82rem; margin-bottom: 5px; }
    .admin-login-btn { padding: 13px 20px; font-size: .95rem; margin-top: 4px; }
    .otp-input {
      text-align: center !important;
      font-size: 2rem !important;
      font-weight: 800 !important;
      letter-spacing: 12px !important;
      padding: 14px 16px !important;
      color: var(--accent) !important;
    }
    .email-hint {
      background: rgba(255,56,56,.08);
      border: 1px solid rgba(255,56,56,.2);
      border-radius: 10px;
      padding: 11px 16px;
      font-size: .84rem;
      color: var(--muted);
      margin-bottom: 18px;
      text-align: center;
    }
    .email-hint strong { color: var(--text); }
    .resend-btn {
      background: none; border: none;
      color: var(--accent); cursor: pointer;
      font-size: .88rem; font-weight: 600;
      text-decoration: underline; padding: 0;
    }
    .resend-btn:hover { opacity: .8; }
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
        <h1>Verify New Email</h1>
        <p>Enter the code sent to your new inbox</p>
      </div>

      <div class="email-hint">
        Code sent to <strong><?= e($newEmail) ?></strong>
      </div>

      <?php if($error): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-triangle"></i>
        <?= e($error) ?>
      </div>
      <?php endif; ?>

      <form method="post" class="admin-login-form" id="otpForm" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="otp">
            <i class="fas fa-key"></i>
            6-Digit Verification Code
          </label>
          <input type="text" id="otp" name="otp" class="otp-input"
                 placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
          <span class="field-error" id="otpError"></span>
        </div>
        <button type="submit" class="admin-login-btn">
          <i class="fas fa-check-circle"></i>
          <span>Verify &amp; Update Email</span>
        </button>
      </form>

      <div class="login-footer" style="text-align:center">
        <form method="post" style="margin-bottom:10px">
          <?= csrf_field() ?>
          <button type="submit" name="resend" value="1" class="resend-btn">
            <i class="fas fa-rotate-right"></i> Resend Code
          </button>
        </form>
        <p style="color:var(--muted);font-size:.85rem">
          <a href="<?=BASE_URL?>/profile.php" style="color:var(--muted)">
            <i class="fas fa-arrow-left"></i> Back to Profile
          </a>
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
  const otpInput = document.getElementById('otp');
  otpInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
  });
  otpInput.focus();

  document.getElementById('otpForm').addEventListener('submit', function(e) {
    const err = document.getElementById('otpError');
    err.textContent = '';
    if (otpInput.value.length !== 6) {
      err.textContent = 'Please enter the complete 6-digit code.';
      e.preventDefault();
    }
  });
  </script>
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
