<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/mailer.php';

if (!is_logged_in()) {
    set_flash('error', 'Login Required', 'Please sign in to view your profile.');
    redirect(BASE_URL.'/login.php');
}

$uid = current_user_id();
$q   = $mysqli->prepare("SELECT name, nickname, email, phone, address_line1, address_line2, city, state, postcode FROM users WHERE id = ?");
$q->bind_param('i', $uid);
$q->execute();
$user = $q->get_result()->fetch_assoc();

$profileErrors = [];
$emailError    = '';
$pwError       = '';
$pwSuccess     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (($_POST['action'] ?? '') === 'update_profile') {
        $name      = trim($_POST['name']          ?? '');
        $nick      = trim($_POST['nickname']      ?? '');
        $phone     = trim($_POST['phone']         ?? '');
        $addr1     = trim($_POST['address_line1'] ?? '');
        $addr2     = trim($_POST['address_line2'] ?? '');
        $city_in   = trim($_POST['city']          ?? '');
        $state_in  = trim($_POST['state_region']  ?? '');
        $post_in   = trim($_POST['postcode']      ?? '');

        if (strlen($name) < 2)
            $profileErrors['name'] = 'Full name must be at least 2 characters.';
        elseif (mb_strlen($name) > 100)
            $profileErrors['name'] = 'Full name must be under 100 characters.';
        elseif (!validate_name($name))
            $profileErrors['name'] = 'Name may only contain letters, spaces, hyphens, apostrophes and dots.';
        $nick  = strip_tags($nick);
        $addr2 = strip_tags($addr2);

        if ($nick !== '' && strlen($nick) < 2)
            $profileErrors['nickname'] = 'Nickname must be at least 2 characters.';
        elseif ($nick !== '' && !validate_name($nick))
            $profileErrors['nickname'] = 'Nickname may only contain letters, spaces, hyphens, apostrophes and dots.';
        if ($phone === '')
            $profileErrors['phone'] = 'Phone number is required.';
        elseif (!preg_match('/^(\+?60|0)\d{8,10}$/', $phone))
            $profileErrors['phone'] = 'Enter a valid Malaysian phone number (e.g. 0123456789 or +60123456789).';
        if ($addr1 === '')
            $profileErrors['address_line1'] = 'Address Line 1 is required.';
        elseif (strlen($addr1) < 5)
            $profileErrors['address_line1'] = 'Address must be at least 5 characters.';
        elseif (!validate_address($addr1))
            $profileErrors['address_line1'] = 'Address may only contain letters, numbers, spaces and common punctuation (, . - / # &).';
        if ($addr2 !== '' && !validate_address($addr2))
            $profileErrors['address_line2'] = 'Address may only contain letters, numbers, spaces and common punctuation (, . - / # &).';
        if ($city_in === '')
            $profileErrors['city'] = 'City is required.';
        elseif (!preg_match('/^[\p{L}\s\'\-\.]{2,}$/u', $city_in))
            $profileErrors['city'] = 'City can only contain letters and spaces.';
        $validStates = ['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis',
                        'Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu',
                        'W.P. Kuala Lumpur','W.P. Labuan','W.P. Putrajaya'];
        if ($state_in === '')
            $profileErrors['state'] = 'State is required.';
        elseif (!in_array($state_in, $validStates))
            $profileErrors['state'] = 'Please select a valid state.';
        if ($post_in === '')
            $profileErrors['postcode'] = 'Postcode is required.';
        elseif (!preg_match('/^\d{5}$/', $post_in))
            $profileErrors['postcode'] = 'Postcode must be exactly 5 digits.';

        if (empty($profileErrors)) {
            $n_nick  = $nick     ?: null;
            $n_phone = $phone    ?: null;
            $n_addr1 = $addr1    ?: null;
            $n_addr2 = $addr2    ?: null;
            $n_city  = $city_in  ?: null;
            $n_state = $state_in ?: null;
            $n_post  = $post_in  ?: null;
            $upd = $mysqli->prepare("UPDATE users SET name=?, nickname=?, phone=?, address_line1=?, address_line2=?, city=?, state=?, postcode=? WHERE id=?");
            $upd->bind_param('ssssssssi', $name, $n_nick, $n_phone, $n_addr1, $n_addr2, $n_city, $n_state, $n_post, $uid);
            $upd->execute();
            $_SESSION['user_name'] = $name;
            set_flash('success', 'Profile Updated!', 'Your changes have been saved successfully.');
            redirect(BASE_URL.'/profile.php');
        }
    }

    if (($_POST['action'] ?? '') === 'change_password') {
        $curPw  = $_POST['current_password'] ?? '';
        $newPw  = $_POST['new_password']     ?? '';
        $conPw  = $_POST['confirm_password'] ?? '';

        $hashRow = $mysqli->prepare("SELECT password_hash FROM users WHERE id = ?");
        $hashRow->bind_param('i', $uid);
        $hashRow->execute();
        $hashData = $hashRow->get_result()->fetch_assoc();

        if (!$curPw) {
            $pwError = 'Please enter your current password.';
        } elseif (!password_verify($curPw, $hashData['password_hash'])) {
            $pwError = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 8) {
            $pwError = 'New password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $newPw)) {
            $pwError = 'New password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $newPw)) {
            $pwError = 'New password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $newPw)) {
            $pwError = 'New password must contain at least one number.';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $newPw)) {
            $pwError = 'New password must contain at least one special character.';
        } elseif ($newPw !== $conPw) {
            $pwError = 'New passwords do not match.';
        } elseif (password_verify($newPw, $hashData['password_hash'])) {
            $pwError = 'New password must be different from your current password.';
        } else {
            $newHash = password_hash($newPw, PASSWORD_BCRYPT);
            $upd = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $upd->bind_param('si', $newHash, $uid);
            $upd->execute();
            $pwSuccess = true;
        }
    }

    if (($_POST['action'] ?? '') === 'change_email') {
        $newEmail = trim($_POST['new_email'] ?? '');

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $emailError = 'Please enter a valid email address.';
        } elseif (!is_allowed_email_domain($newEmail)) {
            $emailError = 'Please use a recognised email provider (Gmail, Outlook, Yahoo, etc.).';
        } elseif (strtolower($newEmail) === strtolower($user['email'])) {
            $emailError = 'New email must be different from your current email.';
        } else {
            $chk = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->bind_param('si', $newEmail, $uid);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $emailError = 'This email is already registered to another account.';
            } else {
                $del = $mysqli->prepare("DELETE FROM email_changes WHERE user_id = ?");
                $del->bind_param('i', $uid);
                $del->execute();

                $otp        = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires_at = date('Y-m-d H:i:s', time() + 900);

                $ins = $mysqli->prepare("INSERT INTO email_changes (user_id, new_email, otp, expires_at) VALUES (?, ?, ?, ?)");
                $ins->bind_param('isss', $uid, $newEmail, $otp, $expires_at);
                $ins->execute();

                $sent = send_email_change_otp($newEmail, $user['name'], $otp);

                if (!$sent) {
                    $emailError = 'Could not send verification email. Please check that your email address is correct and try again.';
                } else {
                    $_SESSION['email_change_uid'] = $uid;
                    $_SESSION['email_change_new'] = $newEmail;
                    set_flash('success', 'Code Sent!', 'Check ' . $newEmail . ' for your 6-digit verification code.');
                    redirect(BASE_URL.'/verify_email_change.php');
                }
            }
        }
    }
}

