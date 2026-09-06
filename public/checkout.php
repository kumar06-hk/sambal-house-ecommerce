<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

if(!is_logged_in()){
  set_flash('error', 'Login Required', 'Please sign in to proceed with checkout.');
  redirect(BASE_URL.'/login.php');
}

$cartId = get_or_create_cart($mysqli);
$totals = cart_totals($mysqli, $cartId);
if($totals['items'] == 0) redirect(BASE_URL.'/cart.php');

// Fetch cart items for order summary sidebar
$ci = $mysqli->prepare("SELECT p.name, p.image, ci.qty, p.price FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.cart_id=?");
$ci->bind_param('i', $cartId);
$ci->execute();
$cartItems = $ci->get_result()->fetch_all(MYSQLI_ASSOC);

$prefill = ['name'=>'','email'=>'','phone'=>'','address_line1'=>null,'address_line2'=>null,'city'=>null,'state'=>null,'postcode'=>null];
$uid = current_user_id();
$s   = $mysqli->prepare("SELECT name, email, phone, address_line1, address_line2, city, state, postcode FROM users WHERE id=?");
$s->bind_param('i', $uid);
$s->execute();
$prefill   = $s->get_result()->fetch_assoc() ?: $prefill;
$hasSaved  = !empty($prefill['address_line1']);

$errors  = [];
$posted  = [];

