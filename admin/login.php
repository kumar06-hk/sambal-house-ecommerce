<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  if(rl_check($ip)){
    $error = 'Too many failed attempts. Please wait a few minutes before trying again.';
  } else {
    $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
    $s=$mysqli->prepare("SELECT id,name,password_hash FROM admins WHERE email=?");
    $s->bind_param('s',$email); $s->execute();
    if($a=$s->get_result()->fetch_assoc()){
      if(password_verify($pass,$a['password_hash'])){
        rl_clear($ip);
        session_regenerate_id(true);
        $_SESSION['admin_id']=$a['id']; $_SESSION['admin_name']=$a['name'];
        redirect(ADMIN_URL.'/index.php');
      }
    }
    rl_record($ip);
    $error="Invalid admin credentials.";
  }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Sambal House</title>
    <link rel="stylesheet" href="<?=BASE_URL?>/assets/style.css?v=<?=time()?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-login-container">
        <!-- Background Elements -->
        <div class="bg-pattern"></div>
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
        
        <!-- Login Card -->
        <div class="admin-login-card">
            <div class="login-header">
                <div class="logo-container">
                    <i class="fas fa-pepper-hot"></i>
                    <span>Sambal House</span>
                </div>
                <h1>Admin Portal</h1>
                <p>Sign in to manage your sambal empire</p>
            </div>
            
            <?php if(!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?= e($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="post" class="admin-login-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" required
                           placeholder="Enter your admin email" autocomplete="email"
                           data-no-domain-validate>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div style="position:relative">
                  <input type="password" id="password" name="password" required
                         placeholder="Enter your password" autocomplete="current-password" style="padding-right:42px;width:100%">
                  <button type="button" onclick="togglePw()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:1rem;padding:0">
                    <i class="fas fa-eye" id="eyeIcon"></i>
                  </button>
                </div>
                </div>
                
                <button type="submit" class="admin-login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Access Dashboard</span>
                </button>
            </form>
            
            <div class="login-footer">
                <div class="security-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure Admin Access</span>
                </div>
            </div>
        </div>
        
        <!-- Back to Shop Link -->
        <div class="back-to-shop">
            <a href="<?= BASE_URL ?>/">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Shop</span>
            </a>
        </div>
    </div>
<script>
function togglePw() {
  const input = document.getElementById('password');
  const icon  = document.getElementById('eyeIcon');
  if(input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}
</script>
</body>
</html>