if (!empty($profileErrors)) {
    $val = [
        'name'          => $_POST['name']          ?? $user['name'],
        'nickname'      => $_POST['nickname']      ?? ($user['nickname']      ?? ''),
        'phone'         => $_POST['phone']         ?? ($user['phone']         ?? ''),
        'address_line1' => $_POST['address_line1'] ?? ($user['address_line1'] ?? ''),
        'address_line2' => $_POST['address_line2'] ?? ($user['address_line2'] ?? ''),
        'city'          => $_POST['city']          ?? ($user['city']          ?? ''),
        'state'         => $_POST['state_region']  ?? ($user['state']         ?? ''),
        'postcode'      => $_POST['postcode']      ?? ($user['postcode']      ?? ''),
    ];
} else {
    $val = [
        'name'          => $user['name'],
        'nickname'      => $user['nickname']      ?? '',
        'phone'         => $user['phone']         ?? '',
        'address_line1' => $user['address_line1'] ?? '',
        'address_line2' => $user['address_line2'] ?? '',
        'city'          => $user['city']          ?? '',
        'state'         => $user['state']         ?? '',
        'postcode'      => $user['postcode']      ?? '',
    ];
}

$newEmailAttempt = $_POST['new_email'] ?? '';
$pageTitle = 'My Profile';
require_once __DIR__.'/includes/header.php';
?>
<header class="page-header">
  <div class="page-header-inner">
    <div class="ph-icon"><i class="fas fa-user-pen"></i></div>
    <div>
      <h1>My Profile</h1>
      <p>Manage your account details and delivery address</p>
    </div>
  </div>