$allowedBanks = ['MAYBANK','CIMB','PUBLIC','RHB','HONGLEONG','AMBANK','BANKISLAM','TOUCHNGO'];

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  $full_name = trim($_POST['full_name'] ?? '');
  $email     = trim($_POST['email']     ?? '');
  $phone     = trim($_POST['phone']     ?? '');
  $addr1     = trim($_POST['address_line1'] ?? '');
  $addr2     = trim($_POST['address_line2'] ?? '');
  $city      = trim($_POST['city']          ?? '');
  $state     = trim($_POST['state_region']  ?? '');
  $postcode  = trim($_POST['postcode']      ?? '');
  $country   = trim($_POST['country']       ?? 'Malaysia');
  $pmethod   = 'FPX';
  $bank      = strtoupper(trim($_POST['bank_code'] ?? ''));
  $isAjax    = ($_POST['is_ajax'] ?? '') === '1';
  $status    = 'PENDING';

  // Keep posted values to re-fill form on error
  $posted = compact('full_name','email','phone','addr1','addr2','city','state','postcode');

  // --- Validation ---
  if(strlen($full_name) < 2)
    $errors['full_name'] = 'Full name must be at least 2 characters.';
  elseif(!preg_match('/^[\p{L}\s\'\-\.]{2,120}$/u', $full_name))
    $errors['full_name'] = 'Name can only contain letters, spaces, hyphens and apostrophes.';

  if(!filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors['email'] = 'Please enter a valid email address.';
  elseif(!is_allowed_email_domain($email))
    $errors['email'] = 'Please use a recognised email provider (Gmail, Outlook, Yahoo, etc.).';

  if($phone !== '' && !preg_match('/^[+\d][\d\s\-\(\)]{5,18}$/', $phone))
    $errors['phone'] = 'Phone must contain only digits, spaces, +, - or (). Min 6 digits.';

  if(strlen($addr1) < 5)
    $errors['addr1'] = 'Please enter a complete address (min 5 characters).';

  if(strlen($city) < 2)
    $errors['city'] = 'City is required.';
  elseif(!preg_match('/^[\p{L}\s\'\-\.]{2,}$/u', $city))
    $errors['city'] = 'City can only contain letters and spaces.';

  $validStates = ['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','W.P. Kuala Lumpur','W.P. Labuan','W.P. Putrajaya'];
  if(!in_array($state, $validStates))
    $errors['state'] = 'Please select a valid Malaysian state.';

  if(!preg_match('/^\d{5}$/', $postcode))
    $errors['postcode'] = 'Postcode must be exactly 5 digits (e.g. 50000).';

  if(!in_array($bank, $allowedBanks))
    $errors['bank'] = 'Please select a bank to proceed.';

  if($isAjax && !empty($errors)){
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => reset($errors)]);
    exit;
  }

  if(empty($errors)){
    // Align cart quantities to current stock
    $needAdjust = false;
    $q = $mysqli->prepare("SELECT ci.id, ci.qty, p.stock FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.cart_id=?");
    $q->bind_param('i', $cartId);
    $q->execute();
    $res = $q->get_result();
    while($row = $res->fetch_assoc()){
      $wanted = (int)$row['qty'];
      $stock  = (int)$row['stock'];
      $newQty = min($wanted, $stock);
      if($newQty !== $wanted){
        $needAdjust = true;
        if($newQty <= 0){
          $d = $mysqli->prepare("DELETE FROM cart_items WHERE id=? AND cart_id=?");
          $d->bind_param('ii', $row['id'], $cartId);
          $d->execute();
        } else {
          $u = $mysqli->prepare("UPDATE cart_items SET qty=? WHERE id=? AND cart_id=?");
          $u->bind_param('iii', $newQty, $row['id'], $cartId);
          $u->execute();
        }
      }
    }
    if($needAdjust){
      if($isAjax){
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Some quantities were adjusted. Please review your cart.']);
        exit;
      }
      set_flash('error', 'Stock changed', 'Some quantities were adjusted. Please review your cart.');
      redirect(BASE_URL.'/cart.php');
    }

    $totals = cart_totals($mysqli, $cartId);

    $mysqli->begin_transaction();
    try {
      $ins = $mysqli->prepare("INSERT INTO orders (user_id,full_name,email,phone,address_line1,address_line2,city,state_region,postcode,country,payment_method,status,total) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $ins->bind_param('isssssssssssd', $uid, $full_name, $email, $phone, $addr1, $addr2, $city, $state, $postcode, $country, $pmethod, $status, $totals['total']);
      $ins->execute();
      $orderId = $ins->insert_id;

      $q = $mysqli->prepare("SELECT p.id pid, p.name, p.price, ci.qty FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.cart_id=? FOR UPDATE");
      $q->bind_param('i', $cartId);
      $q->execute();
      $items = $q->get_result()->fetch_all(MYSQLI_ASSOC);
      if(!$items) throw new Exception('Cart is empty.');

      foreach($items as $row){
        $pid = (int)$row['pid'];
        $qty = (int)$row['qty'];
        $upd = $mysqli->prepare("UPDATE products SET stock = stock - ? WHERE id=? AND stock >= ?");
        $upd->bind_param('iii', $qty, $pid, $qty);
        $upd->execute();
        if($upd->affected_rows === 0) throw new Exception('Not enough stock for: '.$row['name']);
        $oi = $mysqli->prepare("INSERT INTO order_items (order_id, product_id, name, price, qty) VALUES (?,?,?,?,?)");
        $oi->bind_param('iisdi', $orderId, $pid, $row['name'], $row['price'], $qty);
        $oi->execute();
      }

      $del = $mysqli->prepare("DELETE FROM cart_items WHERE cart_id=?");
      $del->bind_param('i', $cartId);
      $del->execute();

      $mysqli->commit();
      track_activity('Completed order #'.$orderId);

      if($isAjax){
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'order_id' => $orderId]);
        exit;
      }
      set_flash('success', 'Order placed!', 'Your order has been created successfully.');
      redirect(BASE_URL.'/order_success.php?id='.$orderId);

    } catch(Exception $ex){
      $mysqli->rollback();
      if($isAjax){
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $ex->getMessage().' Please review your cart.']);
        exit;
      }
      set_flash('error', 'Order failed', $ex->getMessage().' Please review your cart.');
      redirect(BASE_URL.'/cart.php');
    }
  }
}

// Helper to re-populate fields after validation failure
function pv(string $key, array $posted, array $prefill): string {
  if(isset($posted[$key])) return e($posted[$key]);
  $map = ['full_name'=>'name','email'=>'email','phone'=>'phone'];
  return isset($map[$key]) ? e($prefill[$map[$key]] ?? '') : '';
}

