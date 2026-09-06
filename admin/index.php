<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
if(!isset($_SESSION['admin_id'])) redirect('./login.php');

$paid_statuses = "('PAID','PROCESSING','SHIPPED','COMPLETED')";

$stats = [
  'products'        => $mysqli->query("SELECT COUNT(*) c FROM products")->fetch_assoc()['c'] ?? 0,
  'active_products' => $mysqli->query("SELECT COUNT(*) c FROM products WHERE is_active=1")->fetch_assoc()['c'] ?? 0,
  'orders'          => $mysqli->query("SELECT COUNT(*) c FROM orders")->fetch_assoc()['c'] ?? 0,
  'users'           => $mysqli->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0,
  'admins'          => $mysqli->query("SELECT COUNT(*) c FROM admins")->fetch_assoc()['c'] ?? 0,
  'pending'         => $mysqli->query("SELECT COUNT(*) c FROM orders WHERE status='PENDING'")->fetch_assoc()['c'] ?? 0,
  'paid'            => $mysqli->query("SELECT COUNT(*) c FROM orders WHERE status='PAID'")->fetch_assoc()['c'] ?? 0,
  'processing'      => $mysqli->query("SELECT COUNT(*) c FROM orders WHERE status='PROCESSING'")->fetch_assoc()['c'] ?? 0,
  'shipped'         => $mysqli->query("SELECT COUNT(*) c FROM orders WHERE status='SHIPPED'")->fetch_assoc()['c'] ?? 0,
  'completed'       => $mysqli->query("SELECT COUNT(*) c FROM orders WHERE status='COMPLETED'")->fetch_assoc()['c'] ?? 0,
  'cancelled'       => $mysqli->query("SELECT COUNT(*) c FROM orders WHERE status='CANCELLED'")->fetch_assoc()['c'] ?? 0,
  'total_revenue'   => $mysqli->query("SELECT COALESCE(SUM(total),0) c FROM orders WHERE status IN {$paid_statuses}")->fetch_assoc()['c'] ?? 0,
  'pending_revenue' => $mysqli->query("SELECT COALESCE(SUM(total),0) c FROM orders WHERE status='PENDING'")->fetch_assoc()['c'] ?? 0,
  'recent_orders'   => $mysqli->query("SELECT COUNT(*) c FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'] ?? 0,
  'recent_users'    => $mysqli->query("SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'] ?? 0,
];

$sales_today = $mysqli->query("SELECT COALESCE(SUM(total),0) c FROM orders WHERE status IN {$paid_statuses} AND DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;
$sales_week  = $mysqli->query("SELECT COALESCE(SUM(total),0) c FROM orders WHERE status IN {$paid_statuses} AND created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)")->fetch_assoc()['c'] ?? 0;
$sales_month = $mysqli->query("SELECT COALESCE(SUM(total),0) c FROM orders WHERE status IN {$paid_statuses} AND YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW())")->fetch_assoc()['c'] ?? 0;
$best_seller = $mysqli->query("SELECT p.name, p.image, SUM(oi.qty) units FROM order_items oi JOIN products p ON p.id=oi.product_id JOIN orders o ON o.id=oi.order_id WHERE o.status IN {$paid_statuses} GROUP BY oi.product_id ORDER BY units DESC LIMIT 1")->fetch_assoc();

$recent_orders = $mysqli->query("SELECT id, full_name, email, total, status, created_at FROM orders ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$low_stock     = $mysqli->query("SELECT name, stock FROM products WHERE is_active=1 AND stock <= 10 ORDER BY stock ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Dashboard';
$bodyClass = 'admin-dashboard';
require_once __DIR__.'/includes/header.php';
?>
<header class="page-header">
  <div class="page-header-inner" style="justify-content:space-between;width:100%">
    <div style="display:flex;align-items:center;gap:18px">
      <div class="ph-icon"><i class="fas fa-pepper-hot"></i></div>
      <div>
        <h1>Dashboard</h1>
        <p>Welcome back here's what's happening with your sambal business.</p>
      </div>
    </div>
    <a href="<?=ADMIN_URL?>/export_sales.php" class="btn btn-secondary" style="border-radius:999px;padding:10px 18px;font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:7px">
      <i class="fas fa-file-csv"></i> Export Report
    </a>
  </div>
</header>

<div class="container">

  <!-- Main Stats -->
  <div class="stats-grid">
    <div class="stat-card primary">
      <div class="stat-icon"><i class="fas fa-box"></i></div>
      <div class="stat-content">
        <span class="number"><?=$stats['products']?></span>
        <div class="label">Total Products</div>
        <div class="sub-label"><?=$stats['active_products']?> active</div>
      </div>
    </div>
    <div class="stat-card success">
      <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
      <div class="stat-content">
        <span class="number"><?=$stats['orders']?></span>
        <div class="label">Total Orders</div>
        <div class="sub-label"><?=$stats['recent_orders']?> this week</div>
      </div>
    </div>
    <div class="stat-card warning">
      <div class="stat-icon"><i class="fas fa-clock"></i></div>
      <div class="stat-content">
        <span class="number"><?=$stats['pending']?></span>
        <div class="label">Pending Orders</div>
        <div class="sub-label">Needs attention</div>
      </div>
    </div>
    <div class="stat-card info">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <div class="stat-content">
        <span class="number"><?=$stats['users']?></span>
        <div class="label">Total Customers</div>
        <div class="sub-label"><?=$stats['recent_users']?> new this week</div>
      </div>
    </div>
    <div class="stat-card revenue">
      <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
      <div class="stat-content">
        <span class="number">RM <?=number_format($stats['total_revenue'],2)?></span>
        <div class="label">Total Revenue</div>
        <div class="sub-label">RM <?=number_format($stats['pending_revenue'],2)?> pending</div>
      </div>
    </div>
    <div class="stat-card admin">
      <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
      <div class="stat-content">
        <span class="number"><?=$stats['admins']?></span>
        <div class="label">Admin Users</div>
        <div class="sub-label">System access</div>
      </div>
    </div>
  </div>

  <!-- Sales Analytics -->
  <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-top:0">
    <div class="stat-card" style="--sc:#0ea5e9">
      <div class="stat-icon" style="background:#0ea5e9;color:#fff"><i class="fas fa-sun"></i></div>
      <div class="stat-content">
        <span class="number" style="color:#0ea5e9">RM <?=number_format($sales_today,2)?></span>
        <div class="label">Today's Sales</div>
        <div class="sub-label">Paid orders only</div>
      </div>
    </div>
    <div class="stat-card" style="--sc:#8b5cf6">
      <div class="stat-icon" style="background:#8b5cf6;color:#fff"><i class="fas fa-calendar-week"></i></div>
      <div class="stat-content">
        <span class="number" style="color:#8b5cf6">RM <?=number_format($sales_week,2)?></span>
        <div class="label">This Week</div>
        <div class="sub-label">Mon – today</div>
      </div>
    </div>
    <div class="stat-card" style="--sc:#10b981">
      <div class="stat-icon" style="background:#10b981;color:#fff"><i class="fas fa-calendar-alt"></i></div>
      <div class="stat-content">
        <span class="number" style="color:#10b981">RM <?=number_format($sales_month,2)?></span>
        <div class="label">This Month</div>
        <div class="sub-label"><?=date('F Y')?></div>
      </div>
    </div>
  </div>

  <!-- Order Status Breakdown -->
  <div class="dashboard-section">
    <h2><i class="fas fa-chart-pie"></i> Order Status Breakdown</h2>
    <div class="order-status-grid">
      <?php foreach(['pending','paid','processing','shipped','completed','cancelled'] as $s): ?>
      <div class="status-card <?=$s?>">
        <span class="count"><?=$stats[$s]?></span>
        <span class="label"><?=ucfirst($s)?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Recent Orders + Low Stock -->
  <div class="dashboard-row">
    <div class="dashboard-column">
      <div class="dashboard-card">
        <div class="card-header">
          <h3><i class="fas fa-history"></i> Recent Orders</h3>
          <a href="<?=ADMIN_URL?>/orders.php" class="view-all-btn">View All</a>
        </div>
        <div class="card-content">
          <?php if(empty($recent_orders)): ?>
            <div class="empty-state"><i class="fas fa-shopping-cart"></i><p>No orders yet</p></div>
          <?php else: ?>
            <div class="recent-orders">
              <?php foreach($recent_orders as $order): ?>
              <div class="order-item">
                <div class="order-info">
                  <div class="order-name"><?=e($order['full_name'])?></div>
                  <div class="order-email"><?=e($order['email'])?></div>
                  <div class="order-time"><?=date('M j, Y g:i A', strtotime($order['created_at']))?></div>
                </div>
                <div class="order-details">
                  <div class="order-total">RM <?=number_format($order['total'],2)?></div>
                  <div class="order-status status-<?=strtolower($order['status'])?>"><?=e($order['status'])?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="dashboard-column">
      <div class="dashboard-card">
        <div class="card-header">
          <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h3>
          <a href="<?=ADMIN_URL?>/products.php" class="view-all-btn">Manage</a>
        </div>
        <div class="card-content">
          <?php if(empty($low_stock)): ?>
            <div class="empty-state success"><i class="fas fa-check-circle"></i><p>All products well stocked!</p></div>
          <?php else: ?>
            <div class="low-stock-list">
              <?php foreach($low_stock as $product): ?>
              <div class="stock-item">
                <div class="product-name"><?=e($product['name'])?></div>
                <div class="stock-count <?=$product['stock'] <= 5 ? 'critical' : 'warning'?>"><?=$product['stock']?> left</div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Best Seller + Export -->
  <div class="dashboard-row">
    <div class="dashboard-column">
      <div class="dashboard-card">
        <div class="card-header"><h3><i class="fas fa-trophy"></i> Best-Selling Product</h3></div>
        <div class="card-content">
          <?php if($best_seller): ?>
          <div class="best-seller-widget">
            <img src="<?=e(BASE_URL.'/'.$best_seller['image'])?>" alt="<?=e($best_seller['name'])?>">
            <div>
              <div class="bs-name"><?=e($best_seller['name'])?></div>
              <div class="bs-units"><i class="fas fa-box"></i> <?=(int)$best_seller['units']?> units sold</div>
              <div class="bs-badge"><i class="fas fa-fire"></i> Top performer</div>
            </div>
          </div>
          <?php else: ?>
          <div class="empty-state"><i class="fas fa-trophy"></i><p>No sales data yet</p></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="dashboard-column">
      <div class="dashboard-card">
        <div class="card-header">
          <h3><i class="fas fa-file-csv"></i> Sales Report</h3>
          <a href="<?=ADMIN_URL?>/export_sales.php" class="view-all-btn">Custom</a>
        </div>
        <div class="card-content">
          <p style="color:var(--muted);font-size:.85rem;margin:0 0 14px">Quick export order data as CSV spreadsheet.</p>
          <div style="display:flex;flex-direction:column;gap:8px">
            <a href="<?=ADMIN_URL?>/export_sales.php?period=today" class="export-quick-btn"><i class="fas fa-sun"></i> Today's Orders</a>
            <a href="<?=ADMIN_URL?>/export_sales.php?period=week"  class="export-quick-btn"><i class="fas fa-calendar-week"></i> This Week</a>
            <a href="<?=ADMIN_URL?>/export_sales.php?period=month" class="export-quick-btn"><i class="fas fa-calendar-alt"></i> This Month</a>
            <a href="<?=ADMIN_URL?>/export_sales.php"              class="export-quick-btn export-custom"><i class="fas fa-filter"></i> Custom Date Range...</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Live User Activity -->
  <div class="dashboard-section">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h2 style="margin:0"><span class="online-pulse-dot"></span> Live User Activity</h2>
      <span id="activityLastUpdated" style="color:var(--muted);font-size:.78rem"></span>
    </div>
    <div class="live-activity-grid">
      <div class="dashboard-card">
        <div class="card-header">
          <h3><i class="fas fa-users"></i> Active Users</h3>
          <span id="onlineCount" style="font-size:.78rem;color:var(--muted)"></span>
        </div>
        <div class="card-content" id="activeUsersContainer">
          <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div>
        </div>
      </div>
      <div class="dashboard-card">
        <div class="card-header"><h3><i class="fas fa-stream"></i> Activity Timeline</h3></div>
        <div class="card-content" id="activityFeedContainer" style="max-height:340px;overflow-y:auto">
          <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div>
        </div>
      </div>
    </div>
  </div>

  <!-- System Info -->
  <div class="dashboard-section">
    <h2><i class="fas fa-info-circle"></i> System Information</h2>
    <div class="system-info-grid">
      <div class="info-card">
        <i class="fas fa-server"></i>
        <div class="info-content"><div class="info-label">Database Status</div><div class="info-value success">Connected</div></div>
      </div>
      <div class="info-card">
        <i class="fas fa-calendar"></i>
        <div class="info-content"><div class="info-label">Current Time</div><div class="info-value"><?=date('M j, Y g:i A')?></div></div>
      </div>
      <div class="info-card">
        <i class="fas fa-shield-alt"></i>
        <div class="info-content"><div class="info-label">Security Status</div><div class="info-value success">Secure</div></div>
      </div>
    </div>
  </div>

</div>

<script>
(function () {
  function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function timeAgo(d) {
    var date = new Date(d.replace(' ','T'));
    var diff  = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diff < 60)    return diff + 's ago';
    if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return date.toLocaleDateString();
  }
  function statusDot(status) {
    var c = {online:'#10b981', idle:'#f59e0b', offline:'#9ca3af'};
    return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:'+(c[status]||'#9ca3af')+';flex-shrink:0"></span>';
  }
  function renderUsers(users) {
    var el  = document.getElementById('activeUsersContainer');
    var cnt = document.getElementById('onlineCount');
    var online = users.filter(function(u){ return u.status === 'online'; }).length;
    if (cnt) cnt.textContent = online + ' online now';
    if (!users.length) {
      el.innerHTML = '<div class="empty-state"><i class="fas fa-user-slash"></i><p>No recent user activity</p></div>';
      return;
    }
    el.innerHTML = '<div class="activity-users-list">' + users.map(function(u) {
      return '<div class="activity-user-item">'
        + '<div class="aui-left">' + statusDot(u.status)
        + '<span class="aui-name">' + esc(u.user_name) + '</span>'
        + '<span class="activity-status-badge ' + u.status + '">' + u.status + '</span></div>'
        + '<div class="aui-action">' + esc(u.current_action) + '</div>'
        + '<div class="aui-time">' + timeAgo(u.last_seen) + '</div>'
        + '</div>';
    }).join('') + '</div>';
  }
  function renderFeed(log) {
    var el = document.getElementById('activityFeedContainer');
    if (!log.length) {
      el.innerHTML = '<div class="empty-state"><i class="fas fa-stream"></i><p>No recent activity</p></div>';
      return;
    }
    el.innerHTML = '<div class="activity-timeline">' + log.map(function(e) {
      return '<div class="feed-item">'
        + '<div class="feed-dot"></div>'
        + '<div class="feed-body">'
        + '<span class="feed-user">' + esc(e.user_name) + '</span>'
        + '<span class="feed-action"> ' + esc(e.action) + '</span>'
        + '<div class="feed-time">' + timeAgo(e.created_at) + '</div>'
        + '</div></div>';
    }).join('') + '</div>';
  }
  function fetchActivity() {
    fetch('<?=ADMIN_URL?>/activity_feed.php')
      .then(function(r){ return r.json(); })
      .then(function(data) {
        renderUsers(data.users || []);
        renderFeed(data.log   || []);
        var el = document.getElementById('activityLastUpdated');
        if (el) el.textContent = 'Updated ' + new Date().toLocaleTimeString();
      })
      .catch(function(){});
  }
  fetchActivity();
  setInterval(fetchActivity, 30000);
})();
</script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