</header>

<div class="container">
  <div class="co-layout">

    <!-- ── LEFT: Personal info + Delivery Address ── -->
    <div class="co-main">
      <form method="post" id="profileForm" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">

        <!-- Personal Information -->
        <div class="co-card">
          <div class="co-card-header"><i class="fas fa-user"></i> Personal Information</div>
          <div class="co-card-body">
            <div class="co-grid">

              <div class="co-field co-full <?=isset($profileErrors['name'])?'has-error':''?>">
                <label>Full Name <span class="req">*</span></label>
                <input type="text" name="name" id="prof_name"
                       value="<?=e($val['name'])?>"
                       placeholder="Your full name" autocomplete="name">
                <span class="field-error"><?=e($profileErrors['name'] ?? '')?></span>
              </div>

              <div class="co-field <?=isset($profileErrors['nickname'])?'has-error':''?>">
                <label>Nickname <span class="field-hint">optional</span></label>
                <input type="text" name="nickname" id="prof_nick"
                       value="<?=e($val['nickname'])?>"
                       placeholder="e.g. Jerry, Bos" autocomplete="nickname">
                <span class="field-error"><?=e($profileErrors['nickname'] ?? '')?></span>
              </div>

              <div class="co-field <?=isset($profileErrors['phone'])?'has-error':''?>">
                <label>Phone <span class="req">*</span></label>
                <input type="tel" name="phone" id="prof_phone"
                       value="<?=e($val['phone'])?>"
                       placeholder="e.g. 0123456789" autocomplete="tel" inputmode="tel"
                       maxlength="13">
                <span class="field-error"><?=e($profileErrors['phone'] ?? '')?></span>
              </div>

            </div>
          </div>
        </div>

        <!-- Delivery Address -->
        <div class="co-card">
          <div class="co-card-header"><i class="fas fa-map-marker-alt"></i> Delivery Address</div>
          <div class="co-card-body">
            <div class="co-grid">

              <div class="co-field co-full <?=isset($profileErrors['address_line1'])?'has-error':''?>">
                <label>Address Line 1 <span class="req">*</span></label>
                <input type="text" name="address_line1" id="prof_addr1"
                       value="<?=e($val['address_line1'])?>"
                       placeholder="Street address, building number" autocomplete="address-line1">
                <span class="field-error"><?=e($profileErrors['address_line1'] ?? '')?></span>
              </div>

              <div class="co-field co-full <?=isset($profileErrors['address_line2'])?'has-error':''?>">
                <label>Address Line 2 <span class="field-hint">optional</span></label>
                <input type="text" name="address_line2" id="prof_addr2"
                       value="<?=e($val['address_line2'])?>"
                       placeholder="Apartment, suite, unit, etc." autocomplete="address-line2">
                <span class="field-error"><?=e($profileErrors['address_line2'] ?? '')?></span>
              </div>

              <div class="co-field <?=isset($profileErrors['state'])?'has-error':''?>">
                <label>State <span class="req">*</span></label>
                <?php $selState = $val['state']; ?>
                <div class="ac-wrap">
                  <div class="ac-trigger" id="prof-state-trigger" tabindex="0">
                    <span id="prof-state-text" <?=$selState?'':'class="ac-placeholder"'?>><?=e($selState ?: '-- Select State --')?></span>
                    <i class="fas fa-chevron-down ac-chevron"></i>
                  </div>
                  <input type="hidden" name="state_region" id="prof_state" value="<?=e($selState)?>">
                  <div id="prof-state-ac" class="ac-dropdown"></div>
                </div>
                <span class="field-error"><?=e($profileErrors['state'] ?? '')?></span>
              </div>

              <div class="co-field <?=isset($profileErrors['city'])?'has-error':''?>">
                <label>City <span class="req">*</span></label>
                <div class="ac-wrap">
                  <input type="text" name="city" id="prof_city"
                         value="<?=e($val['city'])?>"
                         placeholder="<?=$selState ? 'Click to pick a city' : 'Select a state first'?>"
                         autocomplete="off">
                  <div id="prof-city-ac" class="ac-dropdown"></div>
                </div>
                <span class="field-error"><?=e($profileErrors['city'] ?? '')?></span>
              </div>

              <div class="co-field <?=isset($profileErrors['postcode'])?'has-error':''?>">
                <label>Postcode <span class="req">*</span></label>
                <input type="text" name="postcode" id="prof_postcode"
                       value="<?=e($val['postcode'])?>"
                       placeholder="50000" maxlength="5" inputmode="numeric">
                <span class="field-error"><?=e($profileErrors['postcode'] ?? '')?></span>
              </div>

              <div class="co-field">
                <label>Country</label>
                <input type="text" value="Malaysia" readonly style="opacity:.6;cursor:not-allowed">
              </div>

            </div>
          </div>
        </div>

        <button type="submit" class="co-submit-btn">
          <i class="fas fa-save"></i> Save Changes
        </button>

      </form>
    </div><!-- /.co-main -->

    <!-- ── RIGHT: Change Email + Security ── -->
    <div class="co-side">

      <!-- Change Email Card -->
      <div class="co-card">
        <div class="co-card-header"><i class="fas fa-envelope"></i> Change Email</div>
        <div class="co-card-body">

          <div style="margin-bottom:18px">
            <p style="color:var(--muted);font-size:.82rem;margin-bottom:6px">Current email</p>
            <div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:10px 14px;color:var(--text);font-size:.88rem;word-break:break-all">
              <i class="fas fa-envelope" style="color:var(--accent);margin-right:6px"></i>
              <?=e($user['email'])?>
            </div>
          </div>

          <?php if($emailError): ?>
          <div class="error-message" style="margin-bottom:14px;font-size:.85rem">
            <i class="fas fa-exclamation-triangle"></i> <?=e($emailError)?>
          </div>
          <?php endif; ?>

          <form method="post" id="emailForm" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_email">

            <div class="co-field" style="margin-bottom:14px">
              <label style="font-size:.82rem;color:var(--muted);display:block;margin-bottom:6px">
                New Email Address
              </label>
              <input type="email" name="new_email" id="new_email"
                     value="<?=e($newEmailAttempt)?>"
                     placeholder="your.new@email.com"
                     style="width:100%;padding:11px 14px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:10px;color:var(--text);font-size:.9rem;outline:none;transition:border-color .2s"
                     autocomplete="email">
              <span class="field-error" id="newEmailError"></span>
            </div>

            <button type="submit" class="co-submit-btn" style="font-size:.88rem;padding:11px 18px">
              <i class="fas fa-paper-plane"></i> Send Verification Code
            </button>
          </form>

          <p style="color:var(--muted);font-size:.76rem;margin-top:14px;line-height:1.65">
            A 6-digit code will be sent to your new email. You must verify it before the change takes effect.
          </p>

        </div>
      </div>

      <!-- Account Security Card -->
      <div class="co-card">
        <div class="co-card-header"><i class="fas fa-shield-halved"></i> Account Security</div>
        <div class="co-card-body">

          <?php if($pwSuccess): ?>
          <div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);border-radius:10px;padding:10px 14px;color:#4ade80;font-size:.85rem;margin-bottom:14px">
            <i class="fas fa-check-circle"></i> Password updated successfully!
          </div>
          <?php endif; ?>

          <?php if($pwError): ?>
          <div class="error-message" style="margin-bottom:14px;font-size:.85rem">
            <i class="fas fa-exclamation-triangle"></i> <?=e($pwError)?>
          </div>
          <?php endif; ?>

          <form method="post" id="pwForm" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="co-field" style="margin-bottom:12px">
              <label style="font-size:.82rem;color:var(--muted);display:block;margin-bottom:6px">Current Password</label>
              <div style="position:relative">
                <input type="password" name="current_password" id="cur_pw"
                       placeholder="Enter current password"
                       style="width:100%;padding:11px 42px 11px 14px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:10px;color:var(--text);font-size:.9rem;outline:none;transition:border-color .2s;box-sizing:border-box"
                       autocomplete="current-password">
                <button type="button" onclick="togglePw('cur_pw',this)" tabindex="-1"
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:.95rem;padding:0;line-height:1">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>

            <div class="co-field" style="margin-bottom:12px">
              <label style="font-size:.82rem;color:var(--muted);display:block;margin-bottom:6px">New Password</label>
              <div style="position:relative">
                <input type="password" name="new_password" id="new_pw"
                       placeholder="Min 8 chars, uppercase, number and symbol"
                       style="width:100%;padding:11px 42px 11px 14px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:10px;color:var(--text);font-size:.9rem;outline:none;transition:border-color .2s;box-sizing:border-box"
                       autocomplete="new-password">
                <button type="button" onclick="togglePw('new_pw',this)" tabindex="-1"
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:.95rem;padding:0;line-height:1">
                  <i class="fas fa-eye"></i>
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
            </div>

            <div class="co-field" style="margin-bottom:16px">
              <label style="font-size:.82rem;color:var(--muted);display:block;margin-bottom:6px">Confirm New Password</label>
              <div style="position:relative">
                <input type="password" name="confirm_password" id="con_pw"
                       placeholder="Repeat new password"
                       style="width:100%;padding:11px 42px 11px 14px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:10px;color:var(--text);font-size:.9rem;outline:none;transition:border-color .2s;box-sizing:border-box"
                       autocomplete="new-password">
                <button type="button" onclick="togglePw('con_pw',this)" tabindex="-1"
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:.95rem;padding:0;line-height:1">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
              <span class="field-error" id="pwFormError" style="margin-top:6px;display:block"></span>
            </div>

            <button type="submit"
              style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,56,56,.1);border:1px solid rgba(255,56,56,.25);border-radius:10px;padding:10px 16px;color:var(--accent);font-size:.88rem;font-weight:600;cursor:pointer;transition:background .2s;width:100%;justify-content:center">
              <i class="fas fa-key"></i> Update Password
            </button>
          </form>

        </div>
      </div>

    </div><!-- /.co-side -->

  </div><!-- /.co-layout -->
