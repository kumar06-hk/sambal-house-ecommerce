<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
if(!isset($_SESSION['admin_id'])) redirect('./login.php');

$rows = $mysqli->query("
  SELECT p.id, p.name, p.slug, p.price, p.stock, p.is_active,
         COUNT(oi.product_id) as order_count
  FROM products p
  LEFT JOIN order_items oi ON p.id = oi.product_id
  GROUP BY p.id
  ORDER BY p.is_active DESC, p.id DESC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Products';
$bodyClass = 'admin-products';
require_once __DIR__.'/includes/header.php';
?>
<header class="page-header">
  <div class="page-header-inner" style="justify-content:space-between;width:100%">
    <div style="display:flex;align-items:center;gap:18px;">
      <div class="ph-icon"><i class="fas fa-box"></i></div>
      <div><h1>Products</h1><p>Manage your sambal lineup</p></div>
    </div>
    <a class="btn btn-primary" href="<?=ADMIN_URL?>/product_edit.php" style="border-radius:999px;padding:12px 22px;font-weight:600;background:var(--gradient-fire);color:#2b090a"><i class="fas fa-plus"></i> &nbsp;New Product</a>
  </div>
</header>
<div class="container">
  <table class="table">
    <thead>
      <tr><th>#</th><th>Name</th><th>Slug</th><th>Price</th><th>Stock</th><th>Active</th><th>Orders</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?=e($r['id'])?></td>
        <td><?=e($r['name'])?></td>
        <td><?=e($r['slug'])?></td>
        <td>RM <?=number_format($r['price'],2)?></td>
        <td><?=e($r['stock'])?></td>
        <td><?= $r['is_active'] ? 'Yes' : 'No' ?></td>
        <td><?= $r['order_count'] > 0 ? $r['order_count'] : '-' ?></td>
        <td>
          <a class="btn" href="<?=ADMIN_URL?>/product_edit.php?id=<?=$r['id']?>">Edit</a>
          <?php if($r['is_active']): ?>
            <form method="post" action="<?=ADMIN_URL?>/product_deactivate.php" style="display:inline"
                  onsubmit="return confirm('Deactivate this product? It will be hidden from the shop.')">
              <?=csrf_field()?>
              <input type="hidden" name="id" value="<?=(int)$r['id']?>">
              <button type="submit" class="btn">Deactivate</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?=ADMIN_URL?>/product_reactivate.php" style="display:inline"
                  onsubmit="return confirm('Reactivate this product?')">
              <?=csrf_field()?>
              <input type="hidden" name="id" value="<?=(int)$r['id']?>">
              <button type="submit" class="btn">Reactivate</button>
            </form>
          <?php endif; ?>
          <form method="post" action="<?=ADMIN_URL?>/product_delete.php" style="display:inline"
                onsubmit="return confirm('Permanently delete this product? This will also remove it from all orders and carts. This cannot be undone.')">
            <?=csrf_field()?>
            <input type="hidden" name="id" value="<?=(int)$r['id']?>">
            <button type="submit" class="btn btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__.'/includes/footer.php'; ?>
