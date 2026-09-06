<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
if (!isset($_SESSION['admin_id'])) redirect('./login.php');

$period    = $_GET['period']    ?? '';
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to']   ?? '');

$download = $period && ($period !== 'custom' || ($date_from && $date_to));

if ($download) {
    $where  = '1=1';
    $params = [];
    $types  = '';

    if ($period === 'today') {
        $where .= " AND DATE(created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $where .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
    } elseif ($period === 'month') {
        $where .= " AND YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW())";
    } elseif ($period === 'custom') {
        $where .= " AND DATE(created_at) BETWEEN ? AND ?";
        $params = [$date_from, $date_to];
        $types  = 'ss';
    }

    $sql = "SELECT id, full_name, email, payment_method, status, total, created_at FROM orders WHERE $where ORDER BY created_at DESC";
    if ($params) {
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $rows = $mysqli->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    $filename = 'sambal-house-sales-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Order ID', 'Customer Name', 'Customer Email', 'Payment Method', 'Status', 'Total (RM)', 'Date']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['id'],
            $row['full_name'],
            $row['email'],
            $row['payment_method'],
            $row['status'],
            number_format((float)$row['total'], 2),
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Export Sales Report';
$bodyClass = 'admin-dashboard';
require_once __DIR__.'/includes/header.php';
?>
<style>
.export-wrap {
  max-width: 520px;
  margin: 0 auto;
  padding-bottom: 60px;
}
.export-card {
  background: linear-gradient(180deg,#1c0d0f 0%,#160a0c 100%);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 18px;
  overflow: hidden;
}
.export-card-header {
  padding: 18px 24px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  font-size: .82rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 8px;
}
.export-card-header i { color: var(--accent); }
.period-options {
  display: flex;
  flex-direction: column;
  gap: 0;
}
.period-option {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 24px;
  cursor: pointer;
  border-bottom: 1px solid rgba(255,255,255,.04);
  transition: background .15s;
  user-select: none;
}
.period-option:last-child { border-bottom: none; }
.period-option:hover { background: rgba(255,255,255,.03); }
.period-option.selected { background: rgba(255,56,56,.06); }
.period-option input[type="radio"] { display: none; }
.period-radio-dot {
  width: 20px; height: 20px;
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,.2);
  flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: border-color .15s;
}
.period-option.selected .period-radio-dot {
  border-color: var(--accent);
}
.period-radio-dot::after {
  content: '';
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--accent);
  opacity: 0;
  transition: opacity .15s;
}
.period-option.selected .period-radio-dot::after { opacity: 1; }
.period-icon {
  width: 38px; height: 38px;
  border-radius: 10px;
  background: rgba(255,56,56,.1);
  border: 1px solid rgba(255,56,56,.15);
  display: flex; align-items: center; justify-content: center;
  color: var(--accent);
  font-size: .9rem;
  flex-shrink: 0;
}
.period-option.selected .period-icon {
  background: rgba(255,56,56,.18);
  border-color: rgba(255,56,56,.3);
}
.period-label { font-size: .92rem; font-weight: 600; color: var(--text); }
.period-sub   { font-size: .76rem; color: var(--muted); margin-top: 2px; }

.custom-range {
  padding: 16px 24px;
  border-top: 1px solid rgba(255,255,255,.06);
  display: none;
  gap: 12px;
}
.custom-range.visible { display: grid; grid-template-columns: 1fr 1fr; }
.custom-range label {
  font-size: .78rem; color: var(--muted);
  display: block; margin-bottom: 6px; font-weight: 600;
}
.custom-range input[type="date"] {
  width: 100%;
  padding: 10px 12px;
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 10px;
  color: var(--text);
  font-size: .88rem;
  outline: none;
  box-sizing: border-box;
  transition: border-color .2s;
}
.custom-range input[type="date"]:focus { border-color: var(--accent); }

