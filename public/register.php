<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/mailer.php';

if(is_logged_in()) redirect(BASE_URL.'/index.php');

$errors = [];
$old    = ['name' => '', 'email' => ''];

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  $old   = ['name' => $name, 'email' => $email];

  if(strlen($name) < 2)
    $errors['name'] = 'Full name must be at least 2 characters.';
  elseif(mb_strlen($name) > 100)
    $errors['name'] = 'Full name must be under 100 characters.';
  elseif(!validate_name($name))
    $errors['name'] = 'Name may only contain letters, spaces, hyphens, apostrophes and dots.';
  if(!filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors['email'] = 'Please enter a valid email address.';
  elseif(!is_allowed_email_domain($email))
    $errors['email'] = 'Please use a recognised email provider (Gmail, Outlook, Yahoo, etc.).';
  if(strlen($pass) < 8)
    $errors['password'] = 'Password must be at least 8 characters.';
  elseif(!preg_match('/[A-Z]/', $pass))
    $errors['password'] = 'Password must contain at least one uppercase letter.';
  elseif(!preg_match('/[a-z]/', $pass))
    $errors['password'] = 'Password must contain at least one lowercase letter.';
  elseif(!preg_match('/[0-9]/', $pass))
    $errors['password'] = 'Password must contain at least one number.';
  elseif(!preg_match('/[^A-Za-z0-9]/', $pass))
    $errors['password'] = 'Password must contain at least one special character.';

  if(empty($errors)){
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $ins  = $mysqli->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
    $ins->bind_param('sss', $name, $email, $hash);
    if($ins->execute()){
      $_SESSION['user_id']   = $ins->insert_id;
      $_SESSION['user_name'] = $name;
      @send_welcome_email($email, $name);
      set_flash('success', 'Welcome '.e($name).'!', 'Your account has been created.');
      redirect(BASE_URL.'/index.php');
    } else {
      $errors['email'] = 'This email address is already registered.';
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account - Sambal House</title>
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
    .pw-seg { flex:1; height:4px; border-radius:999px; background:rgba(255,255,255,.1); transition:background .3s; }
    .pw-req { font-size:.7rem; padding:2px 8px; border-radius:999px; background:rgba(255,255,255,.06); color:var(--muted); border:1px solid rgba(255,255,255,.1); transition:all .2s; }
    .pw-req.met { background:rgba(34,197,94,.12); color:#4ade80; border-color:rgba(34,197,94,.3); }
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
        <h1>Create Account</h1>
        <p>Join us and start ordering today</p>
      </div>

      <form method="post" class="admin-login-form" id="registerForm" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="name">
            <i class="fas fa-user"></i>
            Full Name
          </label>
          <input type="text" id="name" name="name" value="<?=e($old['name'])?>" placeholder="Enter your full name" autocomplete="name">
          <span class="field-error" id="nameError"><?=e($errors['name'] ?? '')?></span>
        </div>
        <div class="form-group">
          <label for="email">
            <i class="fas fa-envelope"></i>
            Email Address
          </label>
          <input type="email" id="email" name="email" value="<?=e($old['email'])?>" placeholder="your.email@example.com" autocomplete="email">
          <span class="field-error" id="emailError"><?=e($errors['email'] ?? '')?></span>
        </div>
        <div class="form-group">
          <label for="password">
            <i class="fas fa-lock"></i>
            Password
          </label>
          <div style="position:relative">
            <input type="password" id="password" name="password" placeholder="Min 8 chars, uppercase, number and symbol" autocomplete="new-password" style="padding-right:42px;width:100%">
            <button type="button" onclick="togglePw('password','eyeReg')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:1rem;padding:0">
              <i class="fas fa-eye" id="eyeReg"></i>
            </button>
          </div>
          <div style="display:flex;gap:4px;margin-top:8px">
            <div class="pw-seg" id="pws1"></div>
            <div class="pw-seg" id="pws2"></div>
            <div class="pw-seg" id="pws3"></div>
            <div class="pw-seg" id="pws4"></div>
          </div>
          <div id="pwStrengthLabel" style="font-size:.72rem;margin-top:3px;min-height:1em"></div>
          <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:5px">
            <span class="pw-req" id="req-len">8 chars</span>
            <span class="pw-req" id="req-upper">Uppercase</span>
            <span class="pw-req" id="req-lower">Lowercase</span>
            <span class="pw-req" id="req-num">Number</span>
            <span class="pw-req" id="req-sym">Symbol</span>
          </div>
          <span class="field-error" id="passwordError"><?=e($errors['password'] ?? '')?></span>
        </div>
        <button type="submit" class="admin-login-btn">
          <i class="fas fa-user-plus"></i>
          <span>Create Account</span>
        </button>
      </form>

      <div class="login-footer">
        <p style="text-align:center;color:var(--muted);font-size:.9rem">
          Already have an account?
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

  document.getElementById('registerForm').addEventListener('submit', function(e) {
    let valid = true;
    const name     = document.getElementById('name');
    const email    = document.getElementById('email');
    const password = document.getElementById('password');
    const nameErr  = document.getElementById('nameError');
    const emailErr = document.getElementById('emailError');
    const passErr  = document.getElementById('passwordError');

    nameErr.textContent  = '';
    emailErr.textContent = '';
    passErr.textContent  = '';

    if(name.value.trim().length < 2) {
      nameErr.textContent = 'Full name must be at least 2 characters.';
      valid = false;
    }
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      emailErr.textContent = 'Please enter a valid email address.';
      valid = false;
    } else if(!allowedEmailDomain(email.value.trim())) {
      emailErr.textContent = 'Please use a recognised email provider (Gmail, Outlook, Yahoo, etc.).';
      valid = false;
    }
    if(password.value.length < 8) {
      passErr.textContent = 'Password must be at least 8 characters.';
      valid = false;
    } else if(!/[A-Z]/.test(password.value)) {
      passErr.textContent = 'Password must contain at least one uppercase letter.';
      valid = false;
    } else if(!/[a-z]/.test(password.value)) {
      passErr.textContent = 'Password must contain at least one lowercase letter.';
      valid = false;
    } else if(!/[0-9]/.test(password.value)) {
      passErr.textContent = 'Password must contain at least one number.';
      valid = false;
    } else if(!/[^A-Za-z0-9]/.test(password.value)) {
      passErr.textContent = 'Password must contain at least one special character.';
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

  function pwStrength(val) {
    var checks = {
      len:   val.length >= 8,
      upper: /[A-Z]/.test(val),
      lower: /[a-z]/.test(val),
      num:   /[0-9]/.test(val),
      sym:   /[^A-Za-z0-9]/.test(val)
    };
    var score = [checks.len, checks.upper, checks.lower, checks.num, checks.sym].filter(Boolean).length;
    document.getElementById('req-len').classList.toggle('met', checks.len);
    document.getElementById('req-upper').classList.toggle('met', checks.upper);
    document.getElementById('req-lower').classList.toggle('met', checks.lower);
    document.getElementById('req-num').classList.toggle('met', checks.num);
    document.getElementById('req-sym').classList.toggle('met', checks.sym);
    var fill  = score === 0 ? 0 : score <= 2 ? 1 : score === 3 ? 2 : score === 4 ? 3 : 4;
    var color = score === 0 ? '' : score <= 2 ? '#ef4444' : score === 3 ? '#f59e0b' : score === 4 ? '#eab308' : '#22c55e';
    var label = ['', 'Weak', 'Weak', 'Fair', 'Good', 'Strong'][score];
    for(var i = 1; i <= 4; i++) {
      document.getElementById('pws'+i).style.background = i <= fill ? color : 'rgba(255,255,255,.1)';
    }
    var lbl = document.getElementById('pwStrengthLabel');
    lbl.textContent = val.length ? label : '';
    lbl.style.color = color || 'var(--muted)';
  }
  document.getElementById('password').addEventListener('input', function() { pwStrength(this.value); });
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
