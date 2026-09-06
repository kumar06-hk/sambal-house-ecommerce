<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/helpers.php';

$pageTitle = 'Our Team';
require_once __DIR__.'/includes/header.php';
?>
<style>
.team-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  max-width: 1100px;
  margin: 0 auto;
}
.team-card {
  background: linear-gradient(180deg, #1c0d0f 0%, #160a0c 100%);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 20px;
  padding: 32px 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  transition: transform .25s, box-shadow .25s;
  position: relative;
  overflow: hidden;
}
.team-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--gradient-fire);
  opacity: 0;
  transition: opacity .25s;
}
.team-card:hover { transform: translateY(-6px); box-shadow: 0 20px 48px -16px rgba(255,56,56,.35); }
.team-card:hover::before { opacity: 1; }
.team-avatar {
  width: 72px; height: 72px; border-radius: 18px;
  background: linear-gradient(135deg, #2a0e10 0%, #1a0a0c 100%);
  border: 1px solid rgba(255,56,56,.2);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.8rem; color: var(--accent);
  margin-bottom: 18px;
}
.team-name {
  font-size: .95rem; font-weight: 900; color: var(--accent);
  text-transform: uppercase; letter-spacing: .5px;
  line-height: 1.4; margin: 0 0 6px;
}
.team-id {
  font-size: .78rem; color: var(--muted);
  font-family: monospace; margin-bottom: 12px;
}
.team-badge {
  display: inline-block;
  font-size: .72rem; font-weight: 700;
  padding: 5px 14px; border-radius: 999px;
  background: rgba(255,56,56,.12); color: var(--accent);
  border: 1px solid rgba(255,56,56,.3);
  margin-bottom: 24px;
  line-height: 1.5;
}
.team-badge.leader {
  background: rgba(245,158,11,.12); color: #f59e0b;
  border-color: rgba(245,158,11,.3);
}
.team-divider {
  width: 100%; height: 1px;
  background: rgba(255,255,255,.06);
  margin-bottom: 20px;
}
.contrib-group { width: 100%; margin-bottom: 16px; text-align: left; }
.contrib-group-title {
  font-size: .65rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1.5px; color: var(--muted); margin: 0 0 7px;
}
.contrib-list {
  list-style: none; margin: 0; padding: 0;
  display: flex; flex-direction: column; gap: 5px;
}
.contrib-list li {
  display: flex; align-items: flex-start; gap: 8px;
  font-size: .81rem; color: #c7aeb2; line-height: 1.4;
}
.contrib-list li::before {
  content: '';
  width: 5px; height: 5px; border-radius: 50%;
  background: var(--accent); flex-shrink: 0; margin-top: 6px;
}

@media (max-width: 900px) {
  .team-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
  .team-grid { grid-template-columns: 1fr; }
}
</style>

<header class="page-header">
  <div class="page-header-inner">
    <div class="ph-icon"><i class="fas fa-users"></i></div>
    <div><h1>Meet Our Team</h1><p>The hot heads behind Sambal House</p></div>
  </div>
</header>

<div class="container" style="padding-bottom: 60px">
  <div class="team-grid">

    <!-- Jerry -->
    <article class="team-card">
      <div class="team-avatar"><i class="fas fa-pepper-hot"></i></div>
      <h2 class="team-name">Jerry Jeremiah<br>A/L Raymond Rao</h2>
      <p class="team-id">Student ID: 1201101725</p>
      <span class="team-badge leader">Team Leader · Full Stack Developer · Database &amp; Security</span>
      <div class="team-divider"></div>

      <div class="contrib-group">
        <div class="contrib-group-title">Authentication &amp; Security</div>
        <ul class="contrib-list">
          <li>User registration with password strength meter (uppercase, lowercase, number, symbol)</li>
          <li>Admin login portal &amp; session management</li>
          <li>CSRF token verification, XSS escaping &amp; SQL injection prevention via prepared statements</li>
          <li>BCrypt password hashing on all stored credentials</li>
          <li>Forgot password with email OTP flow</li>
          <li>Welcome email sent automatically on new registration</li>
          <li>Inline change-password form: BCrypt current-password check, live strength meter, eye-toggle</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">User Profile &amp; Email Verification</div>
        <ul class="contrib-list">
          <li>User profile page (name, nickname, phone, full address)</li>
          <li>Malaysian phone number validation (frontend regex + backend)</li>
          <li>Email change with OTP verification flow</li>
          <li>Email autocomplete (25-domain whitelist) with real-time domain validation</li>
          <li>Required address field enforcement before checkout</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">FPX Payment System</div>
        <ul class="contrib-list">
          <li>Bank selection grid in checkout (8 banks incl. Touch 'n Go eWallet)</li>
          <li>Bank gateway simulator branded per bank colour (bank_gateway.php)</li>
          <li>Payment success confirmation page with order summary</li>
          <li>Bank logo auto-detect (PNG priority, SVG fallback)</li>
          <li>Atomic PENDING → PAID status update on gateway callback</li>
          <li>Popup-blocker-safe flow via window.open() with fallback</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Admin Dashboard &amp; Analytics</div>
        <ul class="contrib-list">
          <li>Admin dashboard overview with live order &amp; revenue stats</li>
          <li>Sales analytics cards (today / this week / this month)</li>
          <li>Best-selling product tracker</li>
          <li>Sales report CSV export with date range filters</li>
          <li>Product management: add, edit, deactivate, reactivate, delete</li>
          <li>Product category field (Classic, Ikan Bilis, Seafood, Premium)</li>
          <li>Admin approve return &amp; approve refund actions with live 15-second page polling</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Favourites &amp; Wishlist System</div>
        <ul class="contrib-list">
          <li>Heart button on every product page with AJAX toggle (no page reload)</li>
          <li>Favourites page with 4-column grid, saved date, stock badge, and remove animation</li>
          <li>Navbar Favourites link with live count badge updated on toggle</li>
          <li>favourite_toggle.php JSON endpoint returning new favourited state and total count</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Product Review &amp; Rating System</div>
        <ul class="contrib-list">
          <li>Star rating (1–5) + written review per product on completed orders</li>
          <li>"Rate" button on My Orders page opens a modal listing all unreviewed items</li>
          <li>"My Review" button shows a read-back modal with product image, stars, text and date</li>
          <li>Reviews displayed on every product page: aggregate score, star breakdown bars, masked reviewer names</li>
          <li>One review per user per product enforced via UNIQUE KEY with ON DUPLICATE KEY UPDATE for editing</li>
        </ul>
      </div>
    </article>

    <!-- Harikumar -->
    <article class="team-card">
      <div class="team-avatar"><i class="fas fa-pepper-hot"></i></div>
      <h2 class="team-name">Harikumar<br>A/L Malairaju</h2>
      <p class="team-id">Student ID: 1211109990</p>
      <span class="team-badge">Team Member · Frontend Developer · UI/UX Designer</span>
      <div class="team-divider"></div>

      <div class="contrib-group">
        <div class="contrib-group-title">Product Catalogue &amp; Filtering</div>
        <ul class="contrib-list">
          <li>Product catalogue page with live search (debounced 300 ms)</li>
          <li>Multi-filter system: price range, stock status, category, sort order</li>
          <li>Filter + sort combination with animated card reflow</li>
          <li>Results counter and "No products found" empty state with reset button</li>
          <li>Login-required gate for non-authenticated visitors (View Details locked)</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Product Detail Page Redesign</div>
        <ul class="contrib-list">
          <li>Clean two-column layout: image lightbox left, product info right</li>
          <li>Breadcrumb navigation (Home / Products / Product Name)</li>
          <li>Inline average rating row linking to the reviews section</li>
          <li>Price box with red left-border highlight and quantity stepper</li>
          <li>Origin, stock status, and description info rows</li>
          <li>"You May Also Like" 4-column grid with View button and consistent card heights</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Shopping Cart</div>
        <ul class="contrib-list">
          <li>Cart page with quantity stepper (+ / − buttons) and stock cap enforcement</li>
          <li>Update cart and proceed to checkout flow</li>
          <li>Out-of-stock and capped-quantity warnings per item</li>
          <li>Cart item count displayed live in the navbar</li>
          <li>Checkout page with saved-address autofill ("Use my saved address")</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Receipt &amp; PDF Generation</div>
        <ul class="contrib-list">
          <li>Customer receipt page for all paid orders</li>
          <li>PDF receipt download using jsPDF (client-side, no server dependency)</li>
          <li>Print-friendly receipt layout</li>
          <li>Admin order detail view with full shipping &amp; item breakdown</li>
          <li>Customer purchase summary card (total orders, lifetime spend, last order date)</li>
          <li>Admin invoice PDF download &amp; print</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Full Site Styling &amp; UI System</div>
        <ul class="contrib-list">
          <li>Full custom CSS with zero frameworks (hand-written from scratch)</li>
          <li>Dark-theme CSS variable system (--bg, --card, --accent, --muted, --gradient-fire)</li>
          <li>Card hover lift animations, gradient borders and transitions throughout</li>
          <li>Reusable page-header component used across all pages</li>
          <li>Responsive layout for mobile and tablet on every page</li>
          <li>Empty-state cards, status badge colour system, flash alert styling</li>
          <li>Navbar with hamburger mobile menu and active-state highlighting</li>
        </ul>
      </div>
    </article>

    <!-- Avinnaesh -->
    <article class="team-card">
      <div class="team-avatar"><i class="fas fa-pepper-hot"></i></div>
      <h2 class="team-name">Avinnaesh<br>A/L G Baramesvaran</h2>
      <p class="team-id">Student ID: 1211101658</p>
      <span class="team-badge">Team Member · Backend Developer · Systems &amp; Debugging</span>
      <div class="team-divider"></div>

      <div class="contrib-group">
        <div class="contrib-group-title">Order Management System</div>
        <ul class="contrib-list">
          <li>My Orders tabbed layout: 7 status tabs (All, To Pay, To Ship, To Receive, Completed, Cancelled, Return / Refund)</li>
          <li>Per-tab count badges and colour-coded order cards</li>
          <li>Customer order cancellation (PENDING &amp; PAID) with automatic stock restoration</li>
          <li>Pay Now retry bank-picker modal for incomplete FPX payments</li>
          <li>Stock validation on order placement to prevent overselling</li>
          <li>Admin order list with status dropdown; CANCELLED/RETURNED orders locked as read-only badges</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Cart &amp; Session Helpers</div>
        <ul class="contrib-list">
          <li>get_or_create_cart() helper for persistent guest and logged-in cart</li>
          <li>cart_totals() helper for live item count and order subtotal</li>
          <li>Session-based guest cart (no login required to browse and add items)</li>
          <li>Stale session guard that auto-clears dangling sessions for deleted accounts</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Live Order &amp; Shipment Tracker</div>
        <ul class="contrib-list">
          <li>5-step live tracker: Placed → Paid → Shipped Out → Received → Completed</li>
          <li>Fully API-driven auto-progression every 60 seconds (no cron job)</li>
          <li>MySQL TIMESTAMPDIFF for timezone-safe elapsed calculations</li>
          <li>Status-specific countdown label and icon-pop animation per step</li>
          <li>SweetAlert2 toast notification on each step advancement</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Refund &amp; Return Tracker</div>
        <ul class="contrib-list">
          <li>3-step cancellation refund tracker (orange theme): Requested → Admin Approved → Refunded</li>
          <li>3-step return tracker (teal theme): Return Requested → Admin Approved → Refunded</li>
          <li>Both flows require admin approval before refund is processed (no auto-bypass)</li>
          <li>Deterministic Request ID, copy-to-clipboard, refund amount and payment method details</li>
          <li>Polling API updates tracker live without full page reload</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Transactional Emails</div>
        <ul class="contrib-list">
          <li>Order receipt email triggered after payment confirmed (PAID status), not on creation</li>
          <li>Order cancellation email with full item list and order details</li>
          <li>Welcome email on new account registration</li>
          <li>OTP emails for forgot-password and email-change flows</li>
        </ul>
      </div>

      <div class="contrib-group">
        <div class="contrib-group-title">Admin Live Monitoring</div>
        <ul class="contrib-list">
          <li>Live user activity section on admin dashboard (Online / Idle / Offline)</li>
          <li>activity_feed.php JSON endpoint with 30-second auto-refresh</li>
          <li>Real-time activity timeline feed showing what each user is doing</li>
          <li>Admin orders page auto-polls every 15 seconds; toast notification on new order or return request</li>
        </ul>
      </div>
    </article>

  </div>
</div>

<?php require_once __DIR__.'/includes/footer.php'; ?>