.export-actions {
  padding: 20px 24px;
  border-top: 1px solid rgba(255,255,255,.06);
  display: flex;
  gap: 10px;
}
.btn-export {
  flex: 1;
  padding: 13px;
  background: var(--gradient-fire);
  color: #2b090a;
  border: none;
  border-radius: 12px;
  font-size: .92rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: opacity .15s;
}
.btn-export:hover { opacity: .88; }
.btn-back-export {
  padding: 13px 20px;
  background: rgba(255,255,255,.06);
  color: var(--text);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 12px;
  font-size: .88rem;
  font-weight: 600;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: background .15s;
}
.btn-back-export:hover { background: rgba(255,255,255,.1); }
.export-note {
  margin-top: 14px;
  padding: 13px 18px;
  background: rgba(255,255,255,.03);
  border: 1px solid rgba(255,255,255,.05);
  border-radius: 12px;
  font-size: .81rem;
  color: var(--muted);
  display: flex;
  align-items: flex-start;
  gap: 10px;
  line-height: 1.55;
}
.export-note i { color: var(--accent); flex-shrink: 0; margin-top: 1px; }
</style>

<header class="page-header">
  <div class="page-header-inner">
    <div class="ph-icon"><i class="fas fa-file-csv"></i></div>
    <div>
      <h1>Export Sales Report</h1>
      <p>Download order data as a CSV spreadsheet</p>
    </div>
  </div>
</header>

<div class="container">
  <div class="export-wrap">
    <form method="get" id="exportForm">
      <div class="export-card">

        <div class="export-card-header">
          <i class="fas fa-calendar-alt"></i> Select Date Range
        </div>

        <div class="period-options">

          <label class="period-option <?=($period==='today')?'selected':''?>" onclick="selectPeriod(this)">
            <input type="radio" name="period" value="today" <?=($period==='today')?'checked':''?>>
            <div class="period-radio-dot"></div>
            <div class="period-icon"><i class="fas fa-sun"></i></div>
            <div>
              <div class="period-label">Today</div>
              <div class="period-sub"><?=date('d M Y')?></div>
            </div>
          </label>

          <label class="period-option <?=($period==='week')?'selected':''?>" onclick="selectPeriod(this)">
            <input type="radio" name="period" value="week" <?=($period==='week')?'checked':''?>>
            <div class="period-radio-dot"></div>
            <div class="period-icon"><i class="fas fa-calendar-week"></i></div>
            <div>
              <div class="period-label">This Week</div>
              <div class="period-sub">Monday – Today</div>
            </div>
          </label>

          <label class="period-option <?=($period==='month')?'selected':''?>" onclick="selectPeriod(this)">
            <input type="radio" name="period" value="month" <?=($period==='month')?'checked':''?>>
            <div class="period-radio-dot"></div>
            <div class="period-icon"><i class="fas fa-calendar-days"></i></div>
            <div>
              <div class="period-label">This Month</div>
              <div class="period-sub"><?=date('F Y')?></div>
            </div>
          </label>

          <label class="period-option <?=($period==='custom')?'selected':''?>" onclick="selectPeriod(this)">
            <input type="radio" name="period" value="custom" <?=($period==='custom')?'checked':''?>>
            <div class="period-radio-dot"></div>
            <div class="period-icon"><i class="fas fa-sliders"></i></div>
            <div>
              <div class="period-label">Custom Range</div>
              <div class="period-sub">Pick any start &amp; end date</div>
            </div>
          </label>

        </div>

        <div class="custom-range <?=($period==='custom')?'visible':''?>" id="customRange">
          <div>
            <label>From</label>
            <input type="date" name="date_from" value="<?=e($date_from)?>">
          </div>
          <div>
            <label>To</label>
            <input type="date" name="date_to" value="<?=e($date_to)?>">
          </div>
        </div>

        <div class="export-actions">
          <button type="submit" class="btn-export">
            <i class="fas fa-download"></i> Download CSV
          </button>
          <a href="<?=ADMIN_URL?>/index.php" class="btn-back-export">
            <i class="fas fa-arrow-left"></i> Back
          </a>
        </div>

      </div>
    </form>

    <div class="export-note">
      <i class="fas fa-circle-info"></i>
      The report includes all orders matching the selected period. Open the CSV in Excel or Google Sheets.
    </div>
  </div>
</div>

<script>
function selectPeriod(el) {
  document.querySelectorAll('.period-option').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input[type="radio"]').checked = true;
  document.getElementById('customRange').classList.toggle('visible', el.querySelector('input').value === 'custom');
}
</script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