track_activity('Checking out');
$pageTitle = 'Checkout';
require_once __DIR__.'/includes/header.php';
?>
<style>
.fpx-method-label {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px;
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 10px;
  font-weight: 600; font-size: .95rem;
  margin-bottom: 14px;
}
.fpx-badge {
  background: linear-gradient(135deg, #0046a8, #0070cc);
  color: #fff; font-size: .72rem; font-weight: 700;
  padding: 4px 9px; border-radius: 6px; letter-spacing: .5px;
  white-space: nowrap;
}
.bank-select-hint { font-size: .82rem; color: var(--muted); margin: 0 0 10px; }
.bank-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 9px;
}
.bank-card {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 12px 6px;
  background: rgba(255,255,255,.04);
  border: 2px solid rgba(255,255,255,.08);
  border-radius: 12px;
  cursor: pointer;
  transition: border-color .15s, background .15s, box-shadow .15s;
  text-align: center; user-select: none;
}
.bank-card:hover {
  border-color: rgba(255,56,56,.45);
  background: rgba(255,56,56,.06);
}
.bank-card.selected {
  border-color: var(--accent);
  background: rgba(255,56,56,.1);
  box-shadow: 0 0 0 3px rgba(255,56,56,.18);
}
.bank-logo-img {
  width: 64px; height: 64px; border-radius: 50%;
  display: block;
  box-shadow: 0 3px 10px rgba(0,0,0,.4);
  transition: transform .15s;
}
.bank-card:hover .bank-logo-img { transform: scale(1.07); }
.bank-card.selected .bank-logo-img { box-shadow: 0 0 0 3px var(--accent), 0 3px 10px rgba(0,0,0,.4); }
.bank-card span { font-size: .76rem; font-weight: 600; color: var(--text); line-height: 1.3; }
</style>
<header class="page-header">
  <div class="page-header-inner">
    <div class="ph-icon"><i class="fas fa-credit-card"></i></div>
    <div><h1>Checkout</h1><p>Almost there — just a few details</p></div>
  </div>
</header>

