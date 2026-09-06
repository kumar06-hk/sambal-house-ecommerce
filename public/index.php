<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';
if (is_logged_in()) track_activity('Browsing products');

$products = $mysqli->query("SELECT id, name, slug, price, image, stock, origin_location, type FROM products WHERE is_active=1 ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Shop';
require_once __DIR__.'/includes/header.php';
?>
<style>
body {
  background:
    radial-gradient(ellipse 120% 65% at 50% 20%,  rgba(255,56,56,.22)  0%, transparent 60%),
    radial-gradient(ellipse 80%  55% at 88% 35%,  rgba(255,107,74,.18) 0%, transparent 55%),
    radial-gradient(ellipse 70%  55% at 10% 60%,  rgba(255,56,56,.14)  0%, transparent 55%),
    radial-gradient(ellipse 100% 45% at 50% 90%,  rgba(255,56,56,.10)  0%, transparent 55%),
    linear-gradient(180deg, #150809 0%, #0d0607 100%);
  background-attachment: fixed;
}

/* Fixed particle layer — covers whole page at all scroll depths */
.page-particles {
  position: fixed; inset: 0;
  pointer-events: none; z-index: 0;
  background-image:
    radial-gradient(circle at 25% 30%, rgba(255,56,56,.55)  0 2px,   transparent 3px),
    radial-gradient(circle at 75% 70%, rgba(255,182,39,.45) 0 2px,   transparent 3px),
    radial-gradient(circle at 50% 50%, rgba(255,107,74,.45) 0 1.5px, transparent 2.5px);
  background-size: 90px 90px, 130px 130px, 60px 60px;
  opacity: .4;
  animation: drift 18s linear infinite;
}

/* Fixed full-page chili decorations */
.page-chilies {
  position: fixed; inset: 0;
  pointer-events: none; z-index: 0;
  overflow: hidden;
}
/* Each chili is a container with orb + icon */
.pc-deco {
  position: absolute;
  width: 280px; height: 280px;
  display: flex; align-items: center; justify-content: center;
}
.pc-deco .pc-orb {
  position: absolute; inset: 8%;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,107,74,.55) 0%, rgba(255,56,56,.2) 55%, transparent 75%);
  filter: blur(20px);
  animation: pulse 3.2s ease-in-out infinite;
}
.pc-deco i {
  position: relative; z-index: 1;
  font-size: 9.5rem;
  color: var(--accent);
  opacity: .72;
}

/* Left 1 — upper left */
.pc-l1 { top: 20vh; left: -50px; animation: pcFloatL 8s ease-in-out infinite; }
.pc-l1 i { filter: drop-shadow(0 20px 40px rgba(255,56,56,.65)); transform: rotate(22deg); }
.pc-l1 .pc-orb { animation-delay: 0s; }

/* Left 2 — lower left */
.pc-l2 { top: 58vh; left: -40px; animation: pcFloatL 10s ease-in-out infinite 2.8s; }
.pc-l2 i { filter: drop-shadow(0 20px 38px rgba(255,56,56,.6)); transform: rotate(-18deg); }
.pc-l2 .pc-orb { animation-delay: 1.6s; }

/* Right 1 — upper right */
.pc-r1 { top: 12vh; right: -50px; animation: pcFloatR 9s ease-in-out infinite 1s; }
.pc-r1 i { filter: drop-shadow(0 20px 40px rgba(255,56,56,.65)); transform: rotate(-22deg); }
.pc-r1 .pc-orb { animation-delay: 0.5s; }

/* Right 2 — lower right */
.pc-r2 { top: 52vh; right: -40px; animation: pcFloatR 11s ease-in-out infinite 3.5s; }
.pc-r2 i { filter: drop-shadow(0 20px 38px rgba(255,56,56,.6)); transform: rotate(16deg); }
.pc-r2 .pc-orb { animation-delay: 2s; }

@keyframes pcFloatL {
  0%,100% { transform: translateY(0); }
  50%     { transform: translateY(-22px); }
}
@keyframes pcFloatR {
  0%,100% { transform: translateY(0); }
  50%     { transform: translateY(18px); }
}
@media (max-width: 768px) { .page-chilies { display: none; } }

</style>