</div>

<style>
.pw-seg { flex:1; height:4px; border-radius:999px; background:rgba(255,255,255,.1); transition:background .3s; }
.pw-req { font-size:.7rem; padding:2px 8px; border-radius:999px; background:rgba(255,255,255,.06); color:var(--muted); border:1px solid rgba(255,255,255,.1); transition:all .2s; }
.pw-req.met { background:rgba(34,197,94,.12); color:#4ade80; border-color:rgba(34,197,94,.3); }
</style>
<script>
// ── Profile form validation ──
document.getElementById('profileForm').addEventListener('submit', function(e) {
  let valid = true;
  document.querySelectorAll('.js-perr').forEach(el => el.remove());
  document.querySelectorAll('.co-field').forEach(el => el.classList.remove('has-error'));

  function fErr(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.closest('.co-field').classList.add('has-error');
    const s = document.createElement('span');
    s.className = 'field-error js-perr';
    s.textContent = msg;
    el.parentNode.appendChild(s);
    valid = false;
  }

  const name     = document.getElementById('prof_name').value.trim();
  const nick     = document.getElementById('prof_nick').value.trim();
  const phone    = document.getElementById('prof_phone').value.trim();
  const addr1    = document.getElementById('prof_addr1').value.trim();
  const city     = document.getElementById('prof_city').value.trim();
  const state    = document.getElementById('prof_state').value.trim();
  const postcode = document.getElementById('prof_postcode').value.trim();

  if (name.length < 2)  fErr('prof_name', 'Full name must be at least 2 characters.');
  if (nick && nick.length < 2) fErr('prof_nick', 'Nickname must be at least 2 characters.');
  if (!phone)           fErr('prof_phone', 'Phone number is required.');
  else if (!/^(\+?60|0)\d{8,10}$/.test(phone)) fErr('prof_phone', 'Enter a valid Malaysian phone number (e.g. 0123456789 or +60123456789).');
  if (!addr1)           fErr('prof_addr1', 'Address Line 1 is required.');
  else if (addr1.length < 5) fErr('prof_addr1', 'Address must be at least 5 characters.');
  if (!state)           fErr('prof_state', 'State is required.');
  if (!city)            fErr('prof_city', 'City is required.');
  if (!postcode)        fErr('prof_postcode', 'Postcode is required.');
  else if (!/^\d{5}$/.test(postcode)) fErr('prof_postcode', 'Postcode must be 5 digits.');

  if (!valid) e.preventDefault();
});

// ── Password visibility toggle ──
function togglePw(inputId, btn) {
  var input = document.getElementById(inputId);
  var icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'fas fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fas fa-eye';
  }
}