<div class="container">
  <a href="<?=BASE_URL?>/cart.php" class="back-button">Back to Cart</a>
  <form method="post" id="checkoutForm" novalidate>
    <?= csrf_field() ?>

    <div class="co-layout">

      <!-- ── LEFT: form fields ── -->
      <div class="co-main">

        <!-- Personal Information -->
        <div class="co-card">
          <div class="co-card-header"><i class="fas fa-user"></i> Personal Information</div>
          <div class="co-card-body">
            <div class="co-grid">
              <div class="co-field <?=isset($errors['full_name'])?'has-error':''?>">
                <label>Full Name <span class="req">*</span></label>
                <input name="full_name" id="full_name" value="<?=pv('full_name',$posted,$prefill)?>" placeholder="Enter your full name" autocomplete="name">
                <?php if(isset($errors['full_name'])): ?><span class="field-error"><?=e($errors['full_name'])?></span><?php endif; ?>
              </div>
              <div class="co-field <?=isset($errors['email'])?'has-error':''?>">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" id="c_email" value="<?=pv('email',$posted,$prefill)?>" placeholder="you@example.com" autocomplete="email">
                <?php if(isset($errors['email'])): ?><span class="field-error"><?=e($errors['email'])?></span><?php endif; ?>
              </div>
              <div class="co-field <?=isset($errors['phone'])?'has-error':''?> co-full">
                <label>Phone <span class="field-hint">optional</span></label>
                <input name="phone" id="phone" value="<?=pv('phone',$posted,$prefill)?>" placeholder="+60 12-345 6789" autocomplete="tel" inputmode="tel">
                <?php if(isset($errors['phone'])): ?><span class="field-error"><?=e($errors['phone'])?></span><?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Shipping Address -->
        <div class="co-card">
          <div class="co-card-header"><i class="fas fa-map-marker-alt"></i> Shipping Address</div>
          <div class="co-card-body">
            <?php if($hasSaved && empty($posted)): ?>
            <div class="saved-addr-banner">
              <label class="saved-addr-check">
                <input type="checkbox" id="use_saved_addr" checked>
                <span><i class="fas fa-location-dot"></i> Use my saved address</span>
              </label>
            </div>
            <?php elseif($hasSaved): ?>
            <div class="saved-addr-banner">
              <label class="saved-addr-check">
                <input type="checkbox" id="use_saved_addr">
                <span><i class="fas fa-location-dot"></i> Use my saved address</span>
              </label>
            </div>
            <?php endif; ?>
            <div class="co-grid">
              <div class="co-field co-full <?=isset($errors['addr1'])?'has-error':''?>">
                <label>Address Line 1 <span class="req">*</span></label>
                <input name="address_line1" id="addr1" value="<?=e($posted['addr1']??'')?>" placeholder="Street address, building number" autocomplete="address-line1">
                <?php if(isset($errors['addr1'])): ?><span class="field-error"><?=e($errors['addr1'])?></span><?php endif; ?>
              </div>
              <div class="co-field co-full">
                <label>Address Line 2 <span class="field-hint">optional</span></label>
                <input id="addr2" name="address_line2" value="<?=e($posted['addr2']??'')?>" placeholder="Apartment, suite, unit, etc." autocomplete="address-line2">
              </div>
              <div class="co-field <?=isset($errors['state'])?'has-error':''?>">
                <label>State <span class="req">*</span></label>
                <?php $selState = $posted['state'] ?? ''; ?>
                <div class="ac-wrap">
                  <div class="ac-trigger" id="state-trigger" tabindex="0">
                    <span id="state-text" <?=$selState?'':'class="ac-placeholder"'?>><?=e($selState ?: '-- Select State --')?></span>
                    <i class="fas fa-chevron-down ac-chevron"></i>
                  </div>
                  <input type="hidden" name="state_region" id="state" value="<?=e($selState)?>">
                  <div id="state-ac" class="ac-dropdown"></div>
                </div>
                <?php if(isset($errors['state'])): ?><span class="field-error"><?=e($errors['state'])?></span><?php endif; ?>
              </div>
              <div class="co-field <?=isset($errors['city'])?'has-error':''?>">
                <label>City <span class="req">*</span></label>
                <div class="ac-wrap">
                  <input name="city" id="city" value="<?=e($posted['city']??'')?>" placeholder="Select a state first" autocomplete="off">
                  <div id="city-ac" class="ac-dropdown"></div>
                </div>
                <?php if(isset($errors['city'])): ?><span class="field-error"><?=e($errors['city'])?></span><?php endif; ?>
              </div>
              <div class="co-field <?=isset($errors['postcode'])?'has-error':''?>">
                <label>Postcode <span class="req">*</span></label>
                <input name="postcode" id="postcode" value="<?=e($posted['postcode']??'')?>" placeholder="50000" maxlength="5" inputmode="numeric">
                <?php if(isset($errors['postcode'])): ?><span class="field-error"><?=e($errors['postcode'])?></span><?php endif; ?>
              </div>
              <div class="co-field">
                <label>Country</label>
                <input name="country" value="Malaysia" readonly style="opacity:.6;cursor:not-allowed">
              </div>
            </div>
          </div>
        </div>

      </div><!-- /.co-main -->

      <!-- ── RIGHT: summary + payment ── -->
      <div class="co-side">

        <!-- Order Summary -->
        <div class="co-card">
          <div class="co-card-header"><i class="fas fa-receipt"></i> Order Summary</div>
          <div class="co-card-body" style="padding:0">
            <ul class="co-summary-list">
              <?php foreach($cartItems as $item): ?>
              <li class="co-summary-item">
                <div class="co-item-info">
                  <span class="co-item-name"><?=e($item['name'])?></span>
                  <span class="co-item-qty">× <?=$item['qty']?></span>
                </div>
                <span class="co-item-price">RM <?=number_format($item['price']*$item['qty'],2)?></span>
              </li>
              <?php endforeach; ?>
            </ul>
            <div class="co-total-row">
              <span>Total</span>
              <span class="co-total-amount">RM <?=number_format($totals['total'],2)?></span>
            </div>
          </div>
        </div>

        <!-- Payment -->
        <div class="co-card">
          <div class="co-card-header"><i class="fas fa-wallet"></i> Payment Method</div>
          <div class="co-card-body">
            <input type="hidden" name="payment_method" value="FPX">
            <input type="hidden" name="bank_code" id="bank_code" value="">
            <input type="hidden" name="is_ajax" value="1">
            <div class="fpx-method-label">
              <span class="fpx-badge"><i class="fas fa-shield-alt"></i> FPX</span>
              <span>Online Banking (FPX)</span>
            </div>
            <p class="bank-select-hint">Select your bank:</p>
            <div class="bank-grid" id="bankGrid">
              <div class="bank-card" data-code="MAYBANK" onclick="selectBank(this)">
                <img src="<?=bank_logo('MAYBANK')?>" alt="Maybank" class="bank-logo-img">
                <span>Maybank</span>
              </div>
              <div class="bank-card" data-code="CIMB" onclick="selectBank(this)">
                <img src="<?=bank_logo('CIMB')?>" alt="CIMB Bank" class="bank-logo-img">
                <span>CIMB Bank</span>
              </div>
              <div class="bank-card" data-code="PUBLIC" onclick="selectBank(this)">
                <img src="<?=bank_logo('PUBLIC')?>" alt="Public Bank" class="bank-logo-img">
                <span>Public Bank</span>
              </div>
              <div class="bank-card" data-code="RHB" onclick="selectBank(this)">
                <img src="<?=bank_logo('RHB')?>" alt="RHB Bank" class="bank-logo-img">
                <span>RHB Bank</span>
              </div>
              <div class="bank-card" data-code="HONGLEONG" onclick="selectBank(this)">
                <img src="<?=bank_logo('HONGLEONG')?>" alt="Hong Leong Bank" class="bank-logo-img">
                <span>Hong Leong</span>
              </div>
              <div class="bank-card" data-code="AMBANK" onclick="selectBank(this)">
                <img src="<?=bank_logo('AMBANK')?>" alt="AmBank" class="bank-logo-img">
                <span>AmBank</span>
              </div>
              <div class="bank-card" data-code="BANKISLAM" onclick="selectBank(this)">
                <img src="<?=bank_logo('BANKISLAM')?>" alt="Bank Islam" class="bank-logo-img">
                <span>Bank Islam</span>
              </div>
              <div class="bank-card" data-code="TOUCHNGO" onclick="selectBank(this)">
                <img src="<?=bank_logo('TOUCHNGO')?>" alt="Touch 'n Go" class="bank-logo-img">
                <span>Touch 'n Go</span>
              </div>
            </div>
            <div id="bank-error" class="field-error" style="display:none;margin-top:8px"></div>
          </div>
        </div>

        <button class="co-submit-btn" type="submit" id="placeOrderBtn">
          <i class="fas fa-lock"></i> Place Order
        </button>
        <p class="co-secure-note"><i class="fas fa-shield-alt"></i> Your information is secure &amp; encrypted</p>

      </div><!-- /.co-side -->

    </div><!-- /.co-layout -->
  </form>