<!-- Fixed ambient layers (particles + chilies) -->
<div class="page-particles" aria-hidden="true"></div>
<div class="page-chilies" aria-hidden="true">
  <div class="pc-deco pc-l1"><div class="pc-orb"></div><i class="fas fa-pepper-hot"></i></div>
  <div class="pc-deco pc-l2"><div class="pc-orb"></div><i class="fas fa-pepper-hot"></i></div>
  <div class="pc-deco pc-r1"><div class="pc-orb"></div><i class="fas fa-pepper-hot"></i></div>
  <div class="pc-deco pc-r2"><div class="pc-orb"></div><i class="fas fa-pepper-hot"></i></div>
</div>

<!-- 🔥 HERO -->
<section class="hero">
  <div class="hero-glow" aria-hidden="true"></div>
  <div class="hero-inner">
    <div>
      <span class="hero-eyebrow"><i class="fas fa-fire"></i> Authentic · Handcrafted · Small-Batch</span>
      <h1>Bring the <span class="flame">heat</span><br>home with every spoon.</h1>
      <p class="lead">Slow-cooked Malaysian sambal made from real chilies, fresh aromatics and zero shortcuts. Our handcrafted sambal collection, ready to set your table on fire.
      <div class="hero-cta">
        <a href="#shop" class="btn btn-primary"><i class="fas fa-cart-shopping"></i> &nbsp;Shop Sambal</a>
        <a href="<?=BASE_URL?>/team.php" class="btn btn-ghost"><i class="fas fa-users"></i> &nbsp;Meet the Team</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><div class="num">1</div><div class="lbl">Signature flavors</div></div>
        <div class="hero-stat"><div class="num">100%</div><div class="lbl">Real chilies</div></div>
        <div class="hero-stat"><div class="num">🌶️🌶️🌶️</div><div class="lbl">Heat level</div></div>
      </div>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="glow-orb"></div>
      <div class="chili"><i class="fas fa-pepper-hot"></i></div>
      <span class="spark s1"></span><span class="spark s2"></span><span class="spark s3"></span>
    </div>
  </div>
</section>

<!-- Feature strip -->
<div class="feature-strip">
  <div class="feat"><i class="fas fa-leaf"></i><div><div class="ft">Fresh ingredients</div><div class="fs">No preservatives</div></div></div>
  <div class="feat"><i class="fas fa-fire-flame-curved"></i><div><div class="ft">Slow-cooked heat</div><div class="fs">Traditional recipe</div></div></div>
  <div class="feat"><i class="fas fa-truck-fast"></i><div><div class="ft">Fast delivery</div><div class="fs">Across Malaysia</div></div></div>
  <div class="feat"><i class="fas fa-lock"></i><div><div class="ft">Secure checkout</div><div class="fs">Pay your way</div></div></div>
</div>