// ── Password form validation ──
document.getElementById('pwForm').addEventListener('submit', function(e) {
  const cur  = document.getElementById('cur_pw').value;
  const nw   = document.getElementById('new_pw').value;
  const con  = document.getElementById('con_pw').value;
  const err  = document.getElementById('pwFormError');
  err.textContent = '';
  if (!cur) { err.textContent = 'Please enter your current password.'; e.preventDefault(); return; }
  if (nw.length < 8) { err.textContent = 'New password must be at least 8 characters.'; e.preventDefault(); return; }
  if (!/[A-Z]/.test(nw)) { err.textContent = 'New password must contain at least one uppercase letter.'; e.preventDefault(); return; }
  if (!/[a-z]/.test(nw)) { err.textContent = 'New password must contain at least one lowercase letter.'; e.preventDefault(); return; }
  if (!/[0-9]/.test(nw)) { err.textContent = 'New password must contain at least one number.'; e.preventDefault(); return; }
  if (!/[^A-Za-z0-9]/.test(nw)) { err.textContent = 'New password must contain at least one special character.'; e.preventDefault(); return; }
  if (nw !== con) { err.textContent = 'Passwords do not match.'; e.preventDefault(); }
});

// ── Password strength meter ──
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
  for (var i = 1; i <= 4; i++) {
    document.getElementById('pws'+i).style.background = i <= fill ? color : 'rgba(255,255,255,.1)';
  }
  var lbl = document.getElementById('pwStrengthLabel');
  lbl.textContent = val.length ? label : '';
  lbl.style.color = color || 'var(--muted)';
}
document.getElementById('new_pw').addEventListener('input', function() { pwStrength(this.value); });

