<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

if (is_logged_in()) redirect(BASE_URL.'/index.php');

// Accept token from URL (direct link) or from session (OTP path) or from POST hidden field
$token = $_GET['token'] ?? $_POST['token'] ?? $_SESSION['reset_verified_token'] ?? '';
$error = '';

if (empty($token)) {
    set_flash('error', 'Invalid Link', 'Please request a new password reset.');
    redirect(BASE_URL.'/forgot_password.php');
}

$now = date('Y-m-d H:i:s');
$q   = $mysqli->prepare("SELECT pr.id, pr.email, u.name, u.password_hash FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token = ? AND pr.expires_at > ? AND pr.used = 0");
$q->bind_param('ss', $token, $now);
$q->execute();
$reset = $q->get_result()->fetch_assoc();

if (!$reset) {
    unset($_SESSION['reset_verified_token']);
    set_flash('error', 'Link Expired', 'This reset link has expired or already been used. Please request a new one.');
    redirect(BASE_URL.'/forgot_password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pass  = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $pass)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $pass)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $pass)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $pass)) {
        $error = 'Password must contain at least one special character.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } elseif (password_verify($pass, $reset['password_hash'])) {
        $error = 'New password must be different from your current password.';
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);

        $upd = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $upd->bind_param('ss', $hash, $reset['email']);
        $upd->execute();

        $done = $mysqli->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $done->bind_param('s', $token);
        $done->execute();

        unset($_SESSION['reset_verified_token']);
        set_flash('success', 'Password Updated!', 'Your password has been reset successfully. Please sign in.');
        redirect(BASE_URL.'/login.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - Sambal House</title>
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
        <h1>Reset Password</h1>
        <p>Choose a strong new password, <?= e($reset['name']) ?></p>
      </div>

      <?php if($error): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-triangle"></i>
        <?= e($error) ?>
      </div>
      <?php endif; ?>

      <form method="post" class="admin-login-form" id="resetForm" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="form-group">
          <label for="password">
            <i class="fas fa-lock"></i>
            New Password
          </label>
          <div style="position:relative">
            <input type="password" id="password" name="password"
                   placeholder="Min 8 chars, uppercase, number and symbol"
                   autocomplete="new-password" style="padding-right:42px;width:100%">
            <button type="button" onclick="togglePw('password','eye1')"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:1rem;padding:0">
              <i class="fas fa-eye" id="eye1"></i>
            </button>
          </div>
          <span class="field-error" id="passError"></span>
        </div>

        <div class="form-group">
          <label for="password2">
            <i class="fas fa-lock"></i>
            Confirm Password
          </label>
          <div style="position:relative">
            <input type="password" id="password2" name="password2"
                   placeholder="Repeat your new password"
                   autocomplete="new-password" style="padding-right:42px;width:100%">
            <button type="button" onclick="togglePw('password2','eye2')"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:1rem;padding:0">
              <i class="fas fa-eye" id="eye2"></i>
            </button>
          </div>
          <span class="field-error" id="pass2Error"></span>
        </div>

        <button type="submit" class="admin-login-btn">
          <i class="fas fa-shield-halved"></i>
          <span>Update Password</span>
        </button>
      </form>
    </div>

    <div class="back-to-shop">
      <a href="<?=BASE_URL?>/index.php">
        <i class="fas fa-arrow-left"></i>
        <span>Back to Shop</span>
      </a>
    </div>
  </div>

  <script>
  document.getElementById('resetForm').addEventListener('submit', function(e) {
    const pass  = document.getElementById('password');
    const pass2 = document.getElementById('password2');
    const pErr  = document.getElementById('passError');
    const p2Err = document.getElementById('pass2Error');
    let valid = true;
    pErr.textContent = ''; p2Err.textContent = '';

    if (pass.value.length < 8) {
      pErr.textContent = 'Password must be at least 8 characters.';
      valid = false;
    } else if (!/[A-Z]/.test(pass.value)) {
      pErr.textContent = 'Password must contain at least one uppercase letter.';
      valid = false;
    } else if (!/[a-z]/.test(pass.value)) {
      pErr.textContent = 'Password must contain at least one lowercase letter.';
      valid = false;
    } else if (!/[0-9]/.test(pass.value)) {
      pErr.textContent = 'Password must contain at least one number.';
      valid = false;
    } else if (!/[^A-Za-z0-9]/.test(pass.value)) {
      pErr.textContent = 'Password must contain at least one special character.';
      valid = false;
    }
    if (pass.value !== pass2.value) {
      p2Err.textContent = 'Passwords do not match.';
      valid = false;
    }
    if (!valid) e.preventDefault();
  });

  function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye',       input.type === 'password');
    icon.classList.toggle('fa-eye-slash', input.type === 'text');
  }
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
