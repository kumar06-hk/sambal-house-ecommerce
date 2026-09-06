<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
if(!isset($_SESSION['admin_id'])) redirect('./login.php');

$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = ['name'=>'','slug'=>'','price'=>'','stock'=>'','image'=>'','description'=>'','origin_location'=>'','is_active'=>1,'type'=>''];
$errors  = [];

if($id){
  $s = $mysqli->prepare("SELECT name, slug, price, stock, image, description, origin_location, is_active, type FROM products WHERE id=?");
  $s->bind_param('i', $id);
  $s->execute();
  $r = $s->get_result()->fetch_assoc();
  if($r) $product = $r;
}

$uploadDir = __DIR__.'/../public/assets/images/';

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  $name     = trim($_POST['name'] ?? '');
  $slug     = trim($_POST['slug'] ?? '');
  $price    = (float)($_POST['price'] ?? 0);
  $stock    = (int)($_POST['stock'] ?? 0);
  $image    = trim($_POST['image'] ?? '');
  $desc     = trim($_POST['description'] ?? '');
  $location = trim($_POST['origin_location'] ?? '');
  $active   = isset($_POST['is_active']) ? 1 : 0;
  $type     = trim($_POST['type'] ?? '');

  // Handle file upload (takes priority over manual path if a file was chosen)
  if(!empty($_FILES['image_upload']['name'])){
    $file    = $_FILES['image_upload'];
    $maxSize = 5 * 1024 * 1024; // 5 MB
    $extMap  = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];

    if($file['error'] !== UPLOAD_ERR_OK){
      $errors[] = 'Upload failed (error code '.$file['error'].').';
    } elseif($file['size'] > $maxSize){
      $errors[] = 'Image must be under 5 MB.';
    } else {
      // Use server-side MIME detection — never trust the browser-supplied type
      $finfo    = new finfo(FILEINFO_MIME_TYPE);
      $realMime = $finfo->file($file['tmp_name']);
      if(!isset($extMap[$realMime])){
        $errors[] = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
      } else {
        $ext      = $extMap[$realMime];
        $filename = 'product_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        if(move_uploaded_file($file['tmp_name'], $uploadDir.$filename)){
          $image = 'assets/images/'.$filename;
        } else {
          $errors[] = 'Could not save the uploaded file. Check folder permissions.';
        }
      }
    }
  }

  // Strip any HTML/script from text fields before validation and storage
  $name     = strip_tags($name);
  $slug     = strip_tags($slug);
  $desc     = strip_tags($desc);
  $location = strip_tags($location);
  $image    = strip_tags($image);

  if($name === '')  $errors[] = 'Product name is required.';
  elseif(mb_strlen($name) > 200) $errors[] = 'Product name must be under 200 characters.';
  elseif(!preg_match('/^[\p{L}\d\s\'\-\.\(\),&\/]+$/u', $name)) $errors[] = 'Product name contains invalid characters (no URLs, symbols, or scripts).';
  if($location !== '' && !preg_match('/^[\p{L}\d\s,\.\-\/]+$/u', $location)) $errors[] = 'Origin location contains invalid characters.';
  if($image !== '' && !preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $image)) $errors[] = 'Image path may only contain letters, numbers, hyphens, dots and slashes.';
  if(mb_strlen($desc) > 2000) $errors[] = 'Description must be under 2000 characters.';
  if($price <= 0)  $errors[] = 'Price must be greater than 0.';
  if($stock < 0)   $errors[] = 'Stock cannot be negative.';

  if(empty($errors)){
    if($slug === '' && $name !== ''){
      $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name)));
    } else {
      $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', $slug));
    }
    $slug = trim($slug, '-');

    if($id){
      $stmt = $mysqli->prepare("UPDATE products SET name=?, slug=?, price=?, stock=?, image=?, description=?, origin_location=?, is_active=?, type=? WHERE id=?");
      $stmt->bind_param('ssdisssisi', $name, $slug, $price, $stock, $image, $desc, $location, $active, $type, $id);
    } else {
      $stmt = $mysqli->prepare("INSERT INTO products (name, slug, price, stock, image, description, origin_location, is_active, type) VALUES (?,?,?,?,?,?,?,?,?)");
      $stmt->bind_param('ssdisssis', $name, $slug, $price, $stock, $image, $desc, $location, $active, $type);
    }
    if(!$stmt->execute()){
      $errors[] = 'Database error: '.$stmt->error;
    } else {
      redirect(ADMIN_URL.'/products.php');
    }
  }

  // Re-populate form on error
  $product = array_merge($product, compact('name','slug','price','stock','image','desc','location','active','type'));
}