// ── Email form validation ──
const AC_DOMAINS = [
  "gmail.com","yahoo.com","yahoo.com.my","outlook.com","hotmail.com",
  "hotmail.my","icloud.com","me.com","mac.com","live.com",
  "protonmail.com","proton.me","fastmail.com","zoho.com","gmx.com",
  "mail.com","qq.com","163.com","yandex.com","tm.net.my",
  "streamyx.com","naver.com","googlemail.com","msn.com","aol.com"
];
function allowedDomain(email) {
  const at = email.lastIndexOf('@');
  return at !== -1 && AC_DOMAINS.indexOf(email.slice(at + 1).toLowerCase()) !== -1;
}
document.getElementById('emailForm').addEventListener('submit', function(e) {
  const email = document.getElementById('new_email').value.trim();
  const err   = document.getElementById('newEmailError');
  err.textContent = '';
  if (!email) {
    err.textContent = 'Please enter a new email address.'; e.preventDefault();
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    err.textContent = 'Please enter a valid email address.'; e.preventDefault();
  } else if (!allowedDomain(email)) {
    err.textContent = 'Please use a recognised email provider.'; e.preventDefault();
  }
});

// ── Malaysian state / city data ──
const MY_DATA = {
  'Johor': {
    'Batu Pahat':'83000','Johor Bahru':'80000','Kluang':'86000','Kota Tinggi':'81900',
    'Kulai':'81000','Mersing':'86800','Muar':'84000','Pontian':'82000',
    'Segamat':'85000','Senai':'81400','Skudai':'81300','Tangkak':'84900'
  },
  'Kedah': {
    'Alor Setar':'05000','Baling':'09100','Gurun':'08300','Jitra':'06000',
    'Kuah (Langkawi)':'07000','Kuala Kedah':'06600','Kulim':'09000',
    'Pendang':'06700','Sungai Petani':'08000','Yan':'06800'
  },
  'Kelantan': {
    'Bachok':'16300','Gua Musang':'18300','Kota Bharu':'15000',
    'Kuala Krai':'18000','Pasir Mas':'17000','Pasir Puteh':'16800',
    'Tanah Merah':'17500','Tumpat':'16200'
  },
  'Melaka': {
    'Alor Gajah':'78000','Ayer Keroh':'75450','Jasin':'77000',
    'Masjid Tanah':'78300','Melaka':'75000','Merlimau':'77200'
  },
  'Negeri Sembilan': {
    'Bahau':'72100','Kuala Pilah':'72000','Nilai':'71800',
    'Port Dickson':'71000','Rembau':'71300','Seremban':'70000','Tampin':'73000'
  },
  'Pahang': {
    'Bentong':'28700','Cameron Highlands':'39000','Jerantut':'27000',
    'Kuala Lipis':'27200','Kuantan':'25000','Pekan':'26600',
    'Raub':'27600','Rompin':'26800','Temerloh':'28000'
  },
  'Perak': {
    'Batu Gajah':'31400','Ipoh':'30000','Kampar':'31900','Kuala Kangsar':'33000',
    'Lumut':'32200','Manjung':'32040','Sitiawan':'32000','Sungai Siput':'31100',
    'Taiping':'34000','Tanjung Malim':'35900','Teluk Intan':'36000'
  },
  'Perlis': { 'Arau':'02600','Kangar':'01000','Padang Besar':'02100' },
  'Pulau Pinang': {
    'Balik Pulau':'11000','Batu Ferringhi':'11100','Bayan Lepas':'11900',
    'Bukit Mertajam':'14000','Butterworth':'12000','George Town':'10000',
    'Kepala Batas':'13200','Nibong Tebal':'14300','Seberang Perai':'13000'
  },
  'Sabah': {
    'Beaufort':'87300','Keningau':'89000','Kota Belud':'89150',
    'Kota Kinabalu':'88000','Kudat':'89050','Lahad Datu':'91100',
    'Papar':'89600','Ranau':'89300','Sandakan':'90000','Tawau':'91000'
  },
  'Sarawak': {
    'Bintulu':'97000','Kapit':'96800','Kuching':'93000','Lawas':'98850',
    'Limbang':'98700','Miri':'98000','Mukah':'96400','Sarikei':'96100',
    'Sibu':'96000','Sri Aman':'95000'
  },
  'Selangor': {
    'Ampang':'68000','Ara Damansara':'47301','Cheras':'43200','Cyberjaya':'63000',
    'Kajang':'43000','Klang':'41000','Kuala Selangor':'45000','Pelabuhan Klang':'42000',
    'Petaling Jaya':'46000','Puchong':'47100','Rawang':'48000','Sepang':'43900',
    'Selayang':'68100','Shah Alam':'40000','Subang Jaya':'47500','Sungai Buloh':'47000'
  },
  'Terengganu': {
    'Besut':'22000','Dungun':'23000','Kemaman':'24000','Kuala Nerus':'21300',
    'Kuala Terengganu':'20000','Marang':'21600','Setiu':'22120'
  },
  'W.P. Kuala Lumpur': {
    'Bangsar':'59000','Brickfields':'50470','Bukit Bintang':'55100',
    'Chow Kit':'50300','Cheras (KL)':'56000','Kepong':'52100','Kuala Lumpur':'50000',
    'Segambut':'51200','Seputeh':'58000','Setapak':'53000','Titiwangsa':'53200','Wangsa Maju':'53300'
  },
  'W.P. Labuan': { 'Labuan':'87000','Victoria':'87007' },
  'W.P. Putrajaya': { 'Putrajaya':'62000','Presint 1':'62000','Presint 8':'62250','Presint 14':'62300' }
};