</div>

<script>
var CO_DOMAINS = [
  "gmail.com","yahoo.com","yahoo.com.my","outlook.com","hotmail.com",
  "hotmail.my","icloud.com","me.com","mac.com","live.com",
  "protonmail.com","proton.me","fastmail.com","zoho.com","gmx.com",
  "mail.com","qq.com","163.com","yandex.com","tm.net.my",
  "streamyx.com","naver.com","googlemail.com","msn.com","aol.com"
];
function coAllowedDomain(email) {
  var at = email.lastIndexOf('@');
  return at !== -1 && CO_DOMAINS.indexOf(email.slice(at + 1).toLowerCase()) !== -1;
}

document.getElementById('checkoutForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  let valid = true;

  // Clear old JS errors
  document.querySelectorAll('.js-error').forEach(el => el.remove());
  document.querySelectorAll('.co-field').forEach(el => el.classList.remove('has-error'));

  function fieldErr(id, msg) {
    const el = document.getElementById(id);
    if(!el) return;
    el.closest('.co-field').classList.add('has-error');
    const span = document.createElement('span');
    span.className = 'field-error js-error';
    span.textContent = msg;
    el.after(span);
    valid = false;
  }

  const name     = document.getElementById('full_name').value.trim();
  const email    = document.getElementById('c_email').value.trim();
  const phone    = document.getElementById('phone').value.trim();
  const addr1    = document.getElementById('addr1').value.trim();
  const city     = document.getElementById('city').value.trim();
  const state    = document.getElementById('state').value.trim();
  const postcode = document.getElementById('postcode').value.trim();
  const bankCode = document.getElementById('bank_code').value;

  if(name.length < 2)
    fieldErr('full_name', 'Full name is required (min 2 characters).');
  else if(!/^[\p{L}\s'\-\.]{2,}$/u.test(name))
    fieldErr('full_name', 'Name can only contain letters, spaces, hyphens and apostrophes.');

  if(!email)
    fieldErr('c_email', 'Email is required.');
  else if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
    fieldErr('c_email', 'Please enter a valid email address.');
  else if(!coAllowedDomain(email))
    fieldErr('c_email', 'Please use a recognised email provider (Gmail, Outlook, Yahoo, etc.).');

  if(phone && !/^[+\d][\d\s\-\(\)]{5,18}$/.test(phone))
    fieldErr('phone', 'Phone must only contain digits, spaces, +, - or ().');

  if(addr1.length < 5)
    fieldErr('addr1', 'Please enter a complete address.');

  if(city.length < 2)
    fieldErr('city', 'City is required.');
  else if(!/^[\p{L}\s'\-\.]{2,}$/u.test(city))
    fieldErr('city', 'City can only contain letters and spaces.');

  if(!state)
    fieldErr('state-trigger', 'Please select a state.');

  if(!/^\d{5}$/.test(postcode))
    fieldErr('postcode', 'Postcode must be exactly 5 digits (e.g. 50000).');

  // Bank validation
  const bankErr = document.getElementById('bank-error');
  if(!bankCode) {
    bankErr.textContent = 'Please select a bank to proceed.';
    bankErr.style.display = 'block';
    valid = false;
  } else {
    bankErr.style.display = 'none';
  }

  if(!valid) return;

  const btn = document.getElementById('placeOrderBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

  // Open blank tab synchronously (in click handler context) to avoid popup blockers
  const gatewayWin = window.open('about:blank', '_blank');

  try {
    const resp = await fetch(window.location.href, {
      method: 'POST',
      body: new FormData(this),
    });
    const data = await resp.json();

    if(data.ok) {
      const gatewayUrl = window.location.origin + '<?=BASE_URL?>/bank_gateway.php?bank=' + encodeURIComponent(bankCode) + '&order_id=' + data.order_id;
      if(gatewayWin && !gatewayWin.closed) {
        gatewayWin.location.href = gatewayUrl;
      } else {
        // Popup blocked — open in same tab as fallback
        window.location.href = gatewayUrl;
      }
      btn.innerHTML = '<i class="fas fa-check-circle"></i> Payment Window Opened';
    } else {
      if(gatewayWin) gatewayWin.close();
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-lock"></i> Place Order';
      Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Something went wrong. Please try again.' });
    }
  } catch(err) {
    if(gatewayWin) gatewayWin.close();
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-lock"></i> Place Order';
    Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server. Please try again.' });
  }
});

function selectBank(el) {
  document.querySelectorAll('.bank-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('bank_code').value = el.dataset.code;
  document.getElementById('bank-error').style.display = 'none';
}

// ── Malaysian city / postcode data ──
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
  'Perlis': {
    'Arau':'02600','Kangar':'01000','Padang Besar':'02100'
  },
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
    'Segambut':'51200','Seputeh':'58000','Setapak':'53000','Titiwangsa':'53200',
    'Wangsa Maju':'53300'
  },
  'W.P. Labuan': {
    'Labuan':'87000','Victoria':'87007'
  },
  'W.P. Putrajaya': {
    'Putrajaya':'62000','Presint 1':'62000','Presint 8':'62250','Presint 14':'62300'
  }
};

// ── State custom dropdown ──
(function() {
  const trigger    = document.getElementById('state-trigger');
  const trigText   = document.getElementById('state-text');
  const stateInput = document.getElementById('state');
  const stateAc    = document.getElementById('state-ac');
  const cityEl     = document.getElementById('city');
  const cityAc     = document.getElementById('city-ac');
  const postcodeEl = document.getElementById('postcode');

  function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

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
      // reset city
      cityEl.value = ''; postcodeEl.value = '';
      cityAc.classList.remove('open'); cityAc.innerHTML = '';
      cityEl.placeholder = 'Click to pick a city';
    });
    stateAc.appendChild(item);
  });

  trigger.addEventListener('click', function() {
    const opening = !stateAc.classList.contains('open');
    stateAc.classList.toggle('open');
    trigger.classList.toggle('open');
    if(opening) {
      const active = stateAc.querySelector('.ac-active');
      if(active) active.scrollIntoView({block:'nearest'});
    }
  });
  trigger.addEventListener('blur', function() {
    setTimeout(function() { stateAc.classList.remove('open'); trigger.classList.remove('open'); }, 160);
  });
  trigger.addEventListener('keydown', function(ev) {
    if(ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); trigger.click(); }
    else if(ev.key === 'Escape') { stateAc.classList.remove('open'); trigger.classList.remove('open'); }
  });
})();