<div class="container" id="shop">
  <div class="section-header">
    <h2>Our Sambal</h2>
    <p class="section-subtitle">Handcrafted chili pastes, made fresh in small batches</p>
  </div>

  <?php if(is_logged_in()): ?>
  <div class="search-filter-section">
    <div class="search-container">
      <div class="search-input-group">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="Search sambal products..." class="search-input">
      </div>
    </div>
    <div class="filter-container">
      <div class="filter-group">
        <label for="priceFilter">Price Range:</label>
        <select id="priceFilter" class="filter-select">
          <option value="">All Prices</option>
          <option value="u13">Under RM 13</option>
          <option value="13-20">RM 13 – RM 20</option>
          <option value="20-30">RM 20 – RM 30</option>
          <option value="30-40">RM 30 – RM 40</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="sortBy">Sort By:</label>
        <select id="sortBy" class="filter-select">
          <option value="name-asc">Name A-Z</option>
          <option value="name-desc">Name Z-A</option>
          <option value="price-asc">Price Low to High</option>
          <option value="price-desc">Price High to Low</option>
          <option value="stock-desc">Most Stock</option>
          <option value="stock-asc">Least Stock</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="stockFilter">Stock Status:</label>
        <select id="stockFilter" class="filter-select">
          <option value="">All Products</option>
          <option value="in-stock">In Stock (10+)</option>
          <option value="low-stock">Low Stock (1-10)</option>
          <option value="out-of-stock">Out of Stock</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="categoryFilter">Category:</label>
        <select id="categoryFilter" class="filter-select">
          <option value="">All Categories</option>
          <option value="classic">Classic</option>
          <option value="ikan">Ikan Bilis</option>
          <option value="seafood">Seafood</option>
          <option value="premium">Premium</option>
        </select>
      </div>
      <button type="button" id="resetFilters" class="reset-filters-btn">
        <i class="fas fa-undo"></i> Reset Filters
      </button>
    </div>
    <div class="results-info">
      <span id="resultsCount">Showing all products</span>
    </div>
  </div>
  <?php endif; ?>

  <div class="grid" id="productsGrid">
    <?php foreach($products as $p):
      $stock = (int)$p['stock'];
      if($stock > 10)      { $badgeClass = 'green'; $badgeText = "In stock: $stock";  $stockStatus = 'in-stock'; }
      elseif($stock > 0)   { $badgeClass = 'low';   $badgeText = "Low stock: $stock"; $stockStatus = 'low-stock'; }
      else                 { $badgeClass = 'oos';   $badgeText = "Out of stock";       $stockStatus = 'out-of-stock'; }
      $ptype = $p['type'] ?? '';
    ?>
      <div class="card product-card"
           data-name="<?=strtolower(e($p['name']))?>"
           data-price="<?=$p['price']?>"
           data-stock="<?=$stock?>"
           data-stock-status="<?=$stockStatus?>"
           data-type="<?=$ptype?>">
        <div class="badges">
          <span class="badge <?=$badgeClass?>"><?=$badgeText?></span>
        </div>
        <img src="<?=e($p['image'])?>" alt="<?=e($p['name'])?>">
        <div class="card-body">
          <h3 class="product-name"><?=e($p['name'])?></h3>
          <?php if(!empty($p['origin_location'])): ?>
          <div class="product-location"><i class="fas fa-map-marker-alt"></i> <?=e($p['origin_location'])?></div>
          <?php endif; ?>
          <div class="price">RM <?=number_format($p['price'],2)?></div>
          <?php if(is_logged_in()): ?>
            <a class="btn btn-primary view-btn" href="<?=BASE_URL?>/product.php?slug=<?=e($p['slug'])?>"><i class="fas fa-eye"></i> View Details</a>
          <?php else: ?>
            <button class="btn btn-primary view-btn login-required-btn" onclick="showLoginPrompt()">
              <i class="fas fa-lock"></i> Login to View
            </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div id="noResults" class="no-results" style="display:none;">
    <i class="fas fa-search"></i>
    <h3>No products found</h3>
    <p>Try adjusting your search terms or filters</p>
    <button type="button" id="clearAllFilters" class="btn btn-primary">Clear All Filters</button>
  </div>
</div>

<script>
let allProducts = [];
let filteredProducts = [];
let searchTimeout = null;

document.addEventListener('DOMContentLoaded', function() {
  allProducts = Array.from(document.querySelectorAll('.product-card'));
  filteredProducts = [...allProducts];

  <?php if(is_logged_in()): ?>
  initializeFilters();
  <?php else: ?>
  displayProducts();
  updateResultsCount();
  <?php endif; ?>
});

function initializeFilters() {
  const searchInput    = document.getElementById('searchInput');
  const priceFilter    = document.getElementById('priceFilter');
  const sortBy         = document.getElementById('sortBy');
  const stockFilter    = document.getElementById('stockFilter');
  const categoryFilter = document.getElementById('categoryFilter');
  const resetBtn       = document.getElementById('resetFilters');
  const clearBtn       = document.getElementById('clearAllFilters');

  searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(filterProducts, 300);
  });

  [priceFilter, sortBy, stockFilter, categoryFilter].forEach(function(el) {
    if(el) el.addEventListener('change', filterProducts);
  });

  if(resetBtn) resetBtn.addEventListener('click', resetAllFilters);
  if(clearBtn) clearBtn.addEventListener('click', resetAllFilters);

  filteredProducts = [...allProducts];
  displayProducts();
  updateResultsCount();
}