// ── State dropdown ──
(function() {
  const trigger    = document.getElementById('prof-state-trigger');
  const trigText   = document.getElementById('prof-state-text');
  const stateInput = document.getElementById('prof_state');
  const stateAc    = document.getElementById('prof-state-ac');
  const cityEl     = document.getElementById('prof_city');
  const cityAc     = document.getElementById('prof-city-ac');
  const postcodeEl = document.getElementById('prof_postcode');

  Object.keys(MY_DATA).forEach(function(st) {
    const item = document.createElement('div');
    item.className = 'ac-item' + (stateInput.value === st ? ' ac-active' : '');
    item.textContent = st;
    item.addEventListener('mousedown', function(ev) {
      ev.preventDefault();
      stateInput.value = st;
      trigText.textContent = st;
      trigText.classList.remove('ac-placeholder');
      stateAc.classList.remove('open');
      trigger.classList.remove('open');
      cityEl.value = ''; postcodeEl.value = '';
      cityAc.classList.remove('open'); cityAc.innerHTML = '';
      cityEl.placeholder = 'Click to pick a city';
    });
    stateAc.appendChild(item);
  });

  trigger.addEventListener('click', function() {
    const opening = !stateAc.classList.contains('open');
    stateAc.classList.toggle('open'); trigger.classList.toggle('open');
    if (opening) { const a = stateAc.querySelector('.ac-active'); if(a) a.scrollIntoView({block:'nearest'}); }
  });
  trigger.addEventListener('blur', function() {
    setTimeout(function() { stateAc.classList.remove('open'); trigger.classList.remove('open'); }, 160);
  });
  trigger.addEventListener('keydown', function(ev) {
    if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); trigger.click(); }
    else if (ev.key === 'Escape') { stateAc.classList.remove('open'); trigger.classList.remove('open'); }
  });
})();

