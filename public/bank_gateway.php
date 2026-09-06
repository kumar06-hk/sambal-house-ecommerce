<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

if(!is_logged_in()){
  http_response_code(403);
  exit('Login required.');
}

$bank    = strtoupper(trim($_GET['bank'] ?? ''));
$orderId = (int)($_GET['order_id'] ?? 0);
$uid     = (int)current_user_id();

$banks = [
  'MAYBANK'   => ['name' => 'Maybank',              'abbr' => 'MAY',  'color' => '#F5A000', 'bg' => '#FFFBF0', 'text' => '#3D2A00'],
  'CIMB'      => ['name' => 'CIMB Bank',             'abbr' => 'CIMB', 'color' => '#BE0B0B', 'bg' => '#FFF5F5', 'text' => '#3D0000'],
  'PUBLIC'    => ['name' => 'Public Bank Berhad',    'abbr' => 'PBB',  'color' => '#003087', 'bg' => '#F0F4FF', 'text' => '#001A40'],
  'RHB'       => ['name' => 'RHB Bank',              'abbr' => 'RHB',  'color' => '#7B2D8B', 'bg' => '#FAF0FF', 'text' => '#3A0060'],
  'HONGLEONG' => ['name' => 'Hong Leong Bank',       'abbr' => 'HLB',  'color' => '#0066CC', 'bg' => '#F0F7FF', 'text' => '#00254D'],
  'AMBANK'    => ['name' => 'AmBank',                'abbr' => 'AMB',  'color' => '#C1121F', 'bg' => '#FFF5F5', 'text' => '#3D0008'],
  'BANKISLAM' => ['name' => 'Bank Islam Malaysia',   'abbr' => 'BIMB', 'color' => '#00843D', 'bg' => '#F0FFF6', 'text' => '#002615'],
  'TOUCHNGO'  => ['name' => 'Touch \'n Go eWallet', 'abbr' => 'TNG',  'color' => '#1A9FDD', 'bg' => '#F0FAFF', 'text' => '#003D5C'],
];

if(!$orderId || !isset($banks[$bank])){
  http_response_code(400);
  exit('Invalid payment request.');
}

