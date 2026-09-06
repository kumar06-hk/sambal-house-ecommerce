<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

if(is_logged_in()) redirect(BASE_URL.'/index.php');

$error = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  if(rl_check($ip)){
    $error = 'Too many failed attempts. Please wait a few minutes before trying again.';
  } else {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $s = $mysqli->prepare("SELECT id, name, password_hash FROM users WHERE email=?");
    $s->bind_param('s', $email);
    $s->execute();
    if($u = $s->get_result()->fetch_assoc()){
      if(password_verify($pass, $u['password_hash'])){
        rl_clear($ip);
        session_regenerate_id(true);
        $_SESSION['user_id']   = $u['id'];
        $_SESSION['user_name'] = $u['name'];
        set_flash('success', 'Welcome back!', 'You are now logged in.');
        redirect(BASE_URL.'/index.php');
      }
    }
    rl_record($ip);
    $error = 'Invalid email or password.';
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In - Sambal House</title>
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
        <h1>Welcome Back</h1>
        <p>Sign in to your account</p>
      </div>

      <?php if($error): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-triangle"></i>
        <?= e($error) ?>
      </div>
      <?php endif; ?>

      <form method="post" class="admin-login-form" id="loginForm" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="email">
            <i class="fas fa-envelope"></i>
            Email Address
          </label>
          <input type="email" id="email" name="email" placeholder="SambalHouse@example.com" autocomplete="email" data-no-domain-validate>
          <span class="field-error" id="emailError"></span>
        </div>
        <div class="form-group">
          <label for="password">
            <i class="fas fa-lock"></i>
            Password
          </label>
          <div style="position:relative">
            <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" style="padding-right:42px;width:100%">
            <button type="button" onclick="togglePw('password','eyeLogin')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:1rem;padding:0">
              <i class="fas fa-eye" id="eyeLogin"></i>
            </button>
          </div>
          <span class="field-error" id="passwordError"></span>
        </div>
        <button type="submit" class="admin-login-btn">
          <i class="fas fa-sign-in-alt"></i>
          <span>Sign In</span>
        </button>
      </form>

      <div class="login-footer">
        <p style="text-align:center;margin-bottom:10px">
          <a href="<?=BASE_URL?>/forgot_password.php" style="color:var(--muted);font-size:.88rem">
            <i class="fas fa-key"></i> Forgot your password?
          </a>
        </p>
        <p style="text-align:center;color:var(--muted);font-size:.9rem">
          Don't have an account?
          <a href="<?=BASE_URL?>/register.php" style="color:var(--accent);font-weight:600">Create one here</a>
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
  document.getElementById('loginForm').addEventListener('submit', function(e) {
    let valid = true;
    const email    = document.getElementById('email');
    const password = document.getElementById('password');
    const emailErr = document.getElementById('emailError');
    const passErr  = document.getElementById('passwordError');

    emailErr.textContent = '';
    passErr.textContent  = '';

    if(!email.value.trim()) {
      emailErr.textContent = 'Email is required.';
      valid = false;
    } else if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      emailErr.textContent = 'Please enter a valid email address.';
      valid = false;
    }
    if(!password.value) {
      passErr.textContent = 'Password is required.';
      valid = false;
    }
    if(!valid) e.preventDefault();
  });
  function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if(input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
  }
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