// ── City autocomplete ──
(function() {
  const stateInput = document.getElementById('prof_state');
  const cityEl     = document.getElementById('prof_city');
  const postcodeEl = document.getElementById('prof_postcode');
  const acEl       = document.getElementById('prof-city-ac');

  function esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function showSuggestions(val) {
    const st = stateInput.value;
    acEl.innerHTML = '';
    if (!st || !MY_DATA[st]) { acEl.classList.remove('open'); return; }
    const q = val.toLowerCase();
    const all = Object.keys(MY_DATA[st]).sort();
    const matches = q ? all.filter(c => c.toLowerCase().includes(q)) : all;
    if (!matches.length) { acEl.classList.remove('open'); return; }
    matches.forEach(function(city) {
      const item = document.createElement('div');
      item.className = 'ac-item';
      if (q) {
        const i = city.toLowerCase().indexOf(q);
        item.innerHTML = esc(city.slice(0,i)) + '<strong>' + esc(city.slice(i,i+q.length)) + '</strong>' + esc(city.slice(i+q.length));
      } else { item.textContent = city; }
      item.addEventListener('mousedown', function(ev) {
        ev.preventDefault();
        cityEl.value     = city;
        postcodeEl.value = MY_DATA[st][city] || '';
        acEl.classList.remove('open');
      });
      acEl.appendChild(item);
    });
    acEl.classList.add('open');
  }

  cityEl.addEventListener('focus', function() { showSuggestions(this.value.trim()); });
  cityEl.addEventListener('input', function() { showSuggestions(this.value.trim()); });
  cityEl.addEventListener('blur',  function() { setTimeout(() => acEl.classList.remove('open'), 160); });

  cityEl.addEventListener('keydown', function(ev) {
    const items  = acEl.querySelectorAll('.ac-item');
    const active = acEl.querySelector('.ac-active');
    let idx = active ? Array.from(items).indexOf(active) : -1;
    if (ev.key === 'ArrowDown') {
      ev.preventDefault();
      if (active) active.classList.remove('ac-active');
      const next = items[(idx+1) % items.length];
      if (next) { next.classList.add('ac-active'); next.scrollIntoView({block:'nearest'}); }
    } else if (ev.key === 'ArrowUp') {
      ev.preventDefault();
      if (active) active.classList.remove('ac-active');
      const prev = items[(idx-1+items.length) % items.length];
      if (prev) { prev.classList.add('ac-active'); prev.scrollIntoView({block:'nearest'}); }
    } else if (ev.key === 'Enter' && active) {
      ev.preventDefault(); active.dispatchEvent(new MouseEvent('mousedown'));
    } else if (ev.key === 'Escape') { acEl.classList.remove('open'); }
  });
})();
</script>
<script src="<?=BASE_URL?>/assets/email-autocomplete.js"></script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
