<?php

// ── Internal SMTP helper (Gmail SSL, port 465) ──────────────────────────────
function _smtp_send(string $to_email, string $subject, string $html): bool {
    $host = 'ssl://smtp.gmail.com';
    $port = 465;
    $user = GMAIL_USER;
    $pass = GMAIL_PASS;
    $from = 'Sambal House <' . GMAIL_USER . '>';

    $sock = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$sock) {
        error_log('[Mailer] fsockopen failed: '.$errstr.' ('.$errno.')');
        return false;
    }

    $read = function() use ($sock) { return fgets($sock, 512); };
    $send = function($cmd) use ($sock) { fputs($sock, $cmd."\r\n"); };

    $read(); // greeting

    $send('EHLO localhost');
    while ($line = $read()) { if ($line[3] === ' ') break; }

    $send('AUTH LOGIN');
    $read();
    $send(base64_encode($user));
    $read();
    $send(base64_encode($pass));
    $auth = $read();
    if (strpos($auth, '235') === false) {
        error_log('[Mailer] AUTH failed: '.$auth);
        fclose($sock);
        return false;
    }

    $send('MAIL FROM:<'.GMAIL_USER.'>');
    $read();
    $send('RCPT TO:<'.$to_email.'>');
    $read();
    $send('DATA');
    $read();

    $message  ="From: {$from}\r\n"
              . "To: {$to_email}\r\n"
              . "Subject: {$subject}\r\n"
              . "MIME-Version: 1.0\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "\r\n"
              . $html
              . "\r\n.\r\n";

    fputs($sock, $message);
    $result = $read();

    $send('QUIT');
    fclose($sock);

    return strpos($result, '250') !== false;
}

// ── Public mail functions ────────────────────────────────────────────────────

function send_welcome_email(string $to_email, string $to_name): bool {
    $name_safe  = htmlspecialchars($to_name, ENT_QUOTES, 'UTF-8');
    $shop_url   = APP_URL . BASE_URL . '/index.php';
    $profile_url = APP_URL . BASE_URL . '/profile.php';

    $html = <<<WELCOMEHTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#100708;font-family:ui-sans-serif,system-ui,Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#100708;padding:40px 16px">
    <tr><td align="center">
      <table width="500" cellpadding="0" cellspacing="0" style="max-width:500px;width:100%;background:#1a0c0e;border:1px solid rgba(255,56,56,.25);border-radius:16px;overflow:hidden">
        <tr>
          <td style="background:linear-gradient(135deg,#1e0507 0%,#2a0a0c 100%);padding:36px 36px 28px;text-align:center;border-bottom:1px solid rgba(255,56,56,.18)">
            <div style="font-size:2.8rem;margin-bottom:12px">🌶️</div>
            <h1 style="color:#ff3838;margin:0;font-size:1.6rem;font-weight:900">Welcome to Sambal House!</h1>
            <p style="color:#c7aeb2;margin:10px 0 0;font-size:.9rem">Your account has been created successfully</p>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 36px;color:#ffecef">
            <p style="margin:0 0 14px;font-size:1rem">Hi <strong style="color:#ff3838">{$name_safe}</strong> 👋,</p>
            <p style="color:#c7aeb2;margin:0 0 24px;font-size:.93rem;line-height:1.7">
              Thank you for joining <strong style="color:#ffecef">Sambal House</strong>, your new home for handcrafted Malaysian chili pastes. We're glad to have you with us!
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:26px;border-radius:12px;overflow:hidden">
              <tr>
                <td style="background:#100708;border:1px solid rgba(255,56,56,.15);border-radius:12px;padding:20px 22px">
                  <p style="color:#c7aeb2;font-size:.72rem;text-transform:uppercase;letter-spacing:1.5px;margin:0 0 12px;font-weight:700">What you can do now</p>
                  <table cellpadding="0" cellspacing="0" width="100%">
                    <tr><td style="padding:6px 0;color:#c7aeb2;font-size:.88rem">🛒 &nbsp;Browse our full sambal catalogue</td></tr>
                    <tr><td style="padding:6px 0;color:#c7aeb2;font-size:.88rem">💳 &nbsp;Checkout with FPX online banking</td></tr>
                    <tr><td style="padding:6px 0;color:#c7aeb2;font-size:.88rem">📦 &nbsp;Track your orders in My Orders</td></tr>
                    <tr><td style="padding:6px 0;color:#c7aeb2;font-size:.88rem">👤 &nbsp;Complete your delivery address in Profile</td></tr>
                  </table>
                </td>
              </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px">
              <tr>
                <td style="padding-right:8px" width="50%">
                  <a href="{$shop_url}" style="display:block;text-align:center;background:#ff3838;color:#fff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700;font-size:.9rem">Shop Now &rarr;</a>
                </td>
                <td style="padding-left:8px" width="50%">
                  <a href="{$profile_url}" style="display:block;text-align:center;background:rgba(255,56,56,.12);color:#ff3838;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700;font-size:.9rem;border:1px solid rgba(255,56,56,.3)">My Profile</a>
                </td>
              </tr>
            </table>

            <hr style="border:none;border-top:1px solid rgba(255,255,255,.07);margin:0 0 18px">
            <p style="color:#c7aeb2;font-size:.78rem;margin:0;line-height:1.6">If you did not create this account, please ignore this email.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:14px 36px;text-align:center;border-top:1px solid rgba(255,255,255,.05)">
            <p style="color:#c7aeb2;font-size:.74rem;margin:0">&copy; Sambal House &nbsp;|&nbsp; Handcrafted Malaysian chili pastes</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>
WELCOMEHTML;

    return _smtp_send($to_email, 'Welcome to Sambal House, ' . $to_name . '! 🌶️', $html);
}