// ── City autocomplete ──
(function() {
  const stateInput = document.getElementById('state');
  const cityEl     = document.getElementById('city');
  const postcodeEl = document.getElementById('postcode');
  const acEl       = document.getElementById('city-ac');

  function showSuggestions(val) {
    const st = stateInput.value;
    acEl.innerHTML = '';
    if(!st || !MY_DATA[st]) { acEl.classList.remove('open'); return; }
    const q = val.toLowerCase();
    const all = Object.keys(MY_DATA[st]).sort();
    const matches = q ? all.filter(c => c.toLowerCase().includes(q)) : all;
    if(!matches.length) { acEl.classList.remove('open'); return; }
    matches.forEach(function(city) {
      const item = document.createElement('div');
      item.className = 'ac-item';
      if(q) {
        const i = city.toLowerCase().indexOf(q);
        item.innerHTML = esc(city.slice(0,i)) + '<strong>' + esc(city.slice(i,i+q.length)) + '</strong>' + esc(city.slice(i+q.length));
      } else {
        item.textContent = city;
      }
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

  function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  cityEl.addEventListener('focus',  function() { showSuggestions(this.value.trim()); });
  cityEl.addEventListener('input',  function() { showSuggestions(this.value.trim()); });
  cityEl.addEventListener('blur',   function() { setTimeout(() => acEl.classList.remove('open'), 160); });

  cityEl.addEventListener('keydown', function(ev) {
    const items  = acEl.querySelectorAll('.ac-item');
    const active = acEl.querySelector('.ac-active');
    let idx = active ? Array.from(items).indexOf(active) : -1;
    if(ev.key === 'ArrowDown') {
      ev.preventDefault();
      if(active) active.classList.remove('ac-active');
      const next = items[(idx+1) % items.length];
      if(next){ next.classList.add('ac-active'); next.scrollIntoView({block:'nearest'}); }
    } else if(ev.key === 'ArrowUp') {
      ev.preventDefault();
      if(active) active.classList.remove('ac-active');
      const prev = items[(idx-1+items.length) % items.length];
      if(prev){ prev.classList.add('ac-active'); prev.scrollIntoView({block:'nearest'}); }
    } else if(ev.key === 'Enter' && active) {
      ev.preventDefault();
      active.dispatchEvent(new MouseEvent('mousedown'));
    } else if(ev.key === 'Escape') {
      acEl.classList.remove('open');
    }
  });
})();

// ── Email autocomplete & domain validation ──

<?php if($hasSaved): ?>
var SAVED_ADDR = <?= json_encode([
  'addr1'    => $prefill['address_line1'] ?? '',
  'addr2'    => $prefill['address_line2'] ?? '',
  'city'     => $prefill['city']          ?? '',
  'state'    => $prefill['state']         ?? '',
  'postcode' => $prefill['postcode']      ?? '',
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

(function() {
  var cb = document.getElementById('use_saved_addr');
  if (!cb) return;

  function fillSaved() {
    document.getElementById('addr1').value   = SAVED_ADDR.addr1;
    document.getElementById('addr2').value   = SAVED_ADDR.addr2;
    document.getElementById('city').value    = SAVED_ADDR.city;
    document.getElementById('postcode').value = SAVED_ADDR.postcode;
    if (SAVED_ADDR.state) {
      var stateInput = document.getElementById('state');
      var trigText   = document.getElementById('state-text');
      stateInput.value = SAVED_ADDR.state;
      trigText.textContent = SAVED_ADDR.state;
      trigText.classList.remove('ac-placeholder');
      document.querySelectorAll('#state-ac .ac-item').forEach(function(item) {
        item.classList.toggle('ac-active', item.textContent === SAVED_ADDR.state);
      });
    }
  }

  function clearAddr() {
    ['addr1','addr2','city','postcode'].forEach(function(id){ document.getElementById(id).value = ''; });
    var stateInput = document.getElementById('state');
    var trigText   = document.getElementById('state-text');
    stateInput.value = '';
    trigText.textContent = '-- Select State --';
    trigText.classList.add('ac-placeholder');
    document.querySelectorAll('#state-ac .ac-item').forEach(function(el){ el.classList.remove('ac-active'); });
  }

  if (cb.checked) fillSaved();
  cb.addEventListener('change', function() { if (this.checked) fillSaved(); else clearAddr(); });
})();
<?php endif; ?>
</script>
<script src="<?=BASE_URL?>/assets/email-autocomplete.js"></script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