function filterProducts() {
  const searchTerm  = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
  const priceRange  = document.getElementById('priceFilter')?.value || '';
  const stockStatus = document.getElementById('stockFilter')?.value || '';
  const category    = document.getElementById('categoryFilter')?.value || '';

  filteredProducts = allProducts.filter(function(p) {
    const name  = p.dataset.name || '';
    const price = parseFloat(p.dataset.price) || 0;
    const ss    = p.dataset.stockStatus || '';

    if(searchTerm && !name.includes(searchTerm)) return false;

    if(priceRange) {
      if(priceRange === 'u13'  && price >= 13)                 return false;
      if(priceRange === '13-20'&& (price < 13 || price >= 20)) return false;
      if(priceRange === '20-30'&& (price < 20 || price >= 30)) return false;
      if(priceRange === '30-40'&& (price < 30 || price >  40)) return false;
    }

    if(stockStatus && ss !== stockStatus) return false;

    if(category) {
      const ptype = p.dataset.type || '';
      if(ptype !== category) return false;
    }
    return true;
  });

  sortProducts();
  displayProducts();
  updateResultsCount();
}

function sortProducts() {
  const sortVal = document.getElementById('sortBy')?.value || 'name-asc';
  filteredProducts.sort(function(a, b) {
    const na = a.dataset.name || '', nb = b.dataset.name || '';
    const pa = parseFloat(a.dataset.price)||0, pb = parseFloat(b.dataset.price)||0;
    const sa = parseInt(a.dataset.stock)||0,  sb = parseInt(b.dataset.stock)||0;
    if(sortVal === 'name-asc')   return na.localeCompare(nb);
    if(sortVal === 'name-desc')  return nb.localeCompare(na);
    if(sortVal === 'price-asc')  return pa - pb;
    if(sortVal === 'price-desc') return pb - pa;
    if(sortVal === 'stock-desc') return sb - sa;
    if(sortVal === 'stock-asc')  return sa - sb;
    return 0;
  });
}

function displayProducts() {
  const grid      = document.getElementById('productsGrid');
  const noResults = document.getElementById('noResults');
  allProducts.forEach(function(p) { p.style.display = 'none'; p.classList.remove('product-card-visible'); });
  if(filteredProducts.length > 0) {
    grid.style.display = 'grid';
    noResults.style.display = 'none';
    filteredProducts.forEach(function(p, i) {
      grid.appendChild(p);
      setTimeout(function() { p.style.display = 'flex'; p.classList.add('product-card-visible'); }, i * 50);
    });
  } else {
    grid.style.display = 'none';
    noResults.style.display = 'block';
  }
}

function updateResultsCount() {
  const el = document.getElementById('resultsCount');
  if(!el) return;
  const total    = allProducts.length;
  const filtered = filteredProducts.length;
  el.textContent = filtered === total ? 'Showing all '+total+' products' : 'Showing '+filtered+' of '+total+' products';
}

function resetAllFilters() {
  const searchInput    = document.getElementById('searchInput');
  const priceFilter    = document.getElementById('priceFilter');
  const sortBy         = document.getElementById('sortBy');
  const stockFilter    = document.getElementById('stockFilter');
  const categoryFilter = document.getElementById('categoryFilter');
  if(searchInput)    searchInput.value    = '';
  if(priceFilter)    priceFilter.value    = '';
  if(sortBy)         sortBy.value         = 'name-asc';
  if(stockFilter)    stockFilter.value    = '';
  if(categoryFilter) categoryFilter.value = '';
  clearTimeout(searchTimeout);
  filteredProducts = [...allProducts];
  sortProducts();
  displayProducts();
  updateResultsCount();
}

function showLoginPrompt() {
  Swal.fire({
    title: 'Login Required',
    html: `<p style="font-size:1.1rem;margin-bottom:20px;">Please sign in to view product details and make purchases.</p>
           <div style="display:flex;gap:15px;justify-content:center;">
             <a href="<?=BASE_URL?>/login.php" class="btn btn-primary" style="text-decoration:none;padding:11px 28px;border-radius:50px;">Sign In</a>
             <a href="<?=BASE_URL?>/register.php" class="btn btn-secondary" style="text-decoration:none;padding:11px 28px;border-radius:50px;">Create Account</a>
           </div>`,
    showConfirmButton: false,
    showCancelButton: true,
    cancelButtonText: 'Maybe Later',
    cancelButtonColor: '#6c757d',
    width: '500px'
  });
}


</script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