function send_password_reset_email(string $to_email, string $to_name, string $otp, string $token): bool {
    $reset_link = APP_URL . BASE_URL . '/reset_password.php?token=' . urlencode($token);
    $name_safe  = htmlspecialchars($to_name, ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#100708;font-family:ui-sans-serif,system-ui,Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#100708;padding:40px 16px">
    <tr><td align="center">
      <table width="500" cellpadding="0" cellspacing="0" style="max-width:500px;width:100%;background:#1a0c0e;border:1px solid rgba(255,56,56,.25);border-radius:16px;overflow:hidden">
        <tr>
          <td style="background:linear-gradient(135deg,#1e0507 0%,#2a0a0c 100%);padding:32px 36px;text-align:center;border-bottom:1px solid rgba(255,56,56,.18)">
            <div style="font-size:2.4rem;margin-bottom:10px">🌶️</div>
            <h1 style="color:#ff3838;margin:0;font-size:1.5rem;font-weight:900">Sambal House</h1>
            <p style="color:#c7aeb2;margin:8px 0 0;font-size:.88rem">Password Reset Request</p>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 36px;color:#ffecef">
            <p style="margin:0 0 16px;font-size:1rem">Hi <strong style="color:#ff3838">{$name_safe}</strong>,</p>
            <p style="color:#c7aeb2;margin:0 0 28px;font-size:.93rem;line-height:1.65">We received a request to reset your password. Use the OTP code below <strong style="color:#ffecef">or</strong> click the button to reset directly. Both expire in <strong style="color:#ffecef">15 minutes</strong>.</p>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px">
              <tr><td align="center" style="background:#100708;border:2px dashed rgba(255,56,56,.5);border-radius:12px;padding:26px">
                <p style="color:#c7aeb2;margin:0 0 10px;font-size:.75rem;text-transform:uppercase;letter-spacing:2px">One-Time Password</p>
                <div style="font-size:2.8rem;font-weight:900;color:#ff3838;letter-spacing:14px;font-variant-numeric:tabular-nums">{$otp}</div>
                <p style="color:#c7aeb2;margin:10px 0 0;font-size:.78rem">Enter this code on the verification page</p>
              </td></tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:26px">
              <tr>
                <td style="border-top:1px solid rgba(255,255,255,.08)"></td>
                <td style="padding:0 14px;color:#c7aeb2;font-size:.8rem;white-space:nowrap">or reset directly</td>
                <td style="border-top:1px solid rgba(255,255,255,.08)"></td>
              </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px">
              <tr><td align="center">
                <a href="{$reset_link}" style="display:inline-block;background:#ff3838;color:#fff;text-decoration:none;padding:14px 36px;border-radius:999px;font-weight:700;font-size:.95rem;letter-spacing:.3px">Reset My Password &rarr;</a>
              </td></tr>
            </table>

            <hr style="border:none;border-top:1px solid rgba(255,255,255,.07);margin:0 0 18px">
            <p style="color:#c7aeb2;font-size:.78rem;margin:0;line-height:1.6">If you didn't request a password reset, you can safely ignore this email.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:14px 36px;text-align:center;border-top:1px solid rgba(255,255,255,.05)">
            <p style="color:#c7aeb2;font-size:.74rem;margin:0">&copy; Sambal House &nbsp;|&nbsp; Handcrafted Malaysian chili pastes</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;

    return _smtp_send($to_email, 'Your Sambal House Password Reset Request', $html);
}

function send_email_change_otp(string $to_email, string $to_name, string $otp): bool {
    $name_safe = htmlspecialchars($to_name, ENT_QUOTES, 'UTF-8');

    $html = <<<HTML2
<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#100708;font-family:ui-sans-serif,system-ui,Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#100708;padding:40px 16px">
    <tr><td align="center">
      <table width="500" cellpadding="0" cellspacing="0" style="max-width:500px;width:100%;background:#1a0c0e;border:1px solid rgba(255,56,56,.25);border-radius:16px;overflow:hidden">
        <tr>
          <td style="background:linear-gradient(135deg,#1e0507 0%,#2a0a0c 100%);padding:32px 36px;text-align:center;border-bottom:1px solid rgba(255,56,56,.18)">
            <div style="font-size:2.4rem;margin-bottom:10px">🌶️</div>
            <h1 style="color:#ff3838;margin:0;font-size:1.5rem;font-weight:900">Sambal House</h1>
            <p style="color:#c7aeb2;margin:8px 0 0;font-size:.88rem">Email Address Change</p>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 36px;color:#ffecef">
            <p style="margin:0 0 16px;font-size:1rem">Hi <strong style="color:#ff3838">{$name_safe}</strong>,</p>
            <p style="color:#c7aeb2;margin:0 0 28px;font-size:.93rem;line-height:1.65">We received a request to change your email address to this inbox. Enter the code below to confirm. It expires in <strong style="color:#ffecef">15 minutes</strong>.</p>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px">
              <tr><td align="center" style="background:#100708;border:2px dashed rgba(255,56,56,.5);border-radius:12px;padding:26px">
                <p style="color:#c7aeb2;margin:0 0 10px;font-size:.75rem;text-transform:uppercase;letter-spacing:2px">Verification Code</p>
                <div style="font-size:2.8rem;font-weight:900;color:#ff3838;letter-spacing:14px;font-variant-numeric:tabular-nums">{$otp}</div>
                <p style="color:#c7aeb2;margin:10px 0 0;font-size:.78rem">Enter this code on the verification page</p>
              </td></tr>
            </table>

            <hr style="border:none;border-top:1px solid rgba(255,255,255,.07);margin:0 0 18px">
            <p style="color:#c7aeb2;font-size:.78rem;margin:0;line-height:1.6">If you didn't request this change, you can safely ignore this email. Your current email will remain unchanged.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:14px 36px;text-align:center;border-top:1px solid rgba(255,255,255,.05)">
            <p style="color:#c7aeb2;font-size:.74rem;margin:0">&copy; Sambal House &nbsp;|&nbsp; Handcrafted Malaysian chili pastes</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML2;

    return _smtp_send($to_email, 'Verify Your New Sambal House Email Address', $html);
}

function send_order_receipt(string $to_email, string $to_name, array $ord, array $items): bool {
    $name_safe  = htmlspecialchars($to_name, ENT_QUOTES, 'UTF-8');
    $order_id   = (int)$ord['id'];
    $status     = htmlspecialchars($ord['status'] ?? 'PENDING', ENT_QUOTES, 'UTF-8');
    $total      = number_format((float)($ord['total'] ?? 0), 2);
    $payment    = htmlspecialchars($ord['payment_method'] ?? '', ENT_QUOTES, 'UTF-8');
    $date_str   = isset($ord['created_at']) ? date('d M Y, h:i A', strtotime($ord['created_at'])) : date('d M Y, h:i A');
    $addr_parts = array_filter([$ord['address_line1'] ?? '', $ord['address_line2'] ?? '', ($ord['city'] ?? '').', '.($ord['state_region'] ?? '').' '.($ord['postcode'] ?? '')]);
    $addr       = htmlspecialchars(implode(', ', $addr_parts), ENT_QUOTES, 'UTF-8');
    $s_color    = ($status === 'PAID') ? '#10b981' : '#f59e0b';
    $s_bg       = ($status === 'PAID') ? 'rgba(16,185,129,.15)' : 'rgba(245,158,11,.15)';

    $rows = '';
    foreach ($items as $item) {
        $n  = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
        $p  = number_format((float)$item['price'], 2);
        $q  = (int)$item['qty'];
        $st = number_format((float)$item['price'] * $q, 2);
        $rows .= "<tr>
          <td style='padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.06);color:#ffecef'>{$n}</td>
          <td style='padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.06);color:#c7aeb2;text-align:center'>&times;{$q}</td>
          <td style='padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.06);color:#c7aeb2;text-align:right'>RM {$p}</td>
          <td style='padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.06);color:#ff3838;font-weight:700;text-align:right'>RM {$st}</td>
        </tr>";
    }

    $html = <<<MAILHTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#100708;font-family:ui-sans-serif,system-ui,Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#100708;padding:40px 16px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#1a0c0e;border:1px solid rgba(255,56,56,.25);border-radius:16px;overflow:hidden">
        <tr>
          <td style="background:linear-gradient(135deg,#1e0507 0%,#2a0a0c 100%);padding:32px 36px;text-align:center;border-bottom:1px solid rgba(255,56,56,.18)">
            <div style="font-size:2.2rem;margin-bottom:8px">&#127798;</div>
            <h1 style="color:#ff3838;margin:0;font-size:1.5rem;font-weight:900">Sambal House</h1>
            <p style="color:#c7aeb2;margin:8px 0 12px;font-size:.88rem">Payment Confirmation</p>
            <span style="display:inline-block;background:{$s_bg};color:{$s_color};border:1px solid {$s_color};padding:4px 16px;border-radius:20px;font-size:.78rem;font-weight:700;letter-spacing:1px">{$status}</span>
          </td>
        </tr>
        <tr>
          <td style="padding:28px 36px 0;color:#ffecef">
            <p style="margin:0 0 6px;font-size:1rem">Hi <strong style="color:#ff3838">{$name_safe}</strong>,</p>
            <p style="color:#c7aeb2;margin:0 0 22px;font-size:.9rem;line-height:1.65">Your payment for order <strong style="color:#ffecef">#{$order_id}</strong> has been confirmed. Here is your receipt.</p>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;background:#100708;border-radius:10px">
              <tr>
                <td style="padding:12px 16px;border-right:1px solid rgba(255,255,255,.06)">
                  <div style="color:#c7aeb2;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Order ID</div>
                  <div style="color:#ffecef;font-weight:700">#{$order_id}</div>
                </td>
                <td style="padding:12px 16px;border-right:1px solid rgba(255,255,255,.06)">
                  <div style="color:#c7aeb2;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Date</div>
                  <div style="color:#ffecef;font-weight:700">{$date_str}</div>
                </td>
                <td style="padding:12px 16px">
                  <div style="color:#c7aeb2;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Payment</div>
                  <div style="color:#ffecef;font-weight:700">{$payment}</div>
                </td>
              </tr>
            </table>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px">
              <tr>
                <td style="padding:12px 16px;background:rgba(255,255,255,.03);border-radius:8px;border-left:3px solid #ff3838">
                  <div style="color:#c7aeb2;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">&#128230; Delivery Address</div>
                  <div style="color:#ffecef;font-size:.9rem">{$addr}</div>
                </td>
              </tr>
            </table>
            <table width="100%" cellpadding="0" cellspacing="0" style="border-radius:8px;overflow:hidden">
              <thead>
                <tr style="background:#100708">
                  <th style="padding:9px 14px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:#c7aeb2;font-weight:700">Item</th>
                  <th style="padding:9px 14px;text-align:center;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:#c7aeb2;font-weight:700">Qty</th>
                  <th style="padding:9px 14px;text-align:right;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:#c7aeb2;font-weight:700">Price</th>
                  <th style="padding:9px 14px;text-align:right;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:#c7aeb2;font-weight:700">Total</th>
                </tr>
              </thead>
              <tbody>{$rows}</tbody>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 36px 28px">
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px">
              <tr>
                <td style="text-align:right;padding:10px 0;border-top:1px solid rgba(255,255,255,.08)">
                  <span style="color:#c7aeb2;font-size:.9rem">Order Total &nbsp;</span>
                  <span style="color:#ff3838;font-size:1.2rem;font-weight:900">RM {$total}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:14px 36px;text-align:center;border-top:1px solid rgba(255,255,255,.05)">
            <p style="color:#c7aeb2;font-size:.74rem;margin:0">&copy; Sambal House &nbsp;|&nbsp; Handcrafted Malaysian chili pastes. Thank you for your order!</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>
MAILHTML;

    return _smtp_send($to_email, "Payment Confirmed - Order #{$order_id} | Sambal House", $html);
}

function send_order_cancellation(string $to_email, string $to_name, array $ord, array $items): bool {
    $name_safe   = htmlspecialchars($to_name, ENT_QUOTES, 'UTF-8');
    $order_id    = (int)$ord['id'];
    $total       = number_format((float)($ord['total'] ?? 0), 2);
    $payment     = htmlspecialchars($ord['payment_method'] ?? '', ENT_QUOTES, 'UTF-8');
    $ordered_str = isset($ord['created_at']) ? date('d M Y, h:i A', strtotime($ord['created_at'])) : date('d M Y, h:i A');
    $cancel_str  = date('d M Y, h:i A');
    $addr_parts  = array_filter([$ord['address_line1'] ?? '', $ord['address_line2'] ?? '', ($ord['city'] ?? '').', '.($ord['state_region'] ?? '').' '.($ord['postcode'] ?? '')]);
    $addr        = htmlspecialchars(implode(', ', $addr_parts), ENT_QUOTES, 'UTF-8');

    $rows = '';
    foreach ($items as $item) {
        $n  = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
        $p  = number_format((float)$item['price'], 2);
        $q  = (int)$item['qty'];
        $st = number_format((float)$item['price'] * $q, 2);
        $rows .= "<tr>
          <td style='padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.06);color:#ffecef'>{$n}</td>
          <td style='padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.06);color:#c7aeb2;text-align:center'>&times;{$q}</td>
          <td style='padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.06);color:#c7aeb2;text-align:right'>RM {$p}</td>
          <td style='padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.06);color:#f87171;font-weight:700;text-align:right'>RM {$st}</td>
        </tr>";
    }

    $html = <<<CANCELHTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#100708;font-family:ui-sans-serif,system-ui,Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#100708;padding:40px 16px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#1a0c0e;border:1px solid rgba(244,67,54,.3);border-radius:16px;overflow:hidden">
        <tr>
          <td style="background:linear-gradient(135deg,#1e0507 0%,#2a0a0c 100%);padding:32px 36px;text-align:center;border-bottom:1px solid rgba(244,67,54,.2)">
            <div style="font-size:2.2rem;margin-bottom:8px">&#127798;</div>
            <h1 style="color:#ff3838;margin:0;font-size:1.5rem;font-weight:900">Sambal House</h1>
            <p style="color:#c7aeb2;margin:8px 0 12px;font-size:.88rem">Order Cancellation</p>
            <span style="display:inline-block;background:rgba(244,67,54,.15);color:#f87171;border:1px solid rgba(244,67,54,.4);padding:4px 16px;border-radius:20px;font-size:.78rem;font-weight:700;letter-spacing:1px">CANCELLED</span>
          </td>
        </tr>
        <tr>
          <td style="padding:28px 36px 0;color:#ffecef">
            <p style="margin:0 0 6px;font-size:1rem">Hi <strong style="color:#ff3838">{$name_safe}</strong>,</p>
            <p style="color:#c7aeb2;margin:0 0 22px;font-size:.9rem;line-height:1.65">Your order <strong style="color:#ffecef">#{$order_id}</strong> has been successfully cancelled. Here is a summary of the cancelled order.</p>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;background:#100708;border-radius:10px">
              <tr>
                <td style="padding:12px 16px;border-right:1px solid rgba(255,255,255,.06)">
                  <div style="color:#c7aeb2;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Order ID</div>
                  <div style="color:#ffecef;font-weight:700">#{$order_id}</div>
                </td>
                <td style="padding:12px 16px;border-right:1px solid rgba(255,255,255,.06)">
                  <div style="color:#c7aeb2;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Ordered On</div>
                  <div style="color:#ffecef;font-weight:700">{$ordered_str}</div>
                </td>
                <td style="padding:12px 16px;border-right:1px solid rgba(255,255,255,.06)">
                  <div style="color:#c7aeb2;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Cancelled On</div>
                  <div style="color:#f87171;font-weight:700">{$cancel_str}</div>
                </td>
                <td style="padding:12px 16px">
                  <div style="color:#c7aeb2;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Payment</div>
                  <div style="color:#ffecef;font-weight:700">{$payment}</div>
                </td>
              </tr>
            </table>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px">
              <tr>
                <td style="padding:12px 16px;background:rgba(255,255,255,.03);border-radius:8px;border-left:3px solid #f44336">
                  <div style="color:#c7aeb2;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">&#128230; Delivery Address</div>
                  <div style="color:#ffecef;font-size:.9rem">{$addr}</div>
                </td>
              </tr>
            </table>
            <table width="100%" cellpadding="0" cellspacing="0" style="border-radius:8px;overflow:hidden">
              <thead>
                <tr style="background:#100708">
                  <th style="padding:9px 14px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:#c7aeb2;font-weight:700">Item</th>
                  <th style="padding:9px 14px;text-align:center;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:#c7aeb2;font-weight:700">Qty</th>
                  <th style="padding:9px 14px;text-align:right;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:#c7aeb2;font-weight:700">Price</th>
                  <th style="padding:9px 14px;text-align:right;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:#c7aeb2;font-weight:700">Total</th>
                </tr>
              </thead>
              <tbody>{$rows}</tbody>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 36px 28px">
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px">
              <tr>
                <td style="text-align:right;padding:10px 0;border-top:1px solid rgba(255,255,255,.08)">
                  <span style="color:#c7aeb2;font-size:.9rem">Cancelled Total &nbsp;</span>
                  <span style="color:#f87171;font-size:1.2rem;font-weight:900">RM {$total}</span>
                </td>
              </tr>
            </table>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px">
              <tr><td style="padding:14px 16px;background:rgba(244,67,54,.08);border:1px solid rgba(244,67,54,.2);border-radius:10px">
                <p style="margin:0;color:#c7aeb2;font-size:.85rem;line-height:1.6">If you paid via FPX online banking, any refund will be processed according to your bank's policy. If you have questions, please contact us.</p>
              </td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:14px 36px;text-align:center;border-top:1px solid rgba(255,255,255,.05)">
            <p style="color:#c7aeb2;font-size:.74rem;margin:0">&copy; Sambal House &nbsp;|&nbsp; Handcrafted Malaysian chili pastes. We hope to serve you again!</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>
CANCELHTML;

    return _smtp_send($to_email, "Order #{$order_id} Cancelled | Sambal House", $html);
}