$stmt = $mysqli->prepare("SELECT id, total, status FROM orders WHERE id=? AND user_id=?");
$stmt->bind_param('ii', $orderId, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if(!$order){
  http_response_code(404);
  exit('Order not found.');
}
if($order['status'] !== 'PENDING'){
  http_response_code(400);
  exit('This payment has already been processed.');
}

$bk       = $banks[$bank];
$amount   = number_format((float)$order['total'], 2);
$payToken = bin2hex(random_bytes(16));
$_SESSION['pay_token_'.$orderId] = $payToken;
$successUrl = APP_URL . BASE_URL . '/payment_success.php?order_id=' . $orderId . '&pay_token=' . $payToken;
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?=e($bk['name'])?> – Secure Payment</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      background: <?=e($bk['bg'])?>;
      color: #1a1a2e;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Top bar */
    .gw-topbar {
      background: <?=e($bk['color'])?>;
      color: #fff;
      padding: 10px 24px;
      display: flex;
      align-items: center;
      gap: 14px;
      box-shadow: 0 2px 10px rgba(0,0,0,.18);
    }
    .gw-topbar-logo {
      width: 46px; height: 46px; border-radius: 50%;
      background: rgba(255,255,255,.18);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; overflow: hidden;
    }
    .gw-topbar-logo img { width: 46px; height: 46px; border-radius: 50%; display: block; }
    .gw-topbar-name  { font-size: 1.1rem; font-weight: 700; letter-spacing: .2px; }
    .gw-topbar-sub   { font-size: .75rem; opacity: .82; }
    .gw-topbar-secure {
      margin-left: auto;
      display: flex; align-items: center; gap: 6px;
      font-size: .78rem; opacity: .88;
      background: rgba(255,255,255,.15);
      padding: 5px 12px; border-radius: 20px;
    }

    /* Layout */
    .gw-body {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px 16px;
    }
    .gw-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 10px 40px rgba(0,0,0,.13);
      width: 100%; max-width: 440px;
      overflow: hidden;
    }

    /* Card header (amount block) */
    .gw-card-head {
      background: <?=e($bk['color'])?>;
      color: #fff;
      padding: 22px 28px 20px;
      text-align: center;
    }
    .gw-card-head-logo {
      width: 68px; height: 68px; border-radius: 50%;
      margin: 0 auto 14px;
      display: block;
      box-shadow: 0 4px 16px rgba(0,0,0,.3);
      background: rgba(255,255,255,.15);
    }
    .gw-head-label  { font-size: .85rem; opacity: .85; margin-bottom: 4px; }
    .gw-head-amount { font-size: 2.6rem; font-weight: 800; letter-spacing: .5px; line-height: 1; }
    .gw-head-cur    { font-size: .82rem; opacity: .78; margin-top: 4px; }

    /* Card body */
    .gw-card-body { padding: 24px 28px; }
    .gw-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 11px 0;
      border-bottom: 1px solid #f0f0f0;
      font-size: .88rem;
    }
    .gw-row:last-of-type { border-bottom: none; }
    .gw-row-label { color: #777; }
    .gw-row-value { font-weight: 600; color: #1a1a2e; }
    .gw-row-value.accent { color: <?=e($bk['color'])?>; }

    /* Secure notice */
    .gw-secure {
      display: flex; align-items: center; gap: 10px;
      margin: 18px 0 20px;
      padding: 11px 16px;
      background: #f0fff6;
      border: 1px solid #c3e6cb;
      border-radius: 10px;
      font-size: .82rem;
      color: #155724;
    }
    .gw-secure i { font-size: 1rem; color: #28a745; flex-shrink: 0; }

    /* Pay button */
    .gw-pay-btn {
      width: 100%;
      padding: 15px;
      background: <?=e($bk['color'])?>;
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 10px;
      transition: filter .15s, transform .1s;
    }
    .gw-pay-btn:hover:not(:disabled) { filter: brightness(1.08); transform: translateY(-1px); }
    .gw-pay-btn:disabled { opacity: .72; cursor: not-allowed; transform: none; filter: none; }

    /* Processing message */
    .gw-status {
      text-align: center;
      margin-top: 14px;
      font-size: .84rem;
      color: #666;
      min-height: 22px;
    }
    .gw-status.success { color: #155724; font-weight: 600; }

    /* Progress bar */
    .gw-progress {
      height: 3px;
      background: #eee;
      border-radius: 2px;
      margin-top: 12px;
      overflow: hidden;
      display: none;
    }
    .gw-progress-bar {
      height: 100%;
      background: <?=e($bk['color'])?>;
      border-radius: 2px;
      width: 0%;
      transition: width 1.8s ease;
    }

    /* Cancel link */
    .gw-cancel {
      display: block; text-align: center;
      margin-top: 14px; font-size: .8rem;
      color: #999; text-decoration: underline; cursor: pointer;
    }
    .gw-cancel:hover { color: #555; }

    /* Footer */
    .gw-card-foot {
      padding: 14px 28px 18px;
      border-top: 1px solid #f0f0f0;
      text-align: center;
      font-size: .75rem;
      color: #aaa;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .gw-card-foot strong { color: #888; }

    @keyframes spin { to { transform: rotate(360deg); } }
    .fa-spin { animation: spin 1s linear infinite; }
  </style>
</head>
<body>

  <div class="gw-topbar">
    <div class="gw-topbar-logo">
      <img src="<?=bank_logo($bank)?>" alt="<?=e($bk['name'])?>">
    </div>
    <div>
      <div class="gw-topbar-name"><?=e($bk['name'])?></div>
      <div class="gw-topbar-sub">Online Banking</div>
    </div>
    <div class="gw-topbar-secure">
      <i class="fas fa-lock"></i> 256-bit SSL Secured
    </div>
  </div>

  <div class="gw-body">
    <div class="gw-card">

      <div class="gw-card-head">
        <img class="gw-card-head-logo" src="<?=bank_logo($bank)?>" alt="<?=e($bk['name'])?>">
        <div class="gw-head-label">Total Payment Amount</div>
        <div class="gw-head-amount">RM <?=e($amount)?></div>
        <div class="gw-head-cur">Malaysian Ringgit (MYR)</div>
      </div>

      <div class="gw-card-body">
        <div class="gw-row">
          <span class="gw-row-label">Merchant</span>
          <span class="gw-row-value">Sambal House</span>
        </div>
        <div class="gw-row">
          <span class="gw-row-label">Order Reference</span>
          <span class="gw-row-value">#<?=$orderId?></span>
        </div>
        <div class="gw-row">
          <span class="gw-row-label">Payment Method</span>
          <span class="gw-row-value">FPX – <?=e($bk['name'])?></span>
        </div>
        <div class="gw-row">
          <span class="gw-row-label">Amount Due</span>
          <span class="gw-row-value accent">RM <?=e($amount)?></span>
        </div>

        <div class="gw-secure">
          <i class="fas fa-shield-alt"></i>
          <span>This is a <strong>secure</strong>, encrypted FPX transaction. Your banking details are never shared with the merchant.</span>
        </div>

        <button class="gw-pay-btn" id="payBtn" onclick="processPayment()">
          <i class="fas fa-lock" id="payIcon"></i>
          <span id="payLabel">Confirm &amp; Pay Now</span>
        </button>

        <div class="gw-progress" id="progressWrap">
          <div class="gw-progress-bar" id="progressBar"></div>
        </div>

        <div class="gw-status" id="statusMsg"></div>

        <a class="gw-cancel" id="cancelLink" onclick="window.close()">
          Cancel and return to merchant
        </a>
      </div>

      <div class="gw-card-foot">
        <i class="fas fa-lock"></i>
        Secured by <strong><?=e($bk['name'])?></strong> &nbsp;|&nbsp; FPX Online Banking
      </div>

    </div>
  </div>

  <script>
  var successUrl = <?=json_encode($successUrl)?>;

  function processPayment() {
    var btn        = document.getElementById('payBtn');
    var payIcon    = document.getElementById('payIcon');
    var payLabel   = document.getElementById('payLabel');
    var statusMsg  = document.getElementById('statusMsg');
    var progressWrap = document.getElementById('progressWrap');
    var progressBar  = document.getElementById('progressBar');
    var cancelLink = document.getElementById('cancelLink');

    btn.disabled = true;
    cancelLink.style.display = 'none';
    payIcon.className = 'fas fa-spinner fa-spin';
    payLabel.textContent = 'Processing Payment...';
    statusMsg.textContent = 'Authorising your transaction, please wait...';

    // Animate progress bar
    progressWrap.style.display = 'block';
    setTimeout(function(){ progressBar.style.width = '90%'; }, 50);

    setTimeout(function() {
      progressBar.style.width = '100%';
      payLabel.textContent = 'Payment Approved';
      payIcon.className = 'fas fa-check-circle';
      statusMsg.className = 'gw-status success';
      statusMsg.textContent = 'Payment successful! Redirecting you back to Sambal House...';

      setTimeout(function() {
        if(window.opener && !window.opener.closed) {
          try {
            window.opener.location.href = successUrl;
            // Give the main window a moment to start navigating, then close this tab
            setTimeout(function(){ window.close(); }, 800);
          } catch(e) {
            // Cross-origin or other error — navigate this tab instead
            window.location.href = successUrl;
          }
        } else {
          // No opener (popup was blocked / same tab fallback)
          window.location.href = successUrl;
        }
      }, 600);
    }, 2000);
  }
  </script>

</body>
</html>