$pageTitle = ($id ? 'Edit' : 'New').' Product';
$bodyClass = 'admin-dashboard';
require_once __DIR__.'/includes/header.php';
?>
<header class="page-header">
  <div class="page-header-inner">
    <div class="ph-icon"><i class="fas fa-<?= $id ? 'pen-to-square' : 'plus' ?>"></i></div>
    <div><h1><?= $id ? 'Edit' : 'Create' ?> Product</h1><p>Update product details &amp; visibility</p></div>
  </div>
</header>
<div class="container">
  <div class="form-container">

    <?php if($errors): ?>
    <div class="alert alert-error">
      <?php foreach($errors as $e): ?><p><?=e($e)?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="product-form-grid">
      <?= csrf_field() ?>

      <!-- LEFT: main fields -->
      <div class="pf-main">
        <div class="form-group">
          <label>Product Name</label>
          <input name="name" value="<?=e($product['name'])?>" required placeholder="Enter product name">
        </div>
        <div class="form-group">
          <label>Slug <span class="field-hint">auto-generated if left blank</span></label>
          <input name="slug" value="<?=e($product['slug'])?>" placeholder="e.g., sambal-original-200g">
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" rows="7" placeholder="Enter product description..."><?=e($product['description'])?></textarea>
        </div>
        <div class="form-group">
          <label>Origin Location</label>
          <input name="origin_location" value="<?=e($product['origin_location'])?>" placeholder="e.g., Johor, Malaysia">
        </div>
      </div>

      <!-- RIGHT: price / stock / image / actions -->
      <div class="pf-side">
        <div class="pf-side-row">
          <div class="form-group">
            <label>Price (RM)</label>
            <input type="number" step="0.01" min="0.01" name="price" value="<?=e($product['price'])?>" required placeholder="0.00">
          </div>
          <div class="form-group">
            <label>Stock</label>
            <input type="number" min="0" name="stock" value="<?=e($product['stock'])?>" required placeholder="0">
          </div>
        </div>

        <div class="form-group">
          <label>Upload Image <span class="field-hint">JPG/PNG/WEBP · max 2 MB</span></label>
          <input type="file" name="image_upload" accept="image/jpeg,image/png,image/webp,image/gif" class="file-input">
        </div>

        <?php if(!empty($product['image'])): ?>
        <div class="current-image-preview">
          <img src="<?=e(BASE_URL.'/'.$product['image'])?>" alt="current">
          <small>Current image</small>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label>Image Path <span class="field-hint">or type manually</span></label>
          <input name="image" value="<?=e($product['image'])?>" placeholder="assets/images/product.png">
        </div>

        <div class="form-group">
          <label>Category</label>
          <select name="type" class="filter-select" style="width:100%">
            <option value="">— Select category —</option>
            <option value="classic"  <?= $product['type']==='classic'  ? 'selected' : '' ?>>Classic</option>
            <option value="ikan"     <?= $product['type']==='ikan'     ? 'selected' : '' ?>>Ikan Bilis</option>
            <option value="seafood"  <?= $product['type']==='seafood'  ? 'selected' : '' ?>>Seafood</option>
            <option value="premium"  <?= $product['type']==='premium'  ? 'selected' : '' ?>>Premium</option>
          </select>
        </div>

        <div class="checkbox-group" style="margin-bottom:20px">
          <input type="checkbox" name="is_active" id="is_active" <?= $product['is_active'] ? 'checked' : '' ?>>
          <label for="is_active">Active (visible in shop)</label>
        </div>

        <div class="form-actions">
          <a href="<?=ADMIN_URL?>/products.php" class="btn btn-secondary">Cancel</a>
          <button class="btn btn-primary" type="submit"><?= $id ? 'Update' : 'Create' ?></button>
        </div>
      </div>

    </form>
  </div>
</div>
<?php require_once __DIR__.'/includes/footer.php'; ?>
